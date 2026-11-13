<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Support\CustomerSession;
use App\Support\EmailVerification;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** "An account with this email already exists." must mean exactly that; every other insert failure is a logged 500. */
class RegistrationDuplicateTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        config(['mail.default' => 'array']);
    }

    private function register(string $email)
    {
        return $this->postJson('/auth/register', ['name' => 'Dee Dupe', 'email' => $email, 'password' => 'Correct123']);
    }

    /** Make the next INSERT into customers fail the way the driver would (SQLSTATE, driver code, message). */
    private function failCustomerInsert(string $driverMessage, string $class = UniqueConstraintViolationException::class, string $sqlstate = '23000', int $code = 1062): void
    {
        $pdo = new PDOException("SQLSTATE[{$sqlstate}]: Integrity constraint violation: {$code} {$driverMessage}");
        $pdo->errorInfo = [$sqlstate, $code, $driverMessage];
        DB::beforeExecuting(function (string $query) use ($pdo, $class) {
            if (str_starts_with(strtolower($query), 'insert into `customers`')) {
                throw new $class('mysql', $query, [], $pdo);
            }
        });
    }

    private function assertNoSensitiveData(array $context): void
    {
        $json = json_encode($context);
        $this->assertStringNotContainsString('@example.test', $json);
        $this->assertStringNotContainsString('Correct123', $json);
        $this->assertStringNotContainsString('$2y$', $json);   // password hash
    }

    public function test_existing_email_is_rejected_before_insert_whatever_the_case_or_spaces(): void
    {
        Log::spy();
        $id = $this->makeCustomer('dup@example.test');
        $this->register('  DUP@Example.TEST ')->assertStatus(422)->assertJson(['message' => 'An account with this email already exists.']);
        $this->assertSame(1, DB::table('customers')->where('email', 'dup@example.test')->count());
        $this->assertNull(session(EmailVerification::PENDING));
        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($id) {
            $this->assertNoSensitiveData($context);

            return $message === 'Registration rejected: email already registered' && $context['check'] === 'pre-insert'
                && $context['customer_id'] === $id && $context['database'] === DB::connection()->getDatabaseName();
        })->once();
    }

    public function test_email_key_violation_from_a_race_is_already_exists_mariadb_format(): void
    {
        Log::spy();
        $this->failCustomerInsert("Duplicate entry 'race@example.test' for key 'email'");
        $this->register('race@example.test')->assertStatus(422)->assertJson(['message' => 'An account with this email already exists.']);
        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $this->assertNoSensitiveData($context);

            return $context['constraint'] === 'email' && $context['sqlstate'] === '23000' && $context['driver_code'] === 1062 && $context['check'] === 'unique-key';
        })->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_email_key_violation_mysql8_format(): void
    {
        $this->failCustomerInsert("Duplicate entry 'race@example.test' for key 'customers.email'");
        $this->register('race@example.test')->assertStatus(422)->assertJson(['message' => 'An account with this email already exists.']);
    }

    public function test_primary_key_violation_is_not_reported_as_an_existing_email(): void
    {
        Log::spy();
        $this->failCustomerInsert("Duplicate entry '12' for key 'PRIMARY'");
        $res = $this->register('fresh@example.test')->assertStatus(500);
        $this->assertSame('We could not create your account right now. Please try again shortly.', $res->json('message'));
        $this->assertNull(session(EmailVerification::PENDING));
        $this->assertNull(session(CustomerSession::ID));
        Log::shouldHaveReceived('error')->withArgs(function ($message, $context) {
            $this->assertNoSensitiveData($context);

            return $message === 'Registration insert failed' && $context['constraint'] === 'PRIMARY'
                && $context['sqlstate'] === '23000' && $context['driver_code'] === 1062
                && $context['exception'] === UniqueConstraintViolationException::class;
        })->once();
    }

    public function test_phone_key_violation_is_not_reported_as_an_existing_email(): void
    {
        $this->failCustomerInsert("Duplicate entry '0400000000' for key 'phone'");
        $this->register('fresh@example.test')->assertStatus(500)->assertJsonMissing(['message' => 'An account with this email already exists.']);
    }

    public function test_other_database_errors_are_logged_accurately(): void
    {
        Log::spy();
        $this->failCustomerInsert("Data too long for column 'first_name' at row 1", QueryException::class, '22001', 1406);
        $this->register('fresh@example.test')->assertStatus(500);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $context['constraint'] === null
            && $context['sqlstate'] === '22001' && $context['driver_code'] === 1406 && $context['exception'] === QueryException::class)->once();
    }

    public function test_constraint_name_parsing(): void
    {
        $make = function (string $message) {
            $pdo = new PDOException($message);
            $pdo->errorInfo = ['23000', 1062, $message];

            return new UniqueConstraintViolationException('mysql', 'insert', [], $pdo);
        };
        $this->assertSame('email', AuthController::violatedKey($make("Duplicate entry 'a@b.c' for key 'email'")));
        $this->assertSame('email', AuthController::violatedKey($make("Duplicate entry 'a@b.c' for key 'customers.email'")));
        $this->assertSame('PRIMARY', AuthController::violatedKey($make("Duplicate entry '5' for key 'PRIMARY'")));
        $this->assertSame('email', AuthController::violatedKey($make('UNIQUE constraint failed: customers.email')));
        $this->assertNull(AuthController::violatedKey($make('something else')));
    }

    public function test_concurrent_style_double_submit_creates_one_account(): void
    {
        $this->register('twice@example.test')->assertOk();
        $this->flushSession();
        $this->register('twice@example.test')->assertStatus(422);
        $this->assertSame(1, DB::table('customers')->where('email', 'twice@example.test')->count());
    }
}

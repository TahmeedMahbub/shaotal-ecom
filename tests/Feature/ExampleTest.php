<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL test database (the home page reads the catalog schema).');
        }

        $this->get('/')->assertStatus(200);
    }
}

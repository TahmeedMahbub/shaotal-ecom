<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Admin-managed business configuration: THE one reader of the `config_data` table (created and edited by the admin
 * app; columns id, key, data, modified_by, modified_at). Read values only through StoreConfig::get().
 *
 * Every `data` value is JSON: a number (12), a string ("Free pickup"), true/false, null, a list (["cod"]) or an
 * object ({"label": "..."}). KEYS below lists each key and the shape it must have. All keys are required rows; a value
 * that may be switched off is a JSON null, never a missing row. Nothing falls back to a built-in value: a missing
 * table, a missing key or a value of the wrong shape throws ConfigDataException naming the key.
 *
 * Loading: the first get() of a request reads all keys in one query (the `key` column is unique, so indexed); the
 * values are kept for the rest of that request / queue job only (scoped singleton, AppServiceProvider), so admin
 * changes apply on the next request.
 */
final class StoreConfig
{
    /**
     * key => shape. Shapes: "number" (>= 0), "percent" (0-100), "int" (whole number >= 0), "bool", "string" (non-empty),
     * "string[]" (list of strings); a leading "?" also allows null; an array is a JSON object whose listed fields are all
     * required (with their own shapes).
     */
    public const KEYS = [
        'shipping_fee_adelaide' => 'number',
        'shipping_fee_australia' => 'number',
        'free_shipping_threshold' => '?number',
        'pickup_enabled' => 'bool',
        'pickup_location' => ['label' => 'string', 'suburb' => 'string', 'state' => 'string', 'postcode' => 'string', 'details' => '?string'],
        'online_payment_discount_percent' => 'percent',
        'online_payment_deposit_percent' => 'percent',
        'online_payment_balance_terms' => 'string',
        'gift_wrap_base_fee' => 'number',
        'gift_wrap_base_items' => 'int',
        'gift_wrap_extra_fee' => 'number',
        'payment_methods' => 'string[]',
        'customer_cancellable_statuses' => 'string[]',
        'returns_enabled' => 'bool',
        'returns_window_days' => '?int',
        'return_reasons' => 'string[]',
        'approved_info_pages' => 'string[]',
        'contact_details' => ['email' => '?string', 'phone' => '?string', 'hours' => '?string'],
        'delivery_copy' => [
            'announcement' => '?string', 'checkout_subtitle' => '?string',
            'checkout_promise' => '?string', 'estimate' => '?string', 'dispatch_status' => '?string',
            'timeframe_title' => '?string', 'timeframe' => '?string', 'rates_title' => '?string', 'rates' => '?string',
            'returns_title' => '?string', 'returns_text' => '?string',
        ],
    ];

    /** Values used instead of the database (tests only; see fake()). */
    private static ?array $fake = null;

    /** @var array<string, ?string>|null raw `data` per key, loaded once per request/job */
    private ?array $raw = null;

    /** @var array<string, mixed> validated values */
    private array $values = [];

    /** The validated value of a configuration key (see KEYS for its shape). */
    public static function get(string $key): mixed
    {
        return app(self::class)->value($key);
    }

    /**
     * Tests: serve these values instead of reading config_data (merged into any earlier fake). null stops faking.
     * Values must still have the documented shape.
     */
    public static function fake(?array $values): void
    {
        self::$fake = $values === null ? null : array_merge(self::$fake ?? [], $values);
        if (app()->resolved(self::class)) {
            app()->forgetInstance(self::class);
        }
    }

    /** Every key that is missing or invalid, as "key: reason" (deploy check). Throws only if the table can't be read. */
    public static function problems(): array
    {
        $config = app(self::class);
        $out = [];
        foreach (array_keys(self::KEYS) as $key) {
            try {
                $config->value($key);
            } catch (ConfigDataException $e) {
                if ($e->tableUnreadable) {
                    throw $e;
                }
                $out[] = $e->getMessage();
            }
        }

        return $out;
    }

    public function value(string $key): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }
        if (! isset(self::KEYS[$key])) {
            throw new ConfigDataException("Unknown configuration key \"{$key}\" (not listed in App\\Support\\StoreConfig::KEYS).");
        }
        if (self::$fake !== null) {
            if (! array_key_exists($key, self::$fake)) {
                throw new ConfigDataException("Required configuration \"{$key}\" is missing (test fake).");
            }

            return $this->values[$key] = self::check($key, self::KEYS[$key], self::$fake[$key]);
        }
        $this->raw ??= self::load();
        if (! array_key_exists($key, $this->raw)) {
            throw new ConfigDataException("Required configuration \"{$key}\" is missing from the config_data table.");
        }
        try {
            $decoded = json_decode((string) $this->raw[$key], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ConfigDataException("Configuration \"{$key}\" in config_data is not valid JSON (strings must be quoted, e.g. \"Free pickup\").");
        }

        return $this->values[$key] = self::check($key, self::KEYS[$key], $decoded);
    }

    /** @return array<string, ?string> */
    private static function load(): array
    {
        try {
            return DB::table('config_data')->whereIn('key', array_keys(self::KEYS))->pluck('data', 'key')->all();
        } catch (QueryException $e) {
            throw new ConfigDataException('Storefront configuration could not be read from the config_data table: '.$e->getMessage(), $e, true);
        }
    }

    private static function check(string $key, string|array $shape, mixed $v): mixed
    {
        if (is_array($shape)) {
            if (! is_array($v) || array_is_list($v) && $v !== []) {
                throw self::invalid($key, 'a JSON object with '.implode(', ', array_keys($shape)), $v);
            }
            $out = [];
            foreach ($shape as $field => $fieldShape) {
                if (! array_key_exists($field, $v)) {
                    throw new ConfigDataException("Configuration \"{$key}\" in config_data is missing the field \"{$field}\".");
                }
                $out[$field] = self::check($key.'.'.$field, $fieldShape, $v[$field]);
            }

            return $out;
        }
        if ($shape[0] === '?') {
            if ($v === null) {
                return null;
            }
            $shape = substr($shape, 1);
        }

        return match ($shape) {
            'number' => is_int($v) || is_float($v) ? ($v >= 0 ? (float) $v : throw self::invalid($key, 'a number of 0 or more', $v)) : throw self::invalid($key, 'a number', $v),
            'percent' => (is_int($v) || is_float($v)) && $v >= 0 && $v <= 100 ? (float) $v : throw self::invalid($key, 'a number from 0 to 100', $v),
            'int' => is_int($v) && $v >= 0 ? $v : throw self::invalid($key, 'a whole number of 0 or more', $v),
            'bool' => is_bool($v) ? $v : throw self::invalid($key, 'true or false', $v),
            'string' => is_string($v) && trim($v) !== '' ? $v : throw self::invalid($key, 'a non-empty string', $v),
            'string[]' => is_array($v) && array_is_list($v) && array_filter($v, fn ($s) => ! is_string($s)) === [] ? $v : throw self::invalid($key, 'a JSON list of strings', $v),
        };
    }

    private static function invalid(string $key, string $expected, mixed $got): ConfigDataException
    {
        return new ConfigDataException("Configuration \"{$key}\" in config_data is invalid: expected {$expected}, got ".json_encode($got).'.');
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Customer-facing dates. The database stores UTC (APP_TIMEZONE=UTC, MySQL session +00:00), so every stored value is
 * parsed as UTC exactly once and shown in storefront.display_timezone (Australia/Sydney: AEST/AEDT, daylight saving
 * handled by the tz database). Never convert a value that has already been converted.
 */
class DisplayTime
{
    public static function zone(): string
    {
        return config('storefront.display_timezone') ?: 'UTC';
    }

    public static function local(?string $utc): ?Carbon
    {
        return $utc ? Carbon::parse($utc, 'UTC')->setTimezone(self::zone()) : null;
    }

    /** "2 Nov 2026, 11:00 am AEDT" */
    public static function dateTime(?string $utc): ?string
    {
        return self::local($utc)?->format('j M Y, g:i a T');
    }

    /** "2026-11-02" (calendar date in the display zone) */
    public static function date(?string $utc): ?string
    {
        return self::local($utc)?->format('Y-m-d');
    }
}

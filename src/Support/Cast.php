<?php

namespace Gesflow\Sender\Support;

use Carbon\CarbonImmutable;

/**
 * Conversions of the API's JSON values: amounts are strings ("30.0000"),
 * dates are ISO 8601 strings.
 */
final class Cast
{
    public static function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    public static function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * Digits only, with the country code, as the API expects phone numbers:
     * "+242 06 612 34 56" gives "242066123456". See PhoneNumber.
     */
    public static function phone(string $phone): string
    {
        return PhoneNumber::normalize($phone);
    }
}

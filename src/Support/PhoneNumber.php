<?php

namespace Gesflow\Sender\Support;

use Closure;

/**
 * Puts phone numbers in the format the API expects: digits only, with the
 * country code ("+242 06 612 34 56" gives "242066123456").
 *
 * With a default country code, numbers written in local format get it
 * prefixed ("06 612 34 56" gives "242066123456"); numbers from another
 * country must then start with "+" or "00".
 */
final class PhoneNumber
{
    private static ?string $defaultCountryCode = null;

    /** @var (Closure(string): string)|null */
    private static ?Closure $normalizer = null;

    /**
     * Country code prefixed to numbers written without one, e.g. "242".
     * null disables it.
     */
    public static function defaultCountryCode(?string $countryCode): void
    {
        $digits = $countryCode === null ? '' : (string) preg_replace('/\D+/', '', $countryCode);

        self::$defaultCountryCode = $digits === '' ? null : $digits;
    }

    /**
     * Replaces the normalization, for numbering plans the default rule does
     * not cover (a leading 0 dropped in international format…). The result
     * is reduced to its digits.
     *
     * @param  (Closure(string): string)|null  $normalizer
     */
    public static function normalizeUsing(?Closure $normalizer): void
    {
        self::$normalizer = $normalizer;
    }

    public static function normalize(string $phone): string
    {
        if (self::$normalizer !== null) {
            return self::digits((self::$normalizer)($phone));
        }

        $phone = trim($phone);
        $digits = self::digits($phone);

        if ($digits === '' || str_starts_with($phone, '+')) {
            return $digits;
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        if (self::$defaultCountryCode === null || str_starts_with($digits, self::$defaultCountryCode)) {
            return $digits;
        }

        return self::$defaultCountryCode.$digits;
    }

    /**
     * Back to the default rule, without a default country code.
     */
    public static function flush(): void
    {
        self::$defaultCountryCode = null;
        self::$normalizer = null;
    }

    private static function digits(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }
}

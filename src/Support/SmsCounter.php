<?php

namespace Gesflow\Sender\Support;

use Gesflow\Sender\Enums\SmsEncoding;

/**
 * Compte les SMS facturés pour un texte, comme les opérateurs : 160
 * caractères GSM-7 tiennent dans un SMS (153 par partie au-delà) ; un seul
 * caractère hors de l'alphabet GSM passe tout le message en UCS-2 : 70
 * caractères (67 par partie).
 *
 *     SmsCounter::count('Votre code : 4829')->segments; // 1
 */
final class SmsCounter
{
    private const GSM7_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    /**
     * Characters of the GSM-7 extension table: they cost two units.
     */
    private const GSM7_EXTENSION = "^{}\\[~]|€\f";

    public static function count(string $text): SmsCount
    {
        $gsmLength = 0;
        $unicodeCharacters = [];

        foreach (mb_str_split($text) as $character) {
            if (str_contains(self::GSM7_BASIC, $character)) {
                $gsmLength++;
            } elseif (str_contains(self::GSM7_EXTENSION, $character)) {
                $gsmLength += 2;
            } else {
                $unicodeCharacters[$character] = true;
            }
        }

        $encoding = $unicodeCharacters === [] ? SmsEncoding::Gsm7 : SmsEncoding::Ucs2;
        // UCS-2 counts UTF-16 code units: an emoji takes two.
        $length = $encoding === SmsEncoding::Gsm7 ? $gsmLength : intdiv(strlen((string) mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);
        $single = $encoding === SmsEncoding::Gsm7 ? 160 : 70;
        $multipart = $encoding === SmsEncoding::Gsm7 ? 153 : 67;
        $segments = match (true) {
            $length === 0 => 0,
            $length <= $single => 1,
            default => (int) ceil($length / $multipart),
        };
        $perSegment = $segments <= 1 ? $single : $multipart;

        return new SmsCount(
            encoding: $encoding,
            length: $length,
            segments: $segments,
            perSegment: $perSegment,
            remaining: $segments === 0 ? $single : $segments * $perSegment - $length,
            unicodeCharacters: array_slice(array_map('strval', array_keys($unicodeCharacters)), 0, 5),
            hasVariables: preg_match('/\{\{\s*[a-zA-Z0-9_]+/', $text) === 1,
        );
    }
}

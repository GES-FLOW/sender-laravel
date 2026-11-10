<?php

namespace Gesflow\Sender\Support;

use Gesflow\Sender\Enums\SmsEncoding;

/**
 * Longueur d'un SMS et nombre de SMS facturés, calculés par SmsCounter.
 */
final class SmsCount
{
    /**
     * @param  int  $length  Longueur en unités facturées (septets GSM-7 ou unités UCS-2)
     * @param  int  $perSegment  Capacité d'un SMS pour cette longueur (seul ou partie d'un message long)
     * @param  int  $remaining  Unités restantes avant le SMS suivant
     * @param  list<string>  $unicodeCharacters  Caractères qui imposent l'UCS-2 (5 au plus)
     * @param  bool  $hasVariables  Le texte contient des {{ variables }} : sa longueur réelle dépendra du destinataire
     */
    public function __construct(
        public readonly SmsEncoding $encoding,
        public readonly int $length,
        public readonly int $segments,
        public readonly int $perSegment,
        public readonly int $remaining,
        public readonly array $unicodeCharacters,
        public readonly bool $hasVariables,
    ) {}

    /**
     * Prix de l'envoi : prix d'un SMS × nombre de SMS × destinataires.
     */
    public function cost(float $unitPrice, int $recipients = 1): float
    {
        return round($unitPrice * $this->segments * max(0, $recipients), 4);
    }

    public function isUnicode(): bool
    {
        return $this->encoding === SmsEncoding::Ucs2;
    }
}

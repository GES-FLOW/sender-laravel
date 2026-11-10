<?php

namespace Gesflow\Sender\Enums;

/**
 * Encodage d'un SMS : GSM-7 (160 caractères par SMS) ou UCS-2 (70), dès
 * qu'un caractère sort de l'alphabet GSM (« ê », « ç », emoji…).
 */
enum SmsEncoding: string
{
    case Gsm7 = 'GSM-7';
    case Ucs2 = 'UCS-2';
}

<?php

namespace Gesflow\Sender\Enums;

/**
 * Résultat de la vérification d'un code OTP.
 */
enum OtpStatus: string
{
    case Approved = 'approved';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case MaxAttemptsReached = 'max_attempts_reached';
    case NotFound = 'not_found';
}

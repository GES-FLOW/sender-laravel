<?php

namespace Gesflow\Sender\Enums;

/**
 * Statut d'une recharge Mobile Money.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
}

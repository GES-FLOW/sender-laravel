<?php

namespace Gesflow\Sender\Enums;

/**
 * Opérateur Mobile Money d'une recharge.
 */
enum MobileMoneyOperator: string
{
    case Mtn = 'mtn';
    case Airtel = 'airtel';
}

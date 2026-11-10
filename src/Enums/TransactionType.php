<?php

namespace Gesflow\Sender\Enums;

/**
 * Nature d'un mouvement du solde.
 */
enum TransactionType: string
{
    case OpeningBalance = 'opening_balance';
    case Recharge = 'recharge';
    case Payment = 'payment';
    case MessageCharge = 'message_charge';
    case CampaignCharge = 'campaign_charge';
    case MessageAdjustment = 'message_adjustment';
    case MessageRefund = 'message_refund';
    case CancellationRefund = 'cancellation_refund';
    case Adjustment = 'adjustment';
}

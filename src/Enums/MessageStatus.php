<?php

namespace Gesflow\Sender\Enums;

/**
 * Statut d'un message : programmé, en cours d'envoi, envoyé ou en échec.
 */
enum MessageStatus: string
{
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
}

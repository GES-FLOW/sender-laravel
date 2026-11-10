<?php

namespace Gesflow\Sender\Events;

use Gesflow\Sender\Data\Message;

/**
 * Webhook « message.status » : le statut d'un message a changé (envoyé,
 * échoué…). Envoyé à la callback_url du message.
 */
final class MessageStatusUpdated
{
    public function __construct(
        public readonly Message $message,
        public readonly ?string $deliveryId = null,
    ) {}
}

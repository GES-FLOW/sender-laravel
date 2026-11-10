<?php

namespace Gesflow\Sender\Events;

use Gesflow\Sender\Data\InboundMessage;

/**
 * Webhook « message.received » : un destinataire a répondu (SMS ou
 * WhatsApp). Envoyé à l'URL des messages entrants de votre compte.
 */
final class MessageReceived
{
    public function __construct(
        public readonly InboundMessage $message,
        public readonly ?string $deliveryId = null,
    ) {}
}

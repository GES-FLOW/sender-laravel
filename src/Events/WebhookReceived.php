<?php

namespace Gesflow\Sender\Events;

/**
 * Tout webhook dont la signature est valide, avant les événements typés
 * (MessageStatusUpdated, MessageReceived) : utile pour journaliser ou pour
 * traiter un type d'événement que le SDK ne connaît pas encore.
 */
final class WebhookReceived
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $event,
        public readonly array $payload,
        public readonly ?string $deliveryId = null,
    ) {}
}

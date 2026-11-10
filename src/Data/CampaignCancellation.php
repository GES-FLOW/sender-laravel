<?php

namespace Gesflow\Sender\Data;

/**
 * Résultat de l'annulation d'une campagne : ses messages encore programmés
 * sont annulés et leur prix est recrédité.
 */
final class CampaignCancellation
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly int $cancelledMessages,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        return new self(
            campaign: Campaign::fromArray((array) $response['data']),
            cancelledMessages: (int) data_get($response, 'meta.cancelled', 0),
        );
    }
}

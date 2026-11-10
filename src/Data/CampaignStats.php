<?php

namespace Gesflow\Sender\Data;

use Gesflow\Sender\Support\Cast;

/**
 * Avancement d'une campagne. Les taux sont des pourcentages (0 à 100) et le
 * coût est en FCFA.
 */
final class CampaignStats
{
    public function __construct(
        public readonly int $total,
        public readonly int $sent,
        public readonly int $failed,
        public readonly int $pending,
        public readonly int $scheduled,
        public readonly float $deliveryRate,
        public readonly float $failureRate,
        public readonly float $cost,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: (int) ($data['total'] ?? 0),
            sent: (int) ($data['sent'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            pending: (int) ($data['pending'] ?? 0),
            scheduled: (int) ($data['scheduled'] ?? 0),
            deliveryRate: Cast::float($data['delivery_rate'] ?? 0),
            failureRate: Cast::float($data['failure_rate'] ?? 0),
            cost: Cast::float($data['cost'] ?? 0),
        );
    }
}

<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un mouvement du solde : recharge, débit d'un message, remboursement…
 *
 * @implements Arrayable<string, mixed>
 */
final class CreditTransaction implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>|null  $reference
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $label,
        public readonly float $amount,
        public readonly float $balanceAfter,
        public readonly ?string $description,
        public readonly ?array $reference,
        public readonly ?CarbonImmutable $createdAt,
        public readonly array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            type: (string) $data['type'],
            label: (string) ($data['label'] ?? ''),
            amount: Cast::float($data['amount'] ?? 0),
            balanceAfter: Cast::float($data['balance_after'] ?? 0),
            description: Cast::nullableString($data['description'] ?? null),
            reference: is_array($data['reference'] ?? null) ? $data['reference'] : null,
            createdAt: Cast::date($data['created_at'] ?? null),
            attributes: $data,
        );
    }
}

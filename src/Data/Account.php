<?php

namespace Gesflow\Sender\Data;

use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Le compte : solde, prix appliqués et nombre de messages que le solde
 * couvre encore.
 *
 * @implements Arrayable<string, mixed>
 */
final class Account implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $balance,
        public readonly string $currency,
        public readonly ?float $smsPrice,
        public readonly ?float $whatsappPrice,
        public readonly ?int $remainingSms,
        public readonly ?int $remainingWhatsapp,
        public readonly array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) $data['name'],
            balance: Cast::float($data['credit_balance'] ?? 0),
            currency: (string) ($data['currency'] ?? 'XAF'),
            smsPrice: Cast::nullableFloat(data_get($data, 'prices.sms')),
            whatsappPrice: Cast::nullableFloat(data_get($data, 'prices.whatsapp')),
            remainingSms: Cast::nullableInt(data_get($data, 'remaining.sms')),
            remainingWhatsapp: Cast::nullableInt(data_get($data, 'remaining.whatsapp')),
            attributes: $data,
        );
    }
}

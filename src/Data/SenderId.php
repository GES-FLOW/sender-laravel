<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un Sender ID actif du compte.
 *
 * @implements Arrayable<string, mixed>
 */
final class SenderId implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $value,
        public readonly bool $isDefault,
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
            value: (string) $data['value'],
            isDefault: (bool) ($data['is_default'] ?? false),
            createdAt: Cast::date($data['created_at'] ?? null),
            attributes: $data,
        );
    }
}

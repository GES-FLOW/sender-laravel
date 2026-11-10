<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un groupe de contacts.
 *
 * @implements Arrayable<string, mixed>
 */
final class ContactGroup implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?int $contactsCount,
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
            name: (string) $data['name'],
            description: Cast::nullableString($data['description'] ?? null),
            contactsCount: Cast::nullableInt($data['contacts_count'] ?? null),
            createdAt: Cast::date($data['created_at'] ?? null),
            attributes: $data,
        );
    }
}

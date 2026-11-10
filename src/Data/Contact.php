<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un contact du carnet d'adresses du compte.
 *
 * @implements Arrayable<string, mixed>
 */
final class Contact implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  list<string>  $tags
     * @param  array<string, string>  $customFields
     * @param  list<ContactGroup>  $groups
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $phone,
        public readonly array $tags,
        public readonly array $customFields,
        public readonly array $groups,
        public readonly ?CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $updatedAt,
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
            phone: (string) $data['phone'],
            tags: array_values(array_map('strval', (array) ($data['tags'] ?? []))),
            customFields: (array) ($data['custom_fields'] ?? []),
            groups: array_values(array_map(ContactGroup::fromArray(...), (array) ($data['groups'] ?? []))),
            createdAt: Cast::date($data['created_at'] ?? null),
            updatedAt: Cast::date($data['updated_at'] ?? null),
            attributes: $data,
        );
    }
}

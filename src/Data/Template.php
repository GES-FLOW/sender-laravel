<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un modèle de message, avec ses variables de personnalisation.
 *
 * @implements Arrayable<string, mixed>
 */
final class Template implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $content,
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
            content: (string) $data['content'],
            createdAt: Cast::date($data['created_at'] ?? null),
            updatedAt: Cast::date($data['updated_at'] ?? null),
            attributes: $data,
        );
    }
}

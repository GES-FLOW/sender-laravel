<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Une campagne, créée par un envoi en masse avec une clé de production.
 *
 * @implements Arrayable<string, mixed>
 */
final class Campaign implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly Channel $channel,
        public readonly string $text,
        public readonly ?string $from,
        public readonly int $recipientsCount,
        public readonly ?CarbonImmutable $scheduledAt,
        public readonly ?CarbonImmutable $createdAt,
        public readonly CampaignStats $stats,
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
            channel: Channel::from((string) $data['channel']),
            text: (string) ($data['text'] ?? ''),
            from: Cast::nullableString($data['from'] ?? null),
            recipientsCount: (int) ($data['recipients_count'] ?? 0),
            scheduledAt: Cast::date($data['scheduled_at'] ?? null),
            createdAt: Cast::date($data['created_at'] ?? null),
            stats: CampaignStats::fromArray((array) ($data['stats'] ?? [])),
            attributes: $data,
        );
    }
}

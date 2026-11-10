<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un message reçu : réponse à un SMS ou message WhatsApp entrant.
 *
 * @implements Arrayable<string, mixed>
 */
final class InboundMessage implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly Channel $channel,
        public readonly string $phone,
        public readonly ?string $to,
        public readonly string $text,
        public readonly ?string $mediaUrl,
        public readonly ?string $mediaMimetype,
        public readonly ?CarbonImmutable $receivedAt,
        public readonly ?CarbonImmutable $readAt,
        public readonly array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            channel: Channel::from((string) $data['channel']),
            phone: (string) $data['phone'],
            to: Cast::nullableString($data['to'] ?? null),
            text: (string) ($data['text'] ?? ''),
            mediaUrl: Cast::nullableString($data['media_url'] ?? null),
            mediaMimetype: Cast::nullableString($data['media_mimetype'] ?? null),
            receivedAt: Cast::date($data['received_at'] ?? null),
            readAt: Cast::date($data['read_at'] ?? null),
            attributes: $data,
        );
    }
}

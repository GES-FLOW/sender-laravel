<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\MessageStatus;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un message SMS ou WhatsApp.
 *
 * @implements Arrayable<string, mixed>
 */
final class Message implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly Channel $channel,
        public readonly string $phone,
        public readonly string $text,
        public readonly string $from,
        public readonly MessageStatus $status,
        public readonly float $price,
        public readonly bool $sandbox,
        public readonly ?int $campaignId,
        public readonly ?string $failureReason,
        public readonly ?string $mediaUrl,
        public readonly ?string $mediaType,
        public readonly ?CarbonImmutable $scheduledAt,
        public readonly ?CarbonImmutable $sentAt,
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
            channel: Channel::from((string) $data['channel']),
            phone: (string) $data['phone'],
            text: (string) ($data['text'] ?? ''),
            from: (string) ($data['from'] ?? ''),
            status: MessageStatus::from((string) $data['status']),
            price: Cast::float($data['price'] ?? 0),
            sandbox: (bool) ($data['sandbox'] ?? false),
            campaignId: Cast::nullableInt($data['campaign_id'] ?? null),
            failureReason: Cast::nullableString($data['failure_reason'] ?? null),
            mediaUrl: Cast::nullableString($data['media_url'] ?? null),
            mediaType: Cast::nullableString($data['media_type'] ?? null),
            scheduledAt: Cast::date($data['scheduled_at'] ?? null),
            sentAt: Cast::date($data['sent_at'] ?? null),
            createdAt: Cast::date($data['created_at'] ?? null),
            attributes: $data,
        );
    }

    public function isSent(): bool
    {
        return $this->status === MessageStatus::Sent;
    }

    public function isFailed(): bool
    {
        return $this->status === MessageStatus::Failed;
    }

    /**
     * Programmé ou en cours d'envoi.
     */
    public function isPending(): bool
    {
        return $this->status === MessageStatus::Scheduled || $this->status === MessageStatus::Processing;
    }
}

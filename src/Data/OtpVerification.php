<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Un code OTP envoyé, en attente de vérification.
 *
 * @implements Arrayable<string, mixed>
 */
final class OtpVerification implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $phone,
        public readonly Channel $channel,
        public readonly ?int $messageId,
        public readonly ?CarbonImmutable $expiresAt,
        public readonly bool $sandbox,
        public readonly ?string $code,
        public readonly array $attributes,
        public readonly string $status = 'pending',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            phone: (string) $data['phone'],
            channel: Channel::from((string) $data['channel']),
            messageId: Cast::nullableInt($data['message_id'] ?? null),
            expiresAt: Cast::date($data['expires_at'] ?? null),
            sandbox: (bool) ($data['sandbox'] ?? false),
            code: Cast::nullableString($data['code'] ?? null),
            attributes: $data,
            status: (string) ($data['status'] ?? 'pending'),
        );
    }
}

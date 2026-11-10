<?php

namespace Gesflow\Sender\Data;

use Gesflow\Sender\Enums\OtpStatus;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Résultat de la vérification d'un code OTP.
 *
 * @implements Arrayable<string, mixed>
 */
final class OtpCheck implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly ?string $id,
        public readonly string $phone,
        public readonly bool $valid,
        public readonly OtpStatus $status,
        public readonly int $attemptsRemaining,
        public readonly array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            phone: (string) $data['phone'],
            valid: (bool) $data['valid'],
            status: OtpStatus::from((string) $data['status']),
            attemptsRemaining: (int) ($data['attempts_remaining'] ?? 0),
            attributes: $data,
        );
    }
}

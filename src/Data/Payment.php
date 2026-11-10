<?php

namespace Gesflow\Sender\Data;

use Carbon\CarbonImmutable;
use Gesflow\Sender\Enums\PaymentStatus;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Une recharge Mobile Money. Le montant de base est crédité sur le solde
 * une fois le paiement réussi.
 *
 * @implements Arrayable<string, mixed>
 */
final class Payment implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly float $baseAmount,
        public readonly float $serviceFee,
        public readonly float $transactionFee,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $phoneNumber,
        public readonly string $operator,
        public readonly ?string $failureMessage,
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
            reference: (string) $data['reference'],
            status: (string) $data['status'],
            statusLabel: (string) ($data['status_label'] ?? ''),
            baseAmount: Cast::float($data['base_amount'] ?? 0),
            serviceFee: Cast::float($data['service_fee'] ?? 0),
            transactionFee: Cast::float($data['transaction_fee'] ?? 0),
            amount: Cast::float($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'XAF'),
            phoneNumber: (string) ($data['phone_number'] ?? ''),
            operator: (string) ($data['operator'] ?? ''),
            failureMessage: Cast::nullableString($data['failure_message'] ?? null),
            createdAt: Cast::date($data['created_at'] ?? null),
            updatedAt: Cast::date($data['updated_at'] ?? null),
            attributes: $data,
        );
    }

    /**
     * Le statut sous forme d'enum (null pour un statut que le SDK ne connaît pas).
     */
    public function paymentStatus(): ?PaymentStatus
    {
        return PaymentStatus::tryFrom($this->status);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Success->value;
    }

    /**
     * En attente de la confirmation du payeur ou du prestataire.
     */
    public function isPending(): bool
    {
        return in_array($this->paymentStatus(), [PaymentStatus::Pending, PaymentStatus::Processing], true);
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed->value;
    }
}

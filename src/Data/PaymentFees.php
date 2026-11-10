<?php

namespace Gesflow\Sender\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Montant minimal et frais d'une recharge Mobile Money.
 *
 * @implements Arrayable<string, mixed>
 */
final class PaymentFees implements Arrayable, JsonSerializable
{
    use InteractsWithAttributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly float $minAmount,
        public readonly float $providerFeePercentage,
        public readonly float $providerFeeFixed,
        public readonly float $vatPercentage,
        public readonly float $additionalCentsPercentage,
        public readonly string $currency,
        public readonly array $attributes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minAmount: (float) ($data['min_amount'] ?? 0),
            providerFeePercentage: (float) ($data['provider_fee_percentage'] ?? 0),
            providerFeeFixed: (float) ($data['provider_fee_fixed'] ?? 0),
            vatPercentage: (float) ($data['tax_vat_percentage'] ?? 0),
            additionalCentsPercentage: (float) ($data['tax_ca_percentage'] ?? 0),
            currency: (string) ($data['currency'] ?? 'XAF'),
            attributes: $data,
        );
    }

    /**
     * TVA et centimes additionnels d'une recharge.
     */
    public function taxesFor(float $amount): float
    {
        return $amount * $this->vatPercentage * (1 + $this->additionalCentsPercentage);
    }

    /**
     * Frais du prestataire de paiement d'une recharge.
     */
    public function providerFeeFor(float $amount): float
    {
        return ($amount + $this->taxesFor($amount)) * $this->providerFeePercentage + $this->providerFeeFixed;
    }

    /**
     * Montant total payé pour créditer $amount FCFA sur le solde.
     */
    public function totalFor(float $amount): float
    {
        return $amount + $this->taxesFor($amount) + $this->providerFeeFor($amount);
    }
}

<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Data\Payment;
use Gesflow\Sender\Data\PaymentFees;
use Gesflow\Sender\Enums\MobileMoneyOperator;
use Illuminate\Support\LazyCollection;

/**
 * Recharges du solde par Mobile Money (MTN, Airtel).
 */
class Payments extends Resource
{
    /**
     * Montant minimal et frais d'une recharge.
     */
    public function fees(): PaymentFees
    {
        return PaymentFees::fromArray($this->connector->get('payments/fees-config'));
    }

    /**
     * Demande le paiement sur le téléphone du payeur, qui le confirme avec
     * son code secret Mobile Money.
     *
     * @param  float  $amount  Montant à créditer sur le solde, frais et taxes en sus
     */
    public function initiate(float $amount, string $phoneNumber, MobileMoneyOperator|string $operator, ?string $idempotencyKey = null): Payment
    {
        return Payment::fromArray((array) $this->connector->post('payments/initiate', [
            'amount' => $amount,
            'phone_number' => $phoneNumber,
            'operator' => $operator instanceof MobileMoneyOperator ? $operator->value : strtolower($operator),
        ], $idempotencyKey)['data']);
    }

    public function find(int $id): Payment
    {
        return Payment::fromArray((array) $this->connector->get("payments/{$id}")['data']);
    }

    /**
     * @return Page<Payment>
     */
    public function list(int $page = 1, ?int $perPage = null): Page
    {
        return Page::fromResponse($this->connector->get('payments/me', ['page' => $page, 'per_page' => $perPage]), Payment::fromArray(...));
    }

    /**
     * Toutes les recharges, les pages étant chargées au fil de l'itération.
     *
     * @return LazyCollection<int, Payment>
     */
    public function cursor(?int $perPage = null): LazyCollection
    {
        return $this->cursorOf(fn (int $page): Page => $this->list($page, $perPage));
    }
}

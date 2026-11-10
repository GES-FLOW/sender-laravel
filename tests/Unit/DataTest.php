<?php

use Gesflow\Sender\Data\CreditTransaction;
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\OtpVerification;
use Gesflow\Sender\Data\Payment;
use Gesflow\Sender\Enums\MobileMoneyOperator;
use Gesflow\Sender\Enums\PaymentStatus;
use Gesflow\Sender\Enums\TransactionType;
use Gesflow\Sender\Facades\GesflowSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('maps the whole message', function () {
    $message = Message::fromArray(messageData([
        'interactive_type' => 'location',
        'deferred_until' => '2026-11-10T10:00:00.000000Z',
        'replayable' => true,
        'replayed_at' => '2026-11-10T11:00:00.000000Z',
    ]));

    expect($message->currency)->toBe('XAF')
        ->and($message->interactiveType)->toBe('location')
        ->and($message->deferredUntil?->toIso8601ZuluString())->toBe('2026-11-10T10:00:00Z')
        ->and($message->replayable)->toBeTrue()
        ->and($message->replayedAt?->toIso8601ZuluString())->toBe('2026-11-10T11:00:00Z');
});

it('exposes the payment and transaction enums', function (string $status, ?PaymentStatus $enum, bool $pending, bool $failed) {
    $payment = Payment::fromArray(['id' => 1, 'reference' => 'PAY-1', 'status' => $status]);

    expect($payment->paymentStatus())->toBe($enum)
        ->and($payment->isPending())->toBe($pending)
        ->and($payment->isFailed())->toBe($failed)
        ->and($payment->isSuccessful())->toBe($status === 'success');
})->with([
    ['pending', PaymentStatus::Pending, true, false],
    ['processing', PaymentStatus::Processing, true, false],
    ['success', PaymentStatus::Success, false, false],
    ['failed', PaymentStatus::Failed, false, true],
    ['nouveau', null, false, false],
]);

it('types the balance movements and the OTP status', function () {
    expect(CreditTransaction::fromArray(['id' => 1, 'type' => 'cancellation_refund'])->transactionType())->toBe(TransactionType::CancellationRefund)
        ->and(CreditTransaction::fromArray(['id' => 1, 'type' => 'inconnu'])->transactionType())->toBeNull()
        ->and(OtpVerification::fromArray(['id' => 'x', 'phone' => '242066123456', 'channel' => 'sms', 'status' => 'pending'])->status)->toBe('pending');
});

it('accepts the operator enum', function () {
    Http::fake([apiUrl('payments/initiate') => Http::response(['data' => ['id' => 1, 'reference' => 'PAY-1', 'status' => 'pending']], 201)]);

    GesflowSender::payments()->initiate(1000, '242066123456', MobileMoneyOperator::Airtel);

    Http::assertSent(fn (Request $request): bool => $request['operator'] === 'airtel');
});

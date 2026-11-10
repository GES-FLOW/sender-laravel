<?php

use Gesflow\Sender\Data\CreditTransaction;
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Enums\TransactionType;
use Gesflow\Sender\Facades\GesflowSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

it('iterates over every page lazily', function () {
    Http::fake([
        apiUrl('messages?page=1') => Http::response(pageOf([messageData(['id' => 3]), messageData(['id' => 2])], currentPage: 1, lastPage: 2, total: 3)),
        apiUrl('messages?page=2') => Http::response(pageOf([messageData(['id' => 1])], currentPage: 2, lastPage: 2, total: 3)),
    ]);

    $messages = GesflowSender::messages()->cursor();

    expect($messages)->toBeInstanceOf(LazyCollection::class);

    Http::assertNothingSent();

    expect($messages->first()->id)->toBe(3);

    Http::assertSentCount(1);

    expect($messages->map(fn (Message $message): int => $message->id)->all())->toBe([3, 2, 1]);
});

it('keeps the filters on every page', function () {
    Http::fake([
        apiUrl('contacts?tag=vip&page=1') => Http::response(pageOf([['id' => 1, 'name' => 'Awa', 'phone' => '242066000001']], currentPage: 1, lastPage: 2, total: 2)),
        apiUrl('contacts?tag=vip&page=2') => Http::response(pageOf([['id' => 2, 'name' => 'Bob', 'phone' => '242066000002']], currentPage: 2, lastPage: 2, total: 2)),
    ]);

    expect(GesflowSender::contacts()->cursor(tag: 'vip')->pluck('name')->all())->toBe(['Awa', 'Bob']);

    Http::assertSent(fn (Request $request): bool => $request->data() === ['tag' => 'vip', 'page' => 2]);
});

it('iterates over the balance movements of a type', function () {
    $transaction = fn (int $id): array => ['id' => $id, 'type' => 'message_refund', 'amount' => '25.0000', 'balance_after' => '100.0000'];

    Http::fake([
        apiUrl('account/transactions?type=message_refund&page=1') => Http::response(pageOf([$transaction(2)], currentPage: 1, lastPage: 2, total: 2)),
        apiUrl('account/transactions?type=message_refund&page=2') => Http::response(pageOf([$transaction(1)], currentPage: 2, lastPage: 2, total: 2)),
    ]);

    $transactions = GesflowSender::transactionsCursor(TransactionType::MessageRefund)->all();

    expect($transactions)->toHaveCount(2)
        ->and($transactions[1])->toBeInstanceOf(CreditTransaction::class)
        ->and($transactions[1]->transactionType())->toBe(TransactionType::MessageRefund);
});

it('iterates over the fake resources', function () {
    GesflowSender::fake();

    foreach (range(1, 30) as $index) {
        GesflowSender::templates()->create("Modèle {$index}", 'Bonjour');
    }

    expect(GesflowSender::templates()->cursor()->count())->toBe(30)
        ->and(GesflowSender::templates()->list()->lastPage)->toBe(2);
});

<?php

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\MessageBatch;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\MessageStatus;
use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\Messages\GesflowMessage;
use Gesflow\Sender\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

it('sends an SMS with the API key, the JSON headers and an idempotency key', function () {
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData()], 201)]);

    $message = GesflowSender::sms('+242 06 612 34 56', 'Votre colis est en route.')->from('MaBoutique')->send();

    expect($message)->toBeInstanceOf(Message::class)
        ->id->toBe(42)
        ->channel->toBe(Channel::Sms)
        ->phone->toBe('242066123456')
        ->from->toBe('MaBoutique')
        ->status->toBe(MessageStatus::Processing)
        ->price->toBe(25.0)
        ->sandbox->toBeFalse()
        ->and($message->createdAt?->toIso8601ZuluString())->toBe('2026-11-10T09:30:00Z')
        ->and($message->isPending())->toBeTrue()
        ->and($message->get('currency'))->toBe('XAF');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === apiUrl('messages')
        && $request->header('Authorization') === ['Bearer '.TestCase::API_KEY]
        && $request->header('Accept') === ['application/json']
        && str_starts_with($request->header('User-Agent')[0], 'GesflowSender-Laravel/')
        && Str::isUuid($request->header('Idempotency-Key')[0])
        && $request->data() === [
            'channel' => 'sms',
            'phone' => '242066123456',
            'text' => 'Votre colis est en route.',
            'sender_id' => 'MaBoutique',
        ]);
});

it('uses the configured default sender ID for SMS', function () {
    reconfigure(['sender_id' => 'MaMarque']);
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData()], 201)]);

    GesflowSender::sms('242066123456', 'Bonjour')->send();
    GesflowSender::sms('242066123456', 'Bonjour')->from('Autre')->send();
    GesflowSender::whatsapp('242066123456', 'Bonjour')->send();

    $payloads = collect(Http::recorded())->map(fn (array $pair): array => $pair[0]->data());

    expect($payloads[0]['sender_id'])->toBe('MaMarque')
        ->and($payloads[1]['sender_id'])->toBe('Autre')
        ->and($payloads[2])->not->toHaveKey('sender_id');
});

it('sends the scheduling date in UTC, the callback URL and the flash flag', function () {
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData(['status' => 'scheduled'])], 201)]);

    GesflowSender::sms('242066123456', 'Rappel : rendez-vous demain à 10 h.')
        ->at(Carbon::parse('2026-12-01 10:00', 'Africa/Brazzaville'))
        ->callbackUrl('https://boutique.example/webhooks/gesflow')
        ->flash()
        ->send();

    Http::assertSent(fn (Request $request): bool => $request['scheduled_at'] === '2026-12-01T09:00:00Z'
        && $request['callback_url'] === 'https://boutique.example/webhooks/gesflow'
        && $request['flash'] === true);
});

it('sends WhatsApp media, locations and contact cards', function () {
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData(['channel' => 'whatsapp', 'from' => 'WhatsApp'])], 201)]);

    GesflowSender::whatsapp('242066123456')->image('https://cdn.example/facture.png', 'Votre facture')->send();
    GesflowSender::whatsapp('242066123456')->location(-4.2634, 15.2429, 'Notre agence')->send();
    GesflowSender::whatsapp('242066123456')->contact('Service client', '+242 06 600 00 00', 'GESFLOW')->send();

    $payloads = collect(Http::recorded())->map(fn (array $pair): array => $pair[0]->data())->all();

    expect($payloads[0])->toBe([
        'channel' => 'whatsapp',
        'phone' => '242066123456',
        'text' => 'Votre facture',
        'media_url' => 'https://cdn.example/facture.png',
        'media_type' => 'image',
    ])->and($payloads[1])->toBe([
        'channel' => 'whatsapp',
        'phone' => '242066123456',
        'latitude' => -4.2634,
        'longitude' => 15.2429,
        'title' => 'Notre agence',
    ])->and($payloads[2]['contacts'])->toBe([
        ['full_name' => 'Service client', 'phone_number' => '+242 06 600 00 00', 'organization' => 'GESFLOW'],
    ]);
});

it('sends a prepared message and keeps a given idempotency key', function () {
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData()], 201)]);

    GesflowSender::send(GesflowMessage::sms('Commande expédiée')->to('242066123456')->idempotencyKey('commande-1042'));

    Http::assertSent(fn (Request $request): bool => $request->header('Idempotency-Key') === ['commande-1042']);
});

it('sends a bulk message to numbers, groups and tags', function () {
    Http::fake([apiUrl('messages/bulk') => Http::response([
        'data' => [
            messageData(['id' => 1, 'phone' => '242066000001', 'campaign_id' => 9]),
            messageData(['id' => 2, 'phone' => '242066000002', 'campaign_id' => 9]),
        ],
        'meta' => ['count' => 2, 'skipped_rows' => 0, 'campaign_id' => 9],
    ], 201)]);

    $batch = GesflowSender::bulkSms('Bonjour {{ prenom|cher client }} !')
        ->to(['+242 06 600 00 01', '242066000002'])
        ->toGroups([4])
        ->toTags(['vip'])
        ->name('Promo de Noël')
        ->send();

    expect($batch)->toBeInstanceOf(MessageBatch::class)
        ->toHaveCount(2)
        ->campaignId->toBe(9)
        ->skippedRows->toBe(0)
        ->and($batch->messages[1]->phone)->toBe('242066000002');

    Http::assertSent(fn (Request $request): bool => $request->url() === apiUrl('messages/bulk') && $request->data() === [
        'channel' => 'sms',
        'phones' => ['242066000001', '242066000002'],
        'group_ids' => [4],
        'tags' => ['vip'],
        'text' => 'Bonjour {{ prenom|cher client }} !',
        'campaign_name' => 'Promo de Noël',
    ]);
});

it('finds, lists and cancels messages', function () {
    Http::fake([
        apiUrl('messages/42') => Http::response(['data' => messageData(['status' => 'sent', 'sent_at' => '2026-11-10T09:30:05.000000Z'])]),
        apiUrl('messages?page=2') => Http::response(pageOf([messageData(), messageData(['id' => 41])], currentPage: 2, lastPage: 3, total: 60)),
        apiUrl('messages/43/cancel') => Http::response(['data' => messageData([
            'id' => 43,
            'status' => 'failed',
            'failure_reason' => "Envoi programmé annulé par l'utilisateur.",
        ])]),
    ]);

    $message = GesflowSender::messages()->find(42);
    $page = GesflowSender::messages()->list(page: 2);
    $cancelled = GesflowSender::messages()->cancel(43);

    expect($message->isSent())->toBeTrue()
        ->and($message->sentAt?->toIso8601ZuluString())->toBe('2026-11-10T09:30:05Z')
        ->and($page)->toHaveCount(2)
        ->and($page->items[1]->id)->toBe(41)
        ->and($page->total)->toBe(60)
        ->and($page->hasMorePages())->toBeTrue()
        ->and($page->nextPage())->toBe(3)
        ->and($cancelled->isFailed())->toBeTrue()
        ->and($cancelled->failureReason)->toBe("Envoi programmé annulé par l'utilisateur.");
});

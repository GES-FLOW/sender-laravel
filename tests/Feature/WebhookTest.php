<?php

use Gesflow\Sender\Enums\MessageStatus;
use Gesflow\Sender\Events\MessageReceived;
use Gesflow\Sender\Events\MessageStatusUpdated;
use Gesflow\Sender\Events\WebhookReceived;
use Gesflow\Sender\GesflowSenderServiceProvider;
use Gesflow\Sender\Tests\TestCase;
use Gesflow\Sender\Webhooks\WebhookSignature;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * POST a webhook signed like the platform does.
 *
 * @param  array<string, mixed>  $payload
 * @param  array<string, string>  $headers
 */
function postWebhook(array $payload, string $event = 'message.status', array $headers = [], string $secret = TestCase::WEBHOOK_SECRET): TestResponse
{
    $signed = WebhookSignature::headers((string) json_encode($payload), $event, $secret, 'a1b2c3d4-0000-4000-8000-000000000001');

    return test()->postJson('/gesflow-sender/webhook', $payload, array_merge($signed, $headers));
}

it('dispatches the status updates', function () {
    Event::fake([MessageStatusUpdated::class, WebhookReceived::class]);

    postWebhook(messageData(['status' => 'sent', 'sent_at' => '2026-11-10T09:30:05.000000Z']))
        ->assertOk()
        ->assertJson(['received' => true]);

    Event::assertDispatched(MessageStatusUpdated::class, fn (MessageStatusUpdated $event): bool => $event->message->id === 42
        && $event->message->status === MessageStatus::Sent
        && $event->deliveryId === 'a1b2c3d4-0000-4000-8000-000000000001');
    Event::assertDispatched(WebhookReceived::class, fn (WebhookReceived $event): bool => $event->event === 'message.status'
        && $event->payload['id'] === 42);
});

it('dispatches the received messages', function () {
    Event::fake([MessageReceived::class]);

    postWebhook([
        'event' => 'message.received',
        'data' => [
            'id' => 11,
            'channel' => 'whatsapp',
            'phone' => '242066123456',
            'to' => '242055000000',
            'text' => 'Bonjour, ma commande est-elle partie ?',
            'media_url' => null,
            'media_mimetype' => null,
            'received_at' => '2026-11-10T08:15:00.000000Z',
            'read_at' => null,
        ],
    ], 'message.received')->assertOk();

    Event::assertDispatched(MessageReceived::class, fn (MessageReceived $event): bool => $event->message->id === 11
        && $event->message->text === 'Bonjour, ma commande est-elle partie ?');
});

it('rejects webhooks that are not signed with the secret', function () {
    Event::fake([WebhookReceived::class, MessageStatusUpdated::class]);

    postWebhook(messageData(), secret: 'mauvais-secret')->assertForbidden();
    test()->postJson('/gesflow-sender/webhook', messageData())->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
    Event::assertNotDispatched(MessageStatusUpdated::class);
});

it('rejects old webhooks', function () {
    Event::fake([WebhookReceived::class]);
    $payload = messageData();
    $headers = WebhookSignature::headers((string) json_encode($payload), 'message.status', TestCase::WEBHOOK_SECRET, timestamp: Carbon::now()->subMinutes(6)->getTimestamp());

    test()->postJson('/gesflow-sender/webhook', $payload, $headers)->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
});

it('rejects every webhook when no secret is configured', function () {
    config()->set('gesflow-sender.webhooks.secret', null);
    Event::fake([WebhookReceived::class]);

    postWebhook(messageData())->assertForbidden();

    Event::assertNotDispatched(WebhookReceived::class);
});

it('ignores a delivery it already processed', function () {
    Event::fake([MessageStatusUpdated::class]);

    postWebhook(messageData())->assertOk();
    postWebhook(messageData())->assertOk()->assertJson(['duplicate' => true]);

    Event::assertDispatchedTimes(MessageStatusUpdated::class, 1);
});

it('processes a delivery again when a listener failed', function () {
    $calls = 0;

    Event::listen(MessageStatusUpdated::class, function () use (&$calls): void {
        if (++$calls === 1) {
            throw new RuntimeException('Base de données indisponible.');
        }
    });

    postWebhook(messageData())->assertServerError();
    postWebhook(messageData())->assertOk()->assertJsonMissing(['duplicate' => true]);

    expect($calls)->toBe(2);
});

it('registers the route at the configured path, or not at all', function (?string $path, int $routes) {
    $router = new Router(app('events'), app());
    app()->instance('router', $router);
    Route::clearResolvedInstance('router');
    config()->set('gesflow-sender.webhooks.path', $path);

    (new GesflowSenderServiceProvider(app()))->boot();

    $router->getRoutes()->refreshNameLookups();

    expect($router->getRoutes()->count())->toBe($routes);

    if ($routes > 0) {
        expect($router->getRoutes()->getByName('gesflow-sender.webhook')?->uri())->toBe('hooks/sms');
    }
})->with([
    'custom path' => ['/hooks/sms/', 1],
    'disabled' => [null, 0],
]);

it('computes the signature like the platform', function () {
    $body = '{"id":42,"status":"sent"}';

    expect(WebhookSignature::compute($body, '1762767000', 'secret'))
        ->toBe('sha256='.hash_hmac('sha256', '1762767000.'.$body, 'secret'));
});

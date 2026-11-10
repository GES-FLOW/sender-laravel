<?php

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\Notifications\GesflowSenderChannel;
use Gesflow\Sender\Tests\Fixtures\Customer;
use Gesflow\Sender\Tests\Fixtures\OrderShipped;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

it('sends a notification through the API', function () {
    Http::fake([apiUrl('messages') => Http::response(['data' => messageData()], 201)]);

    (new Customer)->notify(new OrderShipped);

    Http::assertSent(fn (Request $request): bool => $request->url() === apiUrl('messages') && $request->data() === [
        'channel' => 'sms',
        'phone' => '242066123456',
        'text' => 'Votre commande CMD-1042 est en route.',
        'sender_id' => 'MaBoutique',
    ]);
});

it('returns the created message to the NotificationSent event', function () {
    GesflowSender::fake();
    Event::fake([NotificationSent::class]);

    (new Customer)->notify(new OrderShipped);

    Event::assertDispatched(NotificationSent::class, fn (NotificationSent $event): bool => $event->channel === 'gesflow-sender'
        && $event->response instanceof Message
        && $event->response->phone === '242066123456');
});

it('accepts the channel class name and a plain text message', function () {
    $fake = GesflowSender::fake();

    (new Customer)->notify(new OrderShipped(channels: [GesflowSenderChannel::class], plainText: true));

    $fake->assertSentTo('242066123456', fn (Message $message): bool => $message->text === 'Votre commande CMD-1042 est en route.');
});

it('sends on-demand notifications to the routed number', function () {
    $fake = GesflowSender::fake();

    Notification::route('gesflow-sender', '242066000000')->notify(new OrderShipped);

    $fake->assertSentTo('242066000000');
});

it('lets the message choose its recipient', function () {
    $fake = GesflowSender::fake();

    (new Customer)->notify(new OrderShipped(recipient: '242055000000'));

    $fake->assertSentTo('242055000000');
    $fake->assertNotSentTo('242066123456');
});

it('skips notifiables without a phone number', function () {
    $fake = GesflowSender::fake();

    (new Customer(phone: null))->notify(new OrderShipped);

    $fake->assertNothingSent();
});

it('derives the idempotency key from the notification id', function () {
    $fake = GesflowSender::fake();
    $notification = new OrderShipped;
    $notification->id = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    (new Customer)->notify($notification);

    expect($fake->requests()[0]->idempotencyKey)->toBe('notification:9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d:242066123456');
});

it('requires a toGesflowSender method', function () {
    GesflowSender::fake();

    $notification = new class extends BaseNotification
    {
        /**
         * @return list<string>
         */
        public function via(object $notifiable): array
        {
            return ['gesflow-sender'];
        }
    };

    expect(fn () => (new Customer)->notify($notification))->toThrow(InvalidArgumentException::class, 'toGesflowSender()');
});

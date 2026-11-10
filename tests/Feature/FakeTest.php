<?php

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Enums\MessageStatus;
use Gesflow\Sender\Enums\OtpStatus;
use Gesflow\Sender\Exceptions\NotFoundException;
use Gesflow\Sender\Exceptions\RequestRejectedException;
use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\GesflowSender as GesflowSenderClient;
use Gesflow\Sender\Testing\GesflowSenderFake;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function () {
    Http::fake();
});

afterEach(function () {
    Http::assertNothingSent();
});

it('records the messages without calling the API', function () {
    $fake = GesflowSender::fake();

    $message = GesflowSender::sms('+242 06 612 34 56', 'Votre colis est en route.')->send();
    GesflowSender::whatsapp('242055000000')->image('https://cdn.example/facture.png', 'Votre facture')->send();

    expect($message)
        ->status->toBe(MessageStatus::Sent)
        ->from->toBe('GESFLOW')
        ->sandbox->toBeTrue()
        ->and(app(GesflowSenderClient::class))->toBe($fake);

    $fake->assertSentCount(2);
    $fake->assertSentTo('242066123456', fn (Message $message): bool => $message->text === 'Votre colis est en route.');
    $fake->assertSent(fn (Message $message, array $payload): bool => ($payload['media_type'] ?? null) === 'image');
    $fake->assertNotSent(fn (Message $message): bool => $message->text === 'Autre chose');
    GesflowSender::assertSentTo('242055000000');
});

it('uses the configured sender ID', function () {
    reconfigure(['sender_id' => 'MaMarque']);
    $fake = GesflowSender::fake();

    expect(GesflowSender::sms('242066123456', 'Bonjour')->send()->from)->toBe('MaMarque')
        ->and($fake->senderIds()[0]->value)->toBe('MaMarque');
});

it('simulates failures and scheduled messages like a sandbox key', function () {
    GesflowSender::fake();

    $failed = GesflowSender::sms('242000000000', 'Test')->send();
    $scheduled = GesflowSender::sms('242066123456', 'Plus tard')->at(Carbon::now()->addDay())->send();
    $cancelled = GesflowSender::messages()->cancel($scheduled->id);

    expect($failed->isFailed())->toBeTrue()
        ->and($failed->failureReason)->toBe('Échec simulé (numéro de test sandbox).')
        ->and($scheduled->status)->toBe(MessageStatus::Scheduled)
        ->and($cancelled->isFailed())->toBeTrue()
        ->and(GesflowSender::messages()->find($scheduled->id)->isFailed())->toBeTrue()
        ->and(fn () => GesflowSender::messages()->cancel($failed->id))->toThrow(RequestRejectedException::class)
        ->and(fn () => GesflowSender::messages()->find(999))->toThrow(NotFoundException::class);
});

it('sends bulk messages to numbers and to the contacts of groups and tags', function () {
    $fake = GesflowSender::fake();

    $group = GesflowSender::contactGroups()->create('Clients');
    $awa = GesflowSender::contacts()->create('Awa', '242066000001', groupIds: [$group->id]);
    GesflowSender::contacts()->create('Bob', '242066000002', tags: ['vip']);
    GesflowSender::contacts()->create('Cy', '242066000003');

    $batch = GesflowSender::bulkSms('Promo !')->to(['242066000009', '242066000001'])->toGroups([$group->id])->toTags(['vip'])->send();

    expect($batch)->toHaveCount(3)
        ->and($batch->campaignId)->not->toBeNull()
        ->and(GesflowSender::campaigns()->find($batch->campaignId)->stats->sent)->toBe(3)
        ->and(GesflowSender::contacts()->find($awa->id)->groups[0]->name)->toBe('Clients')
        ->and(GesflowSender::contactGroups()->list()->items[0]->contactsCount)->toBe(1);

    $fake->assertBulkSent(fn (array $payload): bool => $payload['text'] === 'Promo !');
    $fake->assertSentTo('242066000002');
    $fake->assertNotSentTo('242066000003');
});

it('sends and checks verification codes', function () {
    $fake = GesflowSender::fake();

    $verification = GesflowSender::otp()->send('242066123456', codeLength: 4);

    expect($verification->code)->toBe('1234')
        ->and(GesflowSender::otp()->check('242066123456', '0000')->status)->toBe(OtpStatus::Invalid)
        ->and(GesflowSender::otp()->check('242066123456', '1234')->valid)->toBeTrue()
        ->and(GesflowSender::otp()->check('242066123456', '1234')->status)->toBe(OtpStatus::NotFound);

    $fake->assertOtpSent('+242 06 612 34 56');
    $fake->assertSentCount(0);
});

it('blocks a code after five wrong attempts', function () {
    GesflowSender::fake();
    GesflowSender::otp()->send('242066123456');

    foreach (range(1, 4) as $attempt) {
        expect(GesflowSender::otp()->check('242066123456', '000000')->attemptsRemaining)->toBe(5 - $attempt);
    }

    expect(GesflowSender::otp()->check('242066123456', '000000')->status)->toBe(OtpStatus::MaxAttemptsReached)
        ->and(GesflowSender::otp()->check('242066123456', '123456')->status)->toBe(OtpStatus::MaxAttemptsReached);
});

it('replays a request sent again with the same idempotency key', function () {
    $fake = GesflowSender::fake();

    $first = GesflowSender::sms('242066123456', 'Commande 1042 expédiée')->idempotencyKey('commande-1042')->send();
    $second = GesflowSender::sms('242066123456', 'Commande 1042 expédiée')->idempotencyKey('commande-1042')->send();

    expect($second->id)->toBe($first->id)
        ->and(fn () => GesflowSender::sms('242066123456', 'Autre texte')->idempotencyKey('commande-1042')->send())
        ->toThrow(RequestRejectedException::class);

    $fake->assertSentCount(1);
});

it('simulates the account, the templates and the payments', function () {
    GesflowSender::fake();

    $template = GesflowSender::templates()->create('Relance', 'Bonjour {{ prenom }}');
    $payment = GesflowSender::payments()->initiate(10000, '242066123456', 'mtn');

    expect(GesflowSender::balance())->toBe(100000.0)
        ->and(GesflowSender::hasWhatsapp('242066123456'))->toBeTrue()
        ->and(GesflowSender::templates()->update($template->id, 'Relance', 'Bonsoir')->content)->toBe('Bonsoir')
        ->and(GesflowSender::templates()->list('rel')->total)->toBe(1)
        ->and($payment->amount)->toBe(round(GesflowSender::payments()->fees()->totalFor(10000), 4))
        ->and(GesflowSender::payments()->find($payment->id)->status)->toBe('pending')
        ->and(GesflowSender::payments()->list()->items)->toHaveCount(1)
        ->and(GesflowSender::inboundMessages()->list()->isEmpty())->toBeTrue();
});

it('fails the assertions when nothing matches', function () {
    $fake = GesflowSender::fake();

    expect(fn () => $fake->assertSent())->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertOtpSent())->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertBulkSent())->toThrow(ExpectationFailedException::class);

    $fake->assertNothingSent();
    GesflowSender::sms('242066123456', 'Test')->send();

    expect(fn () => $fake->assertNothingSent())->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertSentCount(2))->toThrow(ExpectationFailedException::class)
        ->and(fn () => $fake->assertNotSentTo('242066123456'))->toThrow(ExpectationFailedException::class);
});

it('can be built directly', function () {
    $fake = new GesflowSenderFake('MaMarque');

    expect($fake->sms('242066123456', 'Bonjour')->send()->from)->toBe('MaMarque')
        ->and($fake->requests())->toHaveCount(1)
        ->and($fake->requests()[0]->get('phone'))->toBe('242066123456');
});

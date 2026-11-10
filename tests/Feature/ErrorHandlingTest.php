<?php

use Carbon\CarbonInterval;
use Gesflow\Sender\Exceptions\ApiException;
use Gesflow\Sender\Exceptions\AuthenticationException;
use Gesflow\Sender\Exceptions\ConflictException;
use Gesflow\Sender\Exceptions\ConnectionException;
use Gesflow\Sender\Exceptions\GesflowSenderException;
use Gesflow\Sender\Exceptions\MissingApiKeyException;
use Gesflow\Sender\Exceptions\NotFoundException;
use Gesflow\Sender\Exceptions\RateLimitException;
use Gesflow\Sender\Exceptions\RequestRejectedException;
use Gesflow\Sender\Exceptions\ServerException;
use Gesflow\Sender\Exceptions\ValidationException;
use Gesflow\Sender\Facades\GesflowSender;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

it('turns the API errors into typed exceptions', function (int $status, array $body, string $exception) {
    Http::fake([apiUrl('messages') => Http::response($body, $status)]);

    try {
        GesflowSender::sms('242066123456', 'Test')->send();
        $this->fail('An exception should have been thrown.');
    } catch (ApiException $caught) {
        expect($caught)->toBeInstanceOf($exception)
            ->toBeInstanceOf(GesflowSenderException::class)
            ->and($caught->status())->toBe($status)
            ->and($caught->getMessage())->toBe($body['message'])
            ->and($caught->body())->toBe($body);
    }
})->with([
    'invalid key' => [401, ['message' => 'Unauthenticated.'], AuthenticationException::class],
    'not found' => [404, ['message' => 'Ressource introuvable.'], NotFoundException::class],
    'invalid data' => [422, ['message' => 'Le numéro est invalide.', 'errors' => ['phone' => ['Le numéro est invalide.']]], ValidationException::class],
    'business rule' => [422, ['message' => 'Solde insuffisant pour cet envoi.'], RequestRejectedException::class],
    'rate limited' => [429, ['message' => 'Trop de requêtes.', 'retry_after' => 30], RateLimitException::class],
    'server error' => [500, ['message' => 'Erreur interne.'], ServerException::class],
]);

it('exposes the validation errors and the rate limit delay', function () {
    Http::fake([
        apiUrl('messages') => Http::response(['message' => 'Le numéro est invalide.', 'errors' => ['phone' => ['Le numéro est invalide.']]], 422),
        apiUrl('account') => Http::response(['message' => 'Trop de requêtes.', 'retry_after' => 30], 429, ['Retry-After' => '30']),
    ]);

    expect(fn () => GesflowSender::sms('123', 'Test')->send())
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toBe(['phone' => ['Le numéro est invalide.']]))
        ->and(fn () => GesflowSender::account())
        ->toThrow(fn (RateLimitException $exception) => expect($exception->retryAfter())->toBe(30));
});

it('retries server errors with the same idempotency key', function () {
    Http::fake([apiUrl('messages') => Http::sequence()
        ->push(['message' => 'Service indisponible.'], 503)
        ->push(['data' => messageData()], 201)]);

    $message = GesflowSender::sms('242066123456', 'Test')->send();

    $keys = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->header('Idempotency-Key')[0]);

    expect($message->id)->toBe(42)
        ->and($keys)->toHaveCount(2)
        ->and($keys->unique())->toHaveCount(1);
});

it('gives up after the configured number of retries', function () {
    Http::fake([apiUrl('messages') => Http::response(['message' => 'Erreur interne.'], 500)]);

    expect(fn () => GesflowSender::sms('242066123456', 'Test')->send())->toThrow(ServerException::class);

    Http::assertSentCount(3);
});

it('does not retry client errors', function () {
    Http::fake([apiUrl('messages') => Http::response(['message' => 'Solde insuffisant pour cet envoi.'], 422)]);

    expect(fn () => GesflowSender::sms('242066123456', 'Test')->send())->toThrow(RequestRejectedException::class);

    Http::assertSentCount(1);
});

it('waits for Retry-After when the idempotency key is still in use', function () {
    Sleep::fake();
    Http::fake([apiUrl('messages') => Http::sequence()
        ->push(['message' => 'Une requête avec cette clé d’idempotence est encore en cours de traitement.'], 409, ['Retry-After' => '1'])
        ->push(['data' => messageData()], 201)]);

    expect(GesflowSender::sms('242066123456', 'Test')->send()->id)->toBe(42);

    Sleep::assertSlept(fn (CarbonInterval $duration): bool => (int) $duration->totalMilliseconds === 1000);
});

it('raises a conflict when the idempotency key stays in use', function () {
    Sleep::fake();
    Http::fake([apiUrl('messages') => Http::response(['message' => 'Requête en cours.'], 409, ['Retry-After' => '2'])]);

    expect(fn () => GesflowSender::sms('242066123456', 'Test')->send())
        ->toThrow(fn (ConflictException $exception) => expect($exception->retryAfter())->toBe(2));
});

it('retries network errors and reports them when they persist', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectException('cURL error 28: Operation timed out', new Psr7Request('POST', apiUrl('messages')));
    });

    expect(fn () => GesflowSender::sms('242066123456', 'Test')->send())->toThrow(ConnectionException::class)
        ->and($attempts)->toBe(3);
});

it('recovers from a network error on retry', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        if (++$attempts === 1) {
            throw new ConnectException('cURL error 7: Connection refused', new Psr7Request('POST', apiUrl('messages')));
        }

        return Http::response(['data' => messageData()], 201);
    });

    expect(GesflowSender::sms('242066123456', 'Test')->send()->id)->toBe(42);
});

it('requires an API key', function () {
    reconfigure(['api_key' => null]);
    Http::fake();

    expect(fn () => GesflowSender::sms('242066123456', 'Test')->send())->toThrow(MissingApiKeyException::class);

    Http::assertNothingSent();
});

it('never sends a request without a recipient', function () {
    Http::fake();

    expect(fn () => GesflowSender::sms('', 'Test')->send())->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('sends GET requests without an idempotency key', function () {
    Http::fake([apiUrl('messages/42') => Http::response(['data' => messageData()])]);

    GesflowSender::messages()->find(42);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && ! $request->hasHeader('Idempotency-Key'));
});

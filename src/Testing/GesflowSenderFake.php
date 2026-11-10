<?php

namespace Gesflow\Sender\Testing;

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\GesflowSender;
use Gesflow\Sender\Support\Cast;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Le client installé par GesflowSender::fake() : aucune requête ne part,
 * l'API est simulée en mémoire (comme avec une clé sandbox) et les envois
 * sont enregistrés pour vos assertions.
 */
class GesflowSenderFake extends GesflowSender
{
    protected FakeConnector $api;

    public function __construct(?string $defaultSenderId = null)
    {
        $this->api = new FakeConnector($defaultSenderId);

        parent::__construct($this->api, $defaultSenderId);
    }

    /**
     * Les messages envoyés (unitaires et en masse) pour lesquels le
     * callback, appelé avec le message et le corps de la requête, retourne
     * true.
     *
     * @param  (callable(Message, array<string, mixed>): bool)|null  $callback
     * @return Collection<int, Message>
     */
    public function sent(?callable $callback = null): Collection
    {
        return collect($this->api->sent())
            ->filter(fn (array $sent): bool => $callback === null || (bool) $callback(Message::fromArray($sent['message']), $sent['payload']))
            ->map(fn (array $sent): Message => Message::fromArray($sent['message']))
            ->values();
    }

    /**
     * @param  (callable(Message, array<string, mixed>): bool)|null  $callback
     */
    public function assertSent(?callable $callback = null): void
    {
        PHPUnit::assertTrue($this->sent($callback)->isNotEmpty(), "Aucun message correspondant n'a été envoyé.");
    }

    /**
     * @param  (callable(Message, array<string, mixed>): bool)|null  $callback
     */
    public function assertNotSent(?callable $callback = null): void
    {
        PHPUnit::assertTrue($this->sent($callback)->isEmpty(), 'Un message correspondant a été envoyé de façon inattendue.');
    }

    /**
     * @param  (callable(Message, array<string, mixed>): bool)|null  $callback
     */
    public function assertSentTo(string $phone, ?callable $callback = null): void
    {
        $phone = Cast::phone($phone);

        PHPUnit::assertTrue(
            $this->sentTo($phone, $callback)->isNotEmpty(),
            "Aucun message correspondant n'a été envoyé au {$phone}.",
        );
    }

    /**
     * @param  (callable(Message, array<string, mixed>): bool)|null  $callback
     */
    public function assertNotSentTo(string $phone, ?callable $callback = null): void
    {
        $phone = Cast::phone($phone);

        PHPUnit::assertTrue(
            $this->sentTo($phone, $callback)->isEmpty(),
            "Un message correspondant a été envoyé au {$phone} de façon inattendue.",
        );
    }

    public function assertSentCount(int $count): void
    {
        $sent = $this->sent()->count();

        PHPUnit::assertSame($count, $sent, "{$sent} message(s) envoyé(s) au lieu de {$count}.");
    }

    /**
     * Aucun message, envoi en masse ni code OTP n'a été envoyé.
     */
    public function assertNothingSent(): void
    {
        $sends = array_filter($this->api->requests(), fn (RecordedRequest $request): bool => $request->method === 'POST'
            && in_array($request->path, ['messages', 'messages/bulk', 'verify/send'], true));

        PHPUnit::assertEmpty($sends, count($sends).' envoi(s) effectué(s) de façon inattendue.');
    }

    /**
     * Un envoi en masse a été fait ; le callback reçoit le corps de la
     * requête POST /messages/bulk.
     *
     * @param  (callable(array<string, mixed>): bool)|null  $callback
     */
    public function assertBulkSent(?callable $callback = null): void
    {
        $bulks = array_filter(
            $this->api->requests(),
            fn (RecordedRequest $request): bool => $request->method === 'POST' && $request->path === 'messages/bulk'
                && ($callback === null || (bool) $callback($request->data)),
        );

        PHPUnit::assertNotEmpty($bulks, "Aucun envoi en masse correspondant n'a été fait.");
    }

    /**
     * Un code OTP a été envoyé (au numéro donné, le cas échéant).
     */
    public function assertOtpSent(?string $phone = null): void
    {
        $phone = $phone === null ? null : Cast::phone($phone);

        $otps = array_filter(
            $this->api->requests(),
            fn (RecordedRequest $request): bool => $request->method === 'POST' && $request->path === 'verify/send'
                && ($phone === null || $request->get('phone') === $phone),
        );

        PHPUnit::assertNotEmpty($otps, $phone === null ? "Aucun code OTP n'a été envoyé." : "Aucun code OTP n'a été envoyé au {$phone}.");
    }

    /**
     * Toutes les requêtes reçues par l'API simulée, dans l'ordre.
     *
     * @return list<RecordedRequest>
     */
    public function requests(): array
    {
        return $this->api->requests();
    }

    /**
     * @param  (callable(Message, array<string, mixed>): bool)|null  $callback
     * @return Collection<int, Message>
     */
    protected function sentTo(string $phone, ?callable $callback): Collection
    {
        return $this->sent(fn (Message $message, array $payload): bool => $message->phone === $phone
            && ($callback === null || (bool) $callback($message, $payload)));
    }
}

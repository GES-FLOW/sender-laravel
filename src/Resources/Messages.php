<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\MessageBatch;
use Gesflow\Sender\Data\Page;
use Illuminate\Support\LazyCollection;

/**
 * Envoi, consultation et annulation des messages.
 */
class Messages extends Resource
{
    /**
     * Envoie un message (POST /messages).
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(array $payload, ?string $idempotencyKey = null): Message
    {
        return Message::fromArray((array) $this->connector->post('messages', $payload, $idempotencyKey)['data']);
    }

    /**
     * Envoie le même message à plusieurs destinataires (POST /messages/bulk),
     * éventuellement lus dans un fichier de contacts CSV ou XLSX.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{contents: string, filename: string}|null  $file
     */
    public function sendBulk(array $payload, ?string $idempotencyKey = null, ?array $file = null): MessageBatch
    {
        $response = $file === null
            ? $this->connector->post('messages/bulk', $payload, $idempotencyKey)
            : $this->connector->postMultipart('messages/bulk', $payload, ['file' => $file], $idempotencyKey);

        return MessageBatch::fromResponse($response);
    }

    public function find(int $id): Message
    {
        return Message::fromArray((array) $this->connector->get("messages/{$id}")['data']);
    }

    /**
     * Les messages du compte, du plus récent au plus ancien.
     *
     * @return Page<Message>
     */
    public function list(int $page = 1): Page
    {
        return Page::fromResponse($this->connector->get('messages', ['page' => $page]), Message::fromArray(...));
    }

    /**
     * Tous les messages, du plus récent au plus ancien, les pages étant
     * chargées au fil de l'itération.
     *
     * @return LazyCollection<int, Message>
     */
    public function cursor(): LazyCollection
    {
        return $this->cursorOf(fn (int $page): Page => $this->list($page));
    }

    /**
     * Annule un message programmé : son prix est recrédité.
     */
    public function cancel(int $id): Message
    {
        return Message::fromArray((array) $this->connector->post("messages/{$id}/cancel")['data']);
    }
}

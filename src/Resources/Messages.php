<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\MessageBatch;
use Gesflow\Sender\Data\Page;

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
     * Envoie le même message à plusieurs destinataires (POST /messages/bulk).
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendBulk(array $payload, ?string $idempotencyKey = null): MessageBatch
    {
        return MessageBatch::fromResponse($this->connector->post('messages/bulk', $payload, $idempotencyKey));
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
     * Annule un message programmé : son prix est recrédité.
     */
    public function cancel(int $id): Message
    {
        return Message::fromArray((array) $this->connector->post("messages/{$id}/cancel")['data']);
    }
}

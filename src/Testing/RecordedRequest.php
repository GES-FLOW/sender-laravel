<?php

namespace Gesflow\Sender\Testing;

/**
 * Une requête reçue par GesflowSender::fake().
 */
final class RecordedRequest
{
    /**
     * @param  array<string, mixed>  $data  Corps JSON ou paramètres de requête
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $data,
        public readonly ?string $idempotencyKey,
    ) {}

    /**
     * Un champ de la requête, en notation « point ».
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }
}

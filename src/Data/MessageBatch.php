<?php

namespace Gesflow\Sender\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Résultat d'un envoi en masse : un message par destinataire.
 *
 * @implements IteratorAggregate<int, Message>
 */
final class MessageBatch implements Countable, IteratorAggregate
{
    /**
     * @param  list<Message>  $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly int $count,
        public readonly int $skippedRows,
        public readonly ?int $campaignId,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $meta = (array) ($response['meta'] ?? []);
        $messages = array_values(array_map(Message::fromArray(...), (array) ($response['data'] ?? [])));

        return new self(
            messages: $messages,
            count: (int) ($meta['count'] ?? count($messages)),
            skippedRows: (int) ($meta['skipped_rows'] ?? 0),
            campaignId: isset($meta['campaign_id']) ? (int) $meta['campaign_id'] : null,
        );
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }
}

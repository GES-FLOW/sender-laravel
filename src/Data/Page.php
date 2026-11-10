<?php

namespace Gesflow\Sender\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Une page d'une liste paginée (25 éléments par page pour la plupart des
 * listes).
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    /**
     * @template TMapped
     *
     * @param  array<string, mixed>  $response
     * @param  callable(array<string, mixed>): TMapped  $map
     * @return self<TMapped>
     */
    public static function fromResponse(array $response, callable $map): self
    {
        $items = array_values(array_map($map, (array) ($response['data'] ?? [])));
        $meta = (array) ($response['meta'] ?? []);

        return new self(
            items: $items,
            currentPage: (int) ($meta['current_page'] ?? 1),
            lastPage: (int) ($meta['last_page'] ?? 1),
            perPage: (int) ($meta['per_page'] ?? count($items)),
            total: (int) ($meta['total'] ?? count($items)),
        );
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}

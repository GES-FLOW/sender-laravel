<?php

namespace Gesflow\Sender\Resources;

use Closure;
use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Http\Connector;
use Illuminate\Support\LazyCollection;

abstract class Resource
{
    public function __construct(protected Connector $connector) {}

    /**
     * Every item of a paginated list, the pages being fetched as the
     * iteration goes. The API lists the newest items first: an item created
     * during the iteration can shift the pages and appear twice.
     *
     * @template TItem
     *
     * @param  Closure(int): Page<TItem>  $fetch  Fetches the given page
     * @return LazyCollection<int, TItem>
     */
    protected function cursorOf(Closure $fetch): LazyCollection
    {
        return LazyCollection::make(function () use ($fetch) {
            $page = 1;

            do {
                $result = $fetch($page);

                foreach ($result->items as $item) {
                    yield $item;
                }

                $page = $result->nextPage();
            } while ($page !== null);
        });
    }
}

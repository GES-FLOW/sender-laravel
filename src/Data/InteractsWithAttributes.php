<?php

namespace Gesflow\Sender\Data;

/**
 * Raw access to what the API returned, for the fields the SDK does not map.
 */
trait InteractsWithAttributes
{
    /**
     * A raw attribute of the API response, in "dot" notation.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->attributes, $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}

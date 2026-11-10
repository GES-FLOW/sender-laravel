<?php

namespace Gesflow\Sender\Exceptions;

use Throwable;

/**
 * L'API n'a pas pu être jointe (réseau, DNS, délai dépassé), après
 * épuisement des nouvelles tentatives.
 */
class ConnectionException extends GesflowSenderException
{
    public static function from(Throwable $previous): self
    {
        return new self("Impossible de joindre l'API GESFLOW SENDER : {$previous->getMessage()}", 0, $previous);
    }
}

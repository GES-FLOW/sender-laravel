<?php

namespace Gesflow\Sender\Exceptions;

/**
 * 409 : une requête avec la même clé d'idempotence est encore en cours.
 */
class ConflictException extends ApiException
{
    /**
     * Délai d'attente conseillé, en secondes.
     */
    public function retryAfter(): ?int
    {
        $retryAfter = $this->response->header('Retry-After');

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }
}

<?php

namespace Gesflow\Sender\Exceptions;

/**
 * 429 : limite de débit dépassée, ou trop de codes OTP envoyés au même
 * numéro.
 */
class RateLimitException extends ApiException
{
    /**
     * Délai d'attente conseillé, en secondes.
     */
    public function retryAfter(): ?int
    {
        $retryAfter = $this->response->header('Retry-After') ?: $this->response->json('retry_after');

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }
}

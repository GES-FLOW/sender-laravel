<?php

namespace Gesflow\Sender\Exceptions;

/**
 * Aucune clé API n'est configurée.
 */
class MissingApiKeyException extends GesflowSenderException
{
    public static function make(): self
    {
        return new self('Aucune clé API GESFLOW SENDER : renseignez GESFLOW_SENDER_API_KEY dans votre fichier .env.');
    }
}

<?php

namespace Gesflow\Sender\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Réponse d'erreur de l'API. Les sous-classes précisent le cas : clé
 * invalide (401), ressource introuvable (404), conflit (409), validation
 * ou règle métier (422), limite de débit (429), erreur serveur (5xx).
 */
class ApiException extends GesflowSenderException
{
    final public function __construct(string $message, public readonly Response $response)
    {
        parent::__construct($message, $response->status());
    }

    /**
     * L'exception correspondant au statut de la réponse.
     */
    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $message = $response->json('message');
        $message = is_string($message) && $message !== '' ? $message : "Erreur HTTP {$status} de l'API GESFLOW SENDER.";

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 422 && is_array($response->json('errors')) => ValidationException::class,
            $status === 422 => RequestRejectedException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $response);
    }

    /**
     * Code HTTP de la réponse.
     */
    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * Corps JSON de la réponse.
     *
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return (array) $this->response->json();
    }
}

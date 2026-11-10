<?php

namespace Gesflow\Sender\Exceptions;

/**
 * 422 : un ou plusieurs champs sont invalides.
 */
class ValidationException extends ApiException
{
    /**
     * Messages d'erreur par champ, par exemple ["phone" => ["…"]].
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return (array) $this->response->json('errors', []);
    }
}

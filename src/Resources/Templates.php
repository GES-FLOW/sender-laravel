<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Data\Template;

/**
 * Modèles de messages.
 */
class Templates extends Resource
{
    /**
     * @return Page<Template>
     */
    public function list(?string $search = null, int $page = 1): Page
    {
        return Page::fromResponse($this->connector->get('templates', ['search' => $search, 'page' => $page]), Template::fromArray(...));
    }

    public function find(int $id): Template
    {
        return Template::fromArray((array) $this->connector->get("templates/{$id}")['data']);
    }

    public function create(string $name, string $content): Template
    {
        return Template::fromArray((array) $this->connector->post('templates', ['name' => $name, 'content' => $content])['data']);
    }

    public function update(int $id, string $name, string $content): Template
    {
        return Template::fromArray((array) $this->connector->put("templates/{$id}", ['name' => $name, 'content' => $content])['data']);
    }

    public function delete(int $id): void
    {
        $this->connector->delete("templates/{$id}");
    }
}

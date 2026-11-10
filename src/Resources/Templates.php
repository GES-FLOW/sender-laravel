<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Data\Template;
use Illuminate\Support\LazyCollection;

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

    /**
     * Tous les modèles, les pages étant chargées au fil de l'itération.
     *
     * @return LazyCollection<int, Template>
     */
    public function cursor(?string $search = null): LazyCollection
    {
        return $this->cursorOf(fn (int $page): Page => $this->list($search, $page));
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

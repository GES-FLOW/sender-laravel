<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Contact;
use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Support\Cast;
use Illuminate\Support\LazyCollection;

/**
 * Carnet d'adresses du compte.
 */
class Contacts extends Resource
{
    /**
     * @return Page<Contact>
     */
    public function list(?string $search = null, ?int $groupId = null, ?string $tag = null, int $page = 1): Page
    {
        return Page::fromResponse($this->connector->get('contacts', [
            'search' => $search,
            'group_id' => $groupId,
            'tag' => $tag,
            'page' => $page,
        ]), Contact::fromArray(...));
    }

    /**
     * Tous les contacts (filtrés comme list()), les pages étant chargées au
     * fil de l'itération.
     *
     * @return LazyCollection<int, Contact>
     */
    public function cursor(?string $search = null, ?int $groupId = null, ?string $tag = null): LazyCollection
    {
        return $this->cursorOf(fn (int $page): Page => $this->list($search, $groupId, $tag, $page));
    }

    public function find(int $id): Contact
    {
        return Contact::fromArray((array) $this->connector->get("contacts/{$id}")['data']);
    }

    /**
     * @param  list<string>  $tags
     * @param  array<string, string>  $customFields  Utilisables comme variables : {{ ville }}
     * @param  list<int>  $groupIds
     */
    public function create(string $name, string $phone, array $tags = [], array $customFields = [], array $groupIds = []): Contact
    {
        return Contact::fromArray((array) $this->connector->post('contacts', array_filter([
            'name' => $name,
            'phone' => Cast::phone($phone),
            'tags' => $tags,
            'custom_fields' => $customFields,
            'group_ids' => $groupIds,
        ], fn (mixed $value): bool => $value !== []))['data']);
    }

    /**
     * Les groupes ne changent que si $groupIds est fourni.
     *
     * @param  list<string>|null  $tags
     * @param  array<string, string>|null  $customFields
     * @param  list<int>|null  $groupIds
     */
    public function update(int $id, string $name, string $phone, ?array $tags = null, ?array $customFields = null, ?array $groupIds = null): Contact
    {
        return Contact::fromArray((array) $this->connector->put("contacts/{$id}", array_filter([
            'name' => $name,
            'phone' => Cast::phone($phone),
            'tags' => $tags,
            'custom_fields' => $customFields,
            'group_ids' => $groupIds,
        ], fn (mixed $value): bool => $value !== null))['data']);
    }

    public function delete(int $id): void
    {
        $this->connector->delete("contacts/{$id}");
    }
}

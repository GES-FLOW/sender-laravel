<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\ContactGroup;
use Gesflow\Sender\Data\Page;

/**
 * Groupes de contacts, utilisables comme destinataires d'un envoi en masse.
 */
class ContactGroups extends Resource
{
    /**
     * @return Page<ContactGroup>
     */
    public function list(int $page = 1): Page
    {
        return Page::fromResponse($this->connector->get('contact-groups', ['page' => $page]), ContactGroup::fromArray(...));
    }

    public function create(string $name, ?string $description = null): ContactGroup
    {
        return ContactGroup::fromArray((array) $this->connector->post('contact-groups', array_filter([
            'name' => $name,
            'description' => $description,
        ], fn (?string $value): bool => $value !== null))['data']);
    }

    public function update(int $id, string $name, ?string $description = null): ContactGroup
    {
        return ContactGroup::fromArray((array) $this->connector->put("contact-groups/{$id}", [
            'name' => $name,
            'description' => $description,
        ])['data']);
    }

    public function delete(int $id): void
    {
        $this->connector->delete("contact-groups/{$id}");
    }

    /**
     * Ajoute des contacts au groupe (1 000 au plus par appel).
     *
     * @param  array<int, int>  $contactIds
     */
    public function attach(int $groupId, array $contactIds): ContactGroup
    {
        return ContactGroup::fromArray((array) $this->connector->post("contact-groups/{$groupId}/contacts", [
            'contact_ids' => array_values(array_map('intval', $contactIds)),
        ])['data']);
    }

    /**
     * Retire des contacts du groupe, sans les supprimer.
     *
     * @param  array<int, int>  $contactIds
     */
    public function detach(int $groupId, array $contactIds): ContactGroup
    {
        return ContactGroup::fromArray((array) $this->connector->delete("contact-groups/{$groupId}/contacts", [
            'contact_ids' => array_values(array_map('intval', $contactIds)),
        ])['data']);
    }
}

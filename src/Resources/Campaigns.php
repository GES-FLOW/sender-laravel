<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\Campaign;
use Gesflow\Sender\Data\CampaignCancellation;
use Gesflow\Sender\Data\Page;

/**
 * Campagnes créées par les envois en masse.
 */
class Campaigns extends Resource
{
    /**
     * @return Page<Campaign>
     */
    public function list(int $page = 1): Page
    {
        return Page::fromResponse($this->connector->get('campaigns', ['page' => $page]), Campaign::fromArray(...));
    }

    public function find(int $id): Campaign
    {
        return Campaign::fromArray((array) $this->connector->get("campaigns/{$id}")['data']);
    }

    /**
     * Annule les messages encore programmés de la campagne.
     */
    public function cancel(int $id): CampaignCancellation
    {
        return CampaignCancellation::fromResponse($this->connector->post("campaigns/{$id}/cancel"));
    }
}

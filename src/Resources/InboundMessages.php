<?php

namespace Gesflow\Sender\Resources;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Gesflow\Sender\Data\InboundMessage;
use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Support\Cast;

/**
 * Messages reçus (réponses SMS et messages WhatsApp entrants).
 */
class InboundMessages extends Resource
{
    /**
     * @param  string|null  $phone  Filtre sur le numéro de l'expéditeur
     * @param  DateTimeInterface|null  $since  Messages reçus à partir de cette date
     * @return Page<InboundMessage>
     */
    public function list(?string $phone = null, ?DateTimeInterface $since = null, int $page = 1): Page
    {
        return Page::fromResponse($this->connector->get('inbound-messages', [
            'phone' => $phone === null ? null : Cast::phone($phone),
            'since' => $since === null ? null : CarbonImmutable::instance($since)->utc()->format('Y-m-d H:i:s'),
            'page' => $page,
        ]), InboundMessage::fromArray(...));
    }
}

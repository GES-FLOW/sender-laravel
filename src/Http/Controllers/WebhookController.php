<?php

namespace Gesflow\Sender\Http\Controllers;

use Gesflow\Sender\Data\InboundMessage;
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Events\MessageReceived;
use Gesflow\Sender\Events\MessageStatusUpdated;
use Gesflow\Sender\Events\WebhookReceived;
use Gesflow\Sender\Webhooks\WebhookSignature;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reçoit les webhooks (signature déjà vérifiée par le middleware) et les
 * transforme en événements Laravel.
 *
 * Un webhook est renvoyé avec le même X-Gesflow-Delivery tant que votre
 * application ne répond pas 2xx : une livraison déjà traitée avec succès
 * est ignorée, une livraison dont un listener a levé une exception sera
 * traitée de nouveau au prochain essai.
 */
class WebhookController
{
    public const EVENT_MESSAGE_STATUS = 'message.status';

    public const EVENT_MESSAGE_RECEIVED = 'message.received';

    public function __construct(
        protected Dispatcher $events,
        protected Cache $cache,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $event = (string) $request->headers->get(WebhookSignature::EVENT_HEADER);
        $deliveryId = $request->headers->get(WebhookSignature::DELIVERY_HEADER);
        $deliveryId = $deliveryId !== null && $deliveryId !== '' ? $deliveryId : null;
        $cacheKey = $deliveryId !== null ? "gesflow-sender:webhook:{$deliveryId}" : null;

        if ($cacheKey !== null && $this->cache->has($cacheKey)) {
            return new JsonResponse(['received' => true, 'duplicate' => true]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $this->events->dispatch(new WebhookReceived($event, $payload, $deliveryId));

        if ($event === self::EVENT_MESSAGE_STATUS && isset($payload['id'])) {
            $this->events->dispatch(new MessageStatusUpdated(Message::fromArray($payload), $deliveryId));
        }

        if ($event === self::EVENT_MESSAGE_RECEIVED && is_array($payload['data'] ?? null)) {
            $this->events->dispatch(new MessageReceived(InboundMessage::fromArray($payload['data']), $deliveryId));
        }

        if ($cacheKey !== null) {
            $this->cache->put($cacheKey, true, Carbon::now()->addDay());
        }

        return new JsonResponse(['received' => true]);
    }
}

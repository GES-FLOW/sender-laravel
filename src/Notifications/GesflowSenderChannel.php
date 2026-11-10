<?php

namespace Gesflow\Sender\Notifications;

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\GesflowSender;
use Gesflow\Sender\Messages\GesflowMessage;
use Gesflow\Sender\Support\Cast;
use Illuminate\Contracts\Container\Container;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * Canal de notification « gesflow-sender ».
 *
 * La notification définit toGesflowSender($notifiable), qui retourne un
 * GesflowMessage ou simplement le texte d'un SMS. Le numéro vient de la
 * méthode routeNotificationForGesflowSender() du notifiable, sauf si le
 * message en précise un avec to().
 */
class GesflowSenderChannel
{
    public function __construct(protected Container $container) {}

    /**
     * Envoie la notification. Retourne le message créé, ou null quand le
     * notifiable n'a pas de numéro.
     */
    public function send(object $notifiable, Notification $notification): ?Message
    {
        if (! method_exists($notification, 'toGesflowSender')) {
            throw new InvalidArgumentException('La notification '.$notification::class.' doit définir la méthode toGesflowSender().');
        }

        $message = $notification->toGesflowSender($notifiable);

        if (is_string($message)) {
            $message = GesflowMessage::sms($message);
        }

        if (! $message instanceof GesflowMessage) {
            throw new InvalidArgumentException('toGesflowSender() doit retourner un GesflowMessage ou le texte du SMS.');
        }

        $phone = $message->getPhone() ?? $this->phoneFor($notifiable, $notification);

        if ($phone === null) {
            return null;
        }

        $message->to($phone);

        // Une notification en file d'attente garde son identifiant quand la
        // tâche est relancée : s'en servir comme clé d'idempotence évite
        // d'envoyer deux fois le même message.
        $notificationId = (string) $notification->id;

        if ($message->getIdempotencyKey() === null && $notificationId !== '') {
            $message->idempotencyKey("notification:{$notificationId}:".Cast::phone($phone));
        }

        return $this->client()->send($message);
    }

    protected function phoneFor(object $notifiable, Notification $notification): ?string
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $phone = $notifiable->routeNotificationFor('gesflow-sender', $notification)
            ?? $notifiable->routeNotificationFor(self::class, $notification);

        return is_scalar($phone) && (string) $phone !== '' ? (string) $phone : null;
    }

    /**
     * Résolu à chaque envoi, pour que GesflowSender::fake() s'applique
     * aussi aux notifications.
     */
    protected function client(): GesflowSender
    {
        return $this->container->make('gesflow-sender');
    }
}

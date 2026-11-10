<?php

namespace Gesflow\Sender\Tests\Fixtures;

use Gesflow\Sender\Messages\GesflowMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
{
    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        public string $reference = 'CMD-1042',
        public array $channels = ['gesflow-sender'],
        public bool $plainText = false,
        public ?string $recipient = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toGesflowSender(object $notifiable): GesflowMessage|string
    {
        $text = "Votre commande {$this->reference} est en route.";

        if ($this->plainText) {
            return $text;
        }

        $message = GesflowMessage::sms($text)->from('MaBoutique');

        return $this->recipient === null ? $message : $message->to($this->recipient);
    }
}

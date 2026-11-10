<?php

namespace Gesflow\Sender\Messages;

use Gesflow\Sender\Data\Message;
use Gesflow\Sender\GesflowSender;
use LogicException;

/**
 * Un message en cours de préparation, envoyé avec send() :
 *
 *     GesflowSender::sms('242066000000', 'Votre code : 4829')->from('MaMarque')->send();
 */
class PendingMessage extends GesflowMessage
{
    protected ?GesflowSender $sender = null;

    public function using(GesflowSender $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function send(): Message
    {
        if ($this->sender === null) {
            throw new LogicException('Ce message n\'est rattaché à aucun client GesflowSender.');
        }

        return $this->sender->send($this);
    }
}

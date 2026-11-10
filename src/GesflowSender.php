<?php

namespace Gesflow\Sender;

use Gesflow\Sender\Data\Account;
use Gesflow\Sender\Data\CreditTransaction;
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Data\SenderId;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Http\Connector;
use Gesflow\Sender\Messages\GesflowMessage;
use Gesflow\Sender\Messages\PendingBulkMessage;
use Gesflow\Sender\Messages\PendingMessage;
use Gesflow\Sender\Resources\Campaigns;
use Gesflow\Sender\Resources\ContactGroups;
use Gesflow\Sender\Resources\Contacts;
use Gesflow\Sender\Resources\InboundMessages;
use Gesflow\Sender\Resources\Messages;
use Gesflow\Sender\Resources\Otp;
use Gesflow\Sender\Resources\Payments;
use Gesflow\Sender\Resources\Templates;
use Gesflow\Sender\Support\Cast;

/**
 * Client de l'API GESFLOW SENDER.
 *
 *     $message = GesflowSender::sms('242066000000', 'Votre colis est en route.')->send();
 */
class GesflowSender
{
    public function __construct(
        protected Connector $connector,
        protected ?string $defaultSenderId = null,
    ) {}

    /**
     * Prépare un SMS : chaînez from(), at(), callbackUrl()… puis send().
     */
    public function sms(string $phone, string $text = ''): PendingMessage
    {
        return PendingMessage::sms($text)->to($phone)->using($this);
    }

    /**
     * Prépare un message WhatsApp : chaînez image(), document(),
     * location()… puis send().
     */
    public function whatsapp(string $phone, string $text = ''): PendingMessage
    {
        return PendingMessage::whatsapp($text)->to($phone)->using($this);
    }

    /**
     * Envoie un message préparé.
     */
    public function send(GesflowMessage $message): Message
    {
        return $this->messages()->send($message->toArray($this->defaultSenderId), $message->getIdempotencyKey());
    }

    /**
     * Prépare un envoi de SMS en masse.
     */
    public function bulkSms(string $text = ''): PendingBulkMessage
    {
        return new PendingBulkMessage($this->messages(), Channel::Sms, $text, $this->defaultSenderId);
    }

    /**
     * Prépare un envoi WhatsApp en masse (500 destinataires au plus).
     */
    public function bulkWhatsapp(string $text = ''): PendingBulkMessage
    {
        return new PendingBulkMessage($this->messages(), Channel::Whatsapp, $text);
    }

    public function messages(): Messages
    {
        return new Messages($this->connector);
    }

    /**
     * Codes de vérification (OTP).
     */
    public function otp(): Otp
    {
        return new Otp($this->connector, $this->defaultSenderId);
    }

    public function campaigns(): Campaigns
    {
        return new Campaigns($this->connector);
    }

    public function contacts(): Contacts
    {
        return new Contacts($this->connector);
    }

    public function contactGroups(): ContactGroups
    {
        return new ContactGroups($this->connector);
    }

    public function templates(): Templates
    {
        return new Templates($this->connector);
    }

    public function inboundMessages(): InboundMessages
    {
        return new InboundMessages($this->connector);
    }

    public function payments(): Payments
    {
        return new Payments($this->connector);
    }

    /**
     * Le compte : solde, prix appliqués, messages restants.
     */
    public function account(): Account
    {
        return Account::fromArray((array) $this->connector->get('account')['data']);
    }

    /**
     * Solde du compte, en FCFA.
     */
    public function balance(): float
    {
        return $this->account()->balance;
    }

    /**
     * Mouvements du solde, du plus récent au plus ancien.
     *
     * @param  string|null  $type  Filtre : payment, message_charge, message_refund…
     * @return Page<CreditTransaction>
     */
    public function transactions(?string $type = null, int $page = 1): Page
    {
        return Page::fromResponse(
            $this->connector->get('account/transactions', ['type' => $type, 'page' => $page]),
            CreditTransaction::fromArray(...),
        );
    }

    /**
     * Sender IDs actifs du compte.
     *
     * @return list<SenderId>
     */
    public function senderIds(): array
    {
        return array_values(array_map(SenderId::fromArray(...), (array) ($this->connector->get('sender-ids')['data'] ?? [])));
    }

    /**
     * Indique si un numéro utilise WhatsApp (session WhatsApp connectée
     * requise).
     */
    public function hasWhatsapp(string $phone): bool
    {
        return (bool) data_get($this->connector->get('whatsapp/check-number', ['phone' => Cast::phone($phone)]), 'data.has_whatsapp', false);
    }

    public function connector(): Connector
    {
        return $this->connector;
    }
}

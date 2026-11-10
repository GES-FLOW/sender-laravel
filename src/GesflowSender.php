<?php

namespace Gesflow\Sender;

use Gesflow\Sender\Data\Account;
use Gesflow\Sender\Data\CreditTransaction;
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Data\SenderId;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\TransactionType;
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
use Gesflow\Sender\Support\SmsCounter;
use Illuminate\Support\LazyCollection;
use SensitiveParameter;

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
     * Le même client, pour un autre compte GESFLOW SENDER : utile quand
     * chacun de vos clients a sa propre clé API.
     *
     *     GesflowSender::withApiKey($boutique->gesflow_api_key, 'MaBoutique')->sms(...)->send();
     *
     * @param  string|null  $defaultSenderId  Sender ID par défaut de ce compte (sinon celui de la configuration)
     */
    public function withApiKey(#[SensitiveParameter] string $apiKey, ?string $defaultSenderId = null): static
    {
        $client = clone $this;
        $client->connector = $this->connector->withApiKey($apiKey);
        $client->defaultSenderId = $defaultSenderId ?? $this->defaultSenderId;

        return $client;
    }

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
     * Prix estimé d'un SMS, au tarif de votre compte : prix d'un SMS × nombre
     * de SMS facturés pour ce texte × destinataires. Les {{ variables }}
     * rendent l'estimation approximative.
     */
    public function smsCost(string $text, int $recipients = 1): float
    {
        return SmsCounter::count($text)->cost((float) $this->account()->smsPrice, $recipients);
    }

    /**
     * Mouvements du solde, du plus récent au plus ancien.
     *
     * @return Page<CreditTransaction>
     */
    public function transactions(TransactionType|string|null $type = null, int $page = 1): Page
    {
        return Page::fromResponse(
            $this->connector->get('account/transactions', [
                'type' => $type instanceof TransactionType ? $type->value : $type,
                'page' => $page,
            ]),
            CreditTransaction::fromArray(...),
        );
    }

    /**
     * Tous les mouvements du solde, les pages étant chargées au fil de
     * l'itération.
     *
     * @return LazyCollection<int, CreditTransaction>
     */
    public function transactionsCursor(TransactionType|string|null $type = null): LazyCollection
    {
        return LazyCollection::make(function () use ($type) {
            $page = 1;

            do {
                $result = $this->transactions($type, $page);

                foreach ($result->items as $transaction) {
                    yield $transaction;
                }

                $page = $result->nextPage();
            } while ($page !== null);
        });
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

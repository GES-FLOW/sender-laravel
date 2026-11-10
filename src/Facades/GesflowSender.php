<?php

namespace Gesflow\Sender\Facades;

use Gesflow\Sender\Data\Account;
use Gesflow\Sender\Data\CreditTransaction;
use Gesflow\Sender\Data\InboundMessage;
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Data\Page;
use Gesflow\Sender\Data\SenderId;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\MessageStatus;
use Gesflow\Sender\Enums\TransactionType;
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
use Gesflow\Sender\Testing\GesflowSenderFake;
use Gesflow\Sender\Testing\RecordedRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\LazyCollection;

/**
 * @method static \Gesflow\Sender\GesflowSender withApiKey(string $apiKey, ?string $defaultSenderId = null)
 * @method static PendingMessage sms(string $phone, string $text = '')
 * @method static PendingMessage whatsapp(string $phone, string $text = '')
 * @method static Message send(GesflowMessage $message)
 * @method static PendingBulkMessage bulkSms(string $text = '')
 * @method static PendingBulkMessage bulkWhatsapp(string $text = '')
 * @method static Messages messages()
 * @method static Otp otp()
 * @method static Campaigns campaigns()
 * @method static Contacts contacts()
 * @method static ContactGroups contactGroups()
 * @method static Templates templates()
 * @method static InboundMessages inboundMessages()
 * @method static Payments payments()
 * @method static Account account()
 * @method static float balance()
 * @method static float smsCost(string $text, int $recipients = 1)
 * @method static Page<CreditTransaction> transactions(TransactionType|string|null $type = null, int $page = 1)
 * @method static LazyCollection<int, CreditTransaction> transactionsCursor(TransactionType|string|null $type = null)
 * @method static list<SenderId> senderIds()
 * @method static bool hasWhatsapp(string $phone)
 * @method static Collection<int, Message> sent(?callable $callback = null)
 * @method static void assertSent(?callable $callback = null)
 * @method static void assertNotSent(?callable $callback = null)
 * @method static void assertSentTo(string $phone, ?callable $callback = null)
 * @method static void assertNotSentTo(string $phone, ?callable $callback = null)
 * @method static void assertSentCount(int $count)
 * @method static void assertNothingSent()
 * @method static void assertBulkSent(?callable $callback = null)
 * @method static void assertOtpSent(?string $phone = null)
 * @method static list<RecordedRequest> requests()
 * @method static GesflowSenderFake failWith(int $status, ?string $message = null, array<string, list<string>> $errors = [], string $endpoint = '*', ?int $times = null)
 * @method static GesflowSenderFake stopFailing()
 * @method static GesflowSenderFake withBalance(float $balance)
 * @method static Message updateStatus(Message|int $message, MessageStatus|string $status, ?string $failureReason = null)
 * @method static InboundMessage receive(string $phone, string $text, Channel|string $channel = Channel::Sms)
 *
 * @see \Gesflow\Sender\GesflowSender
 * @see GesflowSenderFake
 */
class GesflowSender extends Facade
{
    /**
     * Remplace le client par une version de test : aucune requête n'est
     * envoyée, les messages sont enregistrés pour vos assertions.
     */
    public static function fake(): GesflowSenderFake
    {
        $senderId = static::getFacadeApplication()?->make('config')->get('gesflow-sender.sender_id');
        $fake = new GesflowSenderFake(is_string($senderId) && $senderId !== '' ? $senderId : null);

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'gesflow-sender';
    }
}

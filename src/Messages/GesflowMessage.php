<?php

namespace Gesflow\Sender\Messages;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\MediaType;
use Gesflow\Sender\Support\Cast;
use InvalidArgumentException;

/**
 * Un message SMS ou WhatsApp à envoyer : retourné par la méthode
 * toGesflowSender() d'une notification, ou envoyé avec
 * GesflowSender::send().
 */
class GesflowMessage
{
    protected ?string $phone = null;

    protected ?string $senderId = null;

    protected ?DateTimeInterface $scheduledAt = null;

    protected ?string $callbackUrl = null;

    protected bool $flash = false;

    protected ?string $mediaUrl = null;

    protected ?MediaType $mediaType = null;

    /** @var array{latitude: float, longitude: float, title: string}|null */
    protected ?array $location = null;

    /** @var list<array<string, string>> */
    protected array $contacts = [];

    protected ?string $idempotencyKey = null;

    final public function __construct(
        protected Channel $channel,
        protected string $text = '',
    ) {}

    public static function sms(string $text = ''): static
    {
        return new static(Channel::Sms, $text);
    }

    public static function whatsapp(string $text = ''): static
    {
        return new static(Channel::Whatsapp, $text);
    }

    /**
     * Numéro du destinataire, avec l'indicatif du pays (242…). Les espaces
     * et le « + » sont acceptés.
     */
    public function to(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Texte du message (1 600 caractères au plus). Pour un média WhatsApp,
     * sert de légende.
     */
    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Sender ID affiché comme expéditeur du SMS : il doit être actif sur
     * votre compte.
     */
    public function from(string $senderId): static
    {
        $this->senderId = $senderId;

        return $this;
    }

    /**
     * Programme l'envoi à une date future. Le prix est débité dès la
     * programmation.
     */
    public function at(DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * URL appelée à chaque changement de statut du message.
     */
    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        return $this;
    }

    /**
     * SMS « flash », affiché directement à l'écran du destinataire.
     */
    public function flash(bool $flash = true): static
    {
        $this->assertChannel(Channel::Sms, 'le mode flash');
        $this->flash = $flash;

        return $this;
    }

    public function image(string $url, ?string $caption = null): static
    {
        return $this->media(MediaType::Image, $url, $caption);
    }

    /**
     * Un document (PDF, tableur…) envoyé en pièce jointe.
     */
    public function document(string $url, ?string $caption = null): static
    {
        return $this->media(MediaType::File, $url, $caption);
    }

    public function video(string $url, ?string $caption = null): static
    {
        return $this->media(MediaType::Video, $url, $caption);
    }

    /**
     * Une note vocale (fichier audio).
     */
    public function voice(string $url): static
    {
        return $this->media(MediaType::Voice, $url);
    }

    /**
     * Un média WhatsApp accessible publiquement par son URL.
     */
    public function media(MediaType $type, string $url, ?string $caption = null): static
    {
        $this->assertChannel(Channel::Whatsapp, 'les médias');
        $this->mediaType = $type;
        $this->mediaUrl = $url;

        if ($caption !== null) {
            $this->text = $caption;
        }

        return $this;
    }

    /**
     * Une position sur la carte.
     */
    public function location(float $latitude, float $longitude, string $title): static
    {
        $this->assertChannel(Channel::Whatsapp, 'les positions');
        $this->location = ['latitude' => $latitude, 'longitude' => $longitude, 'title' => $title];

        return $this;
    }

    /**
     * Une fiche contact (10 au plus par message).
     */
    public function contact(string $fullName, string $phoneNumber, ?string $organization = null): static
    {
        $this->assertChannel(Channel::Whatsapp, 'les fiches contact');

        if (count($this->contacts) >= 10) {
            throw new InvalidArgumentException('Un message WhatsApp contient au plus 10 fiches contact.');
        }

        $this->contacts[] = array_filter([
            'full_name' => $fullName,
            'phone_number' => $phoneNumber,
            'organization' => $organization,
        ], fn (?string $value): bool => $value !== null);

        return $this;
    }

    /**
     * Clé d'idempotence (votre référence de commande, par exemple) : une
     * requête renvoyée avec la même clé dans les 24 heures n'envoie pas un
     * second message. Générée automatiquement si vous n'en donnez pas.
     */
    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function getChannel(): Channel
    {
        return $this->channel;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    /**
     * Le corps de la requête POST /messages.
     *
     * @return array<string, mixed>
     */
    public function toArray(?string $defaultSenderId = null): array
    {
        if ($this->phone === null || Cast::phone($this->phone) === '') {
            throw new InvalidArgumentException('Indiquez le numéro du destinataire avec to().');
        }

        $payload = [
            'channel' => $this->channel->value,
            'phone' => Cast::phone($this->phone),
        ];

        if ($this->text !== '') {
            $payload['text'] = $this->text;
        }

        if ($this->channel === Channel::Sms && ($this->senderId ?? $defaultSenderId) !== null) {
            $payload['sender_id'] = $this->senderId ?? $defaultSenderId;
        }

        if ($this->scheduledAt !== null) {
            $payload['scheduled_at'] = CarbonImmutable::instance($this->scheduledAt)->utc()->toIso8601ZuluString();
        }

        if ($this->callbackUrl !== null) {
            $payload['callback_url'] = $this->callbackUrl;
        }

        if ($this->flash) {
            $payload['flash'] = true;
        }

        if ($this->mediaUrl !== null && $this->mediaType !== null) {
            $payload['media_url'] = $this->mediaUrl;
            $payload['media_type'] = $this->mediaType->value;
        }

        if ($this->location !== null) {
            $payload += $this->location;
        }

        if ($this->contacts !== []) {
            $payload['contacts'] = $this->contacts;
        }

        return $payload;
    }

    protected function assertChannel(Channel $channel, string $feature): void
    {
        if ($this->channel !== $channel) {
            $name = $channel === Channel::Sms ? 'aux SMS' : 'à WhatsApp';

            throw new InvalidArgumentException("Option réservée {$name} : {$feature}.");
        }
    }
}

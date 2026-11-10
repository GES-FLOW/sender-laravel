<?php

namespace Gesflow\Sender\Messages;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Gesflow\Sender\Data\MessageBatch;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Enums\MediaType;
use Gesflow\Sender\Resources\Messages;
use Gesflow\Sender\Support\Cast;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use SplFileInfo;

/**
 * Un envoi en masse en cours de préparation : le même texte, personnalisé
 * par destinataire, envoyé à des numéros, des groupes ou des tags.
 *
 *     GesflowSender::bulkSms('Bonjour {{ prenom|cher client }} !')->toGroups([4])->send();
 */
class PendingBulkMessage
{
    /** @var list<string> */
    protected array $phones = [];

    /** @var list<int> */
    protected array $groupIds = [];

    /** @var list<string> */
    protected array $tags = [];

    protected ?string $campaignName = null;

    protected ?string $senderId = null;

    protected ?DateTimeInterface $scheduledAt = null;

    protected ?string $callbackUrl = null;

    protected bool $flash = false;

    protected ?string $mediaUrl = null;

    protected ?MediaType $mediaType = null;

    protected ?string $idempotencyKey = null;

    /** @var array{contents: string, filename: string}|null */
    protected ?array $file = null;

    public function __construct(
        protected Messages $messages,
        protected Channel $channel,
        protected string $text = '',
        protected ?string $defaultSenderId = null,
    ) {}

    /**
     * Numéros des destinataires (10 000 au plus en SMS, 500 sur WhatsApp).
     *
     * @param  iterable<string>  $phones
     */
    public function to(iterable $phones): static
    {
        foreach ($phones as $phone) {
            $this->phones[] = Cast::phone((string) $phone);
        }

        return $this;
    }

    /**
     * Ajoute tous les contacts de ces groupes.
     *
     * @param  list<int>  $groupIds
     */
    public function toGroups(array $groupIds): static
    {
        $this->groupIds = [...$this->groupIds, ...array_map('intval', $groupIds)];

        return $this;
    }

    /**
     * Ajoute tous les contacts portant au moins un de ces tags.
     *
     * @param  list<string>  $tags
     */
    public function toTags(array $tags): static
    {
        $this->tags = [...$this->tags, ...array_map('strval', $tags)];

        return $this;
    }

    /**
     * Ajoute les numéros d'un fichier de contacts CSV ou XLSX (10 Mo au
     * plus) : sur chaque ligne, la première cellule qui ressemble à un
     * numéro est retenue ; les en-têtes et autres colonnes sont ignorés.
     */
    public function toNumbersFromFile(string|SplFileInfo $file): static
    {
        $path = $file instanceof SplFileInfo ? $file->getRealPath() : $file;
        $filename = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename((string) $path);

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Fichier de contacts introuvable ou illisible.');
        }

        if (! in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['csv', 'txt', 'xlsx'], true)) {
            throw new InvalidArgumentException('Le fichier de contacts doit être au format CSV ou XLSX.');
        }

        $this->file = ['contents' => (string) file_get_contents($path), 'filename' => $filename];

        return $this;
    }

    /**
     * Texte commun, avec variables de personnalisation : {{ prenom }},
     * {{ nom }}, {{ ville|valeur par défaut }}…
     */
    public function text(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Nom de la campagne créée.
     */
    public function name(string $campaignName): static
    {
        $this->campaignName = $campaignName;

        return $this;
    }

    public function from(string $senderId): static
    {
        $this->senderId = $senderId;

        return $this;
    }

    public function at(DateTimeInterface $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function callbackUrl(string $url): static
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function flash(bool $flash = true): static
    {
        if ($this->channel !== Channel::Sms) {
            throw new InvalidArgumentException('Le mode flash est réservé aux SMS.');
        }

        $this->flash = $flash;

        return $this;
    }

    /**
     * Un média WhatsApp envoyé à chaque destinataire.
     */
    public function media(MediaType $type, string $url): static
    {
        if ($this->channel !== Channel::Whatsapp) {
            throw new InvalidArgumentException('Les médias sont réservés à WhatsApp.');
        }

        $this->mediaType = $type;
        $this->mediaUrl = $url;

        return $this;
    }

    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Le corps de la requête POST /messages/bulk.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->phones === [] && $this->groupIds === [] && $this->tags === [] && $this->file === null) {
            throw new InvalidArgumentException('Indiquez des destinataires avec to(), toGroups(), toTags() ou toNumbersFromFile().');
        }

        $payload = array_filter([
            'channel' => $this->channel->value,
            'phones' => $this->phones,
            'group_ids' => $this->groupIds,
            'tags' => $this->tags,
            'text' => $this->text,
            'campaign_name' => $this->campaignName,
            'callback_url' => $this->callbackUrl,
            'media_url' => $this->mediaUrl,
            'media_type' => $this->mediaType?->value,
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        if ($this->channel === Channel::Sms && ($this->senderId ?? $this->defaultSenderId) !== null) {
            $payload['sender_id'] = $this->senderId ?? $this->defaultSenderId;
        }

        if ($this->scheduledAt !== null) {
            $payload['scheduled_at'] = CarbonImmutable::instance($this->scheduledAt)->utc()->toIso8601ZuluString();
        }

        if ($this->flash) {
            $payload['flash'] = true;
        }

        return $payload;
    }

    public function send(): MessageBatch
    {
        return $this->messages->sendBulk($this->toArray(), $this->idempotencyKey, $this->file);
    }
}

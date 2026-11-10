<?php

namespace Gesflow\Sender\Testing;

use Carbon\CarbonImmutable;
use Closure;
use Gesflow\Sender\Exceptions\ApiException;
use Gesflow\Sender\Http\Connector;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use LogicException;

/**
 * L'API simulée en mémoire par GesflowSender::fake(), qui se comporte
 * comme avec une clé sandbox : rien n'est facturé, les messages passent à
 * « sent » (sauf vers le 242000000000, qui échoue toujours) et le code OTP
 * est fixe (123456, ou ses N premiers chiffres de 12345678).
 *
 * @internal
 */
final class FakeConnector extends Connector
{
    public const FAILING_PHONE = '242000000000';

    public const OTP_DIGITS = '12345678';

    public const DEFAULT_SENDER_ID = 'GESFLOW';

    /** @var list<RecordedRequest> */
    private array $requests = [];

    /** @var list<array{message: array<string, mixed>, payload: array<string, mixed>}> */
    private array $sent = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $records = [];

    /** @var array<string, int> */
    private array $lastIds = [];

    /** @var array<string, array{fingerprint: string, response: array<string, mixed>}> */
    private array $replays = [];

    /** @var array<string, array{verification: array<string, mixed>, code: string, attempts: int}> */
    private array $otps = [];

    public function __construct(private ?string $defaultSenderId = null)
    {
        parent::__construct(new Factory, 'https://gesflow-sender.test/api/v1', 'fake');
    }

    /**
     * @return list<RecordedRequest>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    /**
     * Messages créés par POST /messages et POST /messages/bulk, avec le
     * corps de la requête qui les a créés.
     *
     * @return list<array{message: array<string, mixed>, payload: array<string, mixed>}>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function send(string $method, string $path, array $options, ?string $idempotencyKey = null): array
    {
        $path = trim($path, '/');
        /** @var array<string, mixed> $data */
        $data = (array) ($options['json'] ?? $options['query'] ?? []);

        $this->requests[] = new RecordedRequest($method, $path, $data, $idempotencyKey);

        if ($idempotencyKey === null) {
            return $this->respond($method, $path, $data);
        }

        $fingerprint = hash('sha256', (string) json_encode([$method, $path, $data]));

        if (isset($this->replays[$idempotencyKey])) {
            if (! hash_equals($this->replays[$idempotencyKey]['fingerprint'], $fingerprint)) {
                throw $this->error(422, 'Cette clé d’idempotence a déjà été utilisée pour une requête différente.');
            }

            return $this->replays[$idempotencyKey]['response'];
        }

        $response = $this->respond($method, $path, $data);
        $this->replays[$idempotencyKey] = ['fingerprint' => $fingerprint, 'response' => $response];

        return $response;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function respond(string $method, string $path, array $data): array
    {
        /** @var array<string, Closure(int...): array<string, mixed>> $routes */
        $routes = [
            'POST messages' => fn (): array => ['data' => $this->sendMessage($data, $data)],
            'POST messages/bulk' => fn (): array => $this->sendBulk($data),
            'GET messages' => fn (): array => $this->paginate('messages', $data),
            'GET messages/{id}' => fn (int $id): array => ['data' => $this->find('messages', $id)],
            'POST messages/{id}/cancel' => fn (int $id): array => ['data' => $this->cancelMessage($id)],
            'GET campaigns' => fn (): array => $this->paginate('campaigns', $data),
            'GET campaigns/{id}' => fn (int $id): array => ['data' => $this->campaign($id)],
            'POST campaigns/{id}/cancel' => fn (int $id): array => $this->cancelCampaign($id),
            'POST verify/send' => fn (): array => ['data' => $this->sendOtp($data)],
            'POST verify/check' => fn (): array => ['data' => $this->checkOtp($data)],
            'GET account' => fn (): array => ['data' => $this->account()],
            'GET account/transactions' => fn (): array => $this->paginate('transactions', $data),
            'GET sender-ids' => fn (): array => ['data' => [$this->senderId()]],
            'GET whatsapp/check-number' => fn (): array => ['data' => ['phone' => (string) ($data['phone'] ?? ''), 'has_whatsapp' => true]],
            'GET contacts' => fn (): array => $this->paginate('contacts', $data, $this->contactFilter($data)),
            'POST contacts' => fn (): array => ['data' => $this->saveContact(null, $data)],
            'GET contacts/{id}' => fn (int $id): array => ['data' => $this->find('contacts', $id)],
            'PUT contacts/{id}' => fn (int $id): array => ['data' => $this->saveContact($id, $data)],
            'DELETE contacts/{id}' => fn (int $id): array => $this->destroy('contacts', $id),
            'GET contact-groups' => fn (): array => $this->paginate('contact-groups', $data),
            'POST contact-groups' => fn (): array => ['data' => $this->saveGroup(null, $data)],
            'PUT contact-groups/{id}' => fn (int $id): array => ['data' => $this->saveGroup($id, $data)],
            'DELETE contact-groups/{id}' => fn (int $id): array => $this->destroy('contact-groups', $id),
            'POST contact-groups/{id}/contacts' => fn (int $id): array => ['data' => $this->syncGroup($id, (array) ($data['contact_ids'] ?? []), attach: true)],
            'DELETE contact-groups/{id}/contacts' => fn (int $id): array => ['data' => $this->syncGroup($id, (array) ($data['contact_ids'] ?? []), attach: false)],
            'GET templates' => fn (): array => $this->paginate('templates', $data, $this->searchFilter($data, 'name')),
            'POST templates' => fn (): array => ['data' => $this->saveTemplate(null, $data)],
            'GET templates/{id}' => fn (int $id): array => ['data' => $this->find('templates', $id)],
            'PUT templates/{id}' => fn (int $id): array => ['data' => $this->saveTemplate($id, $data)],
            'DELETE templates/{id}' => fn (int $id): array => $this->destroy('templates', $id),
            'GET inbound-messages' => fn (): array => $this->paginate('inbound-messages', $data),
            'GET payments/fees-config' => fn (): array => $this->paymentFees(),
            'POST payments/initiate' => fn (): array => ['data' => $this->initiatePayment($data)],
            'GET payments/me' => fn (): array => $this->paginate('payments', $data),
            'GET payments/{id}' => fn (int $id): array => ['data' => $this->find('payments', $id)],
        ];

        foreach ($routes as $route => $handler) {
            [$routeMethod, $routePath] = explode(' ', $route, 2);
            $pattern = '#^'.str_replace('\{id\}', '(\d+)', preg_quote($routePath, '#')).'$#';

            if ($routeMethod === $method && preg_match($pattern, $path, $matches) === 1) {
                return $handler(...array_map('intval', array_slice($matches, 1)));
            }
        }

        throw new LogicException("GesflowSender::fake() ne simule pas {$method} /{$path}.");
    }

    /**
     * @param  array<string, mixed>  $data  Champs du message
     * @param  array<string, mixed>|null  $payload  Requête d'origine, enregistrée pour les assertions (null pour un code OTP)
     * @return array<string, mixed>
     */
    private function sendMessage(array $data, ?array $payload, ?int $campaignId = null): array
    {
        $now = CarbonImmutable::now();
        $phone = (string) $data['phone'];
        $channel = (string) ($data['channel'] ?? 'sms');
        $scheduledAt = isset($data['scheduled_at']) ? CarbonImmutable::parse((string) $data['scheduled_at']) : null;

        $status = match (true) {
            $scheduledAt !== null && $scheduledAt->isFuture() => 'scheduled',
            $phone === self::FAILING_PHONE => 'failed',
            default => 'sent',
        };

        $message = $this->store('messages', [
            'campaign_id' => $campaignId,
            'sandbox' => true,
            'channel' => $channel,
            'phone' => $phone,
            'text' => $data['text'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'media_type' => $data['media_type'] ?? null,
            'interactive_type' => match (true) {
                isset($data['latitude']) => 'location',
                isset($data['contacts']) => 'contact',
                default => null,
            },
            'interactive_payload' => match (true) {
                isset($data['latitude']) => ['latitude' => $data['latitude'], 'longitude' => $data['longitude'] ?? null, 'title' => $data['title'] ?? null],
                isset($data['contacts']) => ['contacts' => $data['contacts']],
                default => null,
            },
            'from' => $channel === 'whatsapp' ? 'WhatsApp' : (string) ($data['sender_id'] ?? $this->defaultSenderId ?? self::DEFAULT_SENDER_ID),
            'status' => $status,
            'failure_reason' => $status === 'failed' ? 'Échec simulé (numéro de test sandbox).' : null,
            'price' => '0.0000',
            'currency' => 'XAF',
            'scheduled_at' => $scheduledAt?->toJSON(),
            'deferred_until' => null,
            'sent_at' => $status === 'sent' ? $now->toJSON() : null,
            'replayed_at' => null,
            'replayable' => false,
            'created_at' => $now->toJSON(),
        ]);

        if ($payload !== null) {
            $this->sent[] = ['message' => $message, 'payload' => $payload];
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sendBulk(array $data): array
    {
        $phones = $this->bulkRecipients($data);
        $now = CarbonImmutable::now();

        $campaign = $this->store('campaigns', [
            'name' => (string) ($data['campaign_name'] ?? 'Envoi en masse du '.$now->format('d/m/Y H:i')),
            'channel' => (string) ($data['channel'] ?? 'sms'),
            'text' => $data['text'] ?? null,
            'from' => ($data['channel'] ?? 'sms') === 'whatsapp' ? 'WhatsApp' : (string) ($data['sender_id'] ?? $this->defaultSenderId ?? self::DEFAULT_SENDER_ID),
            'recipients_count' => count($phones),
            'scheduled_at' => isset($data['scheduled_at']) ? CarbonImmutable::parse((string) $data['scheduled_at'])->toJSON() : null,
            'created_at' => $now->toJSON(),
            'stats' => [],
        ]);

        $messages = array_map(
            fn (string $phone): array => $this->sendMessage(['phone' => $phone] + $data, $data, (int) $campaign['id']),
            $phones,
        );

        return [
            'data' => $messages,
            'meta' => ['count' => count($messages), 'skipped_rows' => 0, 'campaign_id' => $campaign['id']],
        ];
    }

    /**
     * Les numéros donnés, puis ceux des contacts des groupes et des tags
     * demandés, sans doublon.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function bulkRecipients(array $data): array
    {
        $groupIds = array_map('intval', (array) ($data['group_ids'] ?? []));
        $tags = array_map('strval', (array) ($data['tags'] ?? []));
        $phones = array_map('strval', (array) ($data['phones'] ?? []));

        foreach ($this->records['contacts'] ?? [] as $contact) {
            if (array_intersect($groupIds, array_column($contact['groups'], 'id')) !== [] || array_intersect($tags, $contact['tags']) !== []) {
                $phones[] = (string) $contact['phone'];
            }
        }

        return array_values(array_unique($phones));
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelMessage(int $id): array
    {
        $message = $this->find('messages', $id);

        if ($message['status'] !== 'scheduled') {
            throw $this->error(422, "Ce message n'est plus programmé : il ne peut plus être annulé.");
        }

        return $this->records['messages'][$id] = array_merge($message, [
            'status' => 'failed',
            'failure_reason' => "Envoi programmé annulé par l'utilisateur.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaign(int $id): array
    {
        $campaign = $this->find('campaigns', $id);
        $messages = array_filter($this->records['messages'] ?? [], fn (array $message): bool => $message['campaign_id'] === $id);
        $count = fn (string $status): int => count(array_filter($messages, fn (array $message): bool => $message['status'] === $status));
        $total = count($messages);

        $campaign['stats'] = [
            'total' => $total,
            'sent' => $count('sent'),
            'failed' => $count('failed'),
            'pending' => $count('processing'),
            'scheduled' => $count('scheduled'),
            'delivery_rate' => $total > 0 ? round($count('sent') / $total * 100, 1) : 0.0,
            'failure_rate' => $total > 0 ? round($count('failed') / $total * 100, 1) : 0.0,
            'cost' => 0.0,
        ];

        return $campaign;
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelCampaign(int $id): array
    {
        $this->find('campaigns', $id);
        $cancelled = 0;

        foreach ($this->records['messages'] ?? [] as $messageId => $message) {
            if ($message['campaign_id'] === $id && $message['status'] === 'scheduled') {
                $this->cancelMessage($messageId);
                $cancelled++;
            }
        }

        if ($cancelled === 0) {
            throw $this->error(422, "Cette campagne n'a plus de message programmé à annuler.");
        }

        return ['data' => $this->campaign($id), 'meta' => ['cancelled' => $cancelled]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sendOtp(array $data): array
    {
        $phone = (string) $data['phone'];
        $channel = (string) ($data['channel'] ?? 'sms');
        $expiresIn = (int) ($data['expires_in'] ?? 300);
        $code = substr(self::OTP_DIGITS, 0, (int) ($data['code_length'] ?? 6));
        $text = (string) preg_replace(
            ['/\{\{\s*code\s*\}\}/', '/\{\{\s*minutes\s*\}\}/'],
            [$code, (string) max(1, intdiv($expiresIn, 60))],
            (string) ($data['template'] ?? 'Votre code de vérification est {{ code }}. Il expire dans {{ minutes }} minutes.'),
        );

        $message = $this->sendMessage([
            'channel' => $channel,
            'phone' => $phone,
            'text' => $text,
            'sender_id' => $data['sender_id'] ?? null,
        ], null);

        $verification = [
            'id' => (string) Str::ulid(),
            'phone' => $phone,
            'channel' => $channel,
            'status' => 'pending',
            'message_id' => $message['id'],
            'expires_at' => CarbonImmutable::now()->addSeconds($expiresIn)->toJSON(),
            'sandbox' => true,
            'code' => $code,
        ];

        $this->otps[$phone] = ['verification' => $verification, 'code' => $code, 'attempts' => 0];

        return $verification;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function checkOtp(array $data): array
    {
        $phone = (string) $data['phone'];
        $otp = $this->otps[$phone] ?? null;
        $maxAttempts = 5;

        $result = fn (string $status, int $attempts): array => [
            'id' => $otp['verification']['id'] ?? null,
            'phone' => $phone,
            'valid' => $status === 'approved',
            'status' => $status,
            'attempts_remaining' => $otp === null ? 0 : max(0, $maxAttempts - $attempts),
        ];

        if ($otp === null) {
            return $result('not_found', 0);
        }

        if (CarbonImmutable::parse((string) $otp['verification']['expires_at'])->isPast()) {
            return $result('expired', $otp['attempts']);
        }

        if ($otp['attempts'] >= $maxAttempts) {
            return $result('max_attempts_reached', $otp['attempts']);
        }

        $attempts = ++$this->otps[$phone]['attempts'];

        if (! hash_equals($otp['code'], (string) $data['code'])) {
            return $result($attempts >= $maxAttempts ? 'max_attempts_reached' : 'invalid', $attempts);
        }

        unset($this->otps[$phone]);

        return $result('approved', $attempts);
    }

    /**
     * @return array<string, mixed>
     */
    private function account(): array
    {
        return [
            'id' => 1,
            'name' => 'Compte de test',
            'credit_balance' => '100000.0000',
            'currency' => 'XAF',
            'prices' => ['sms' => '25.0000', 'whatsapp' => '10.0000'],
            'remaining' => ['sms' => 4000, 'whatsapp' => 10000],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function senderId(): array
    {
        return [
            'id' => 1,
            'value' => $this->defaultSenderId ?? self::DEFAULT_SENDER_ID,
            'is_default' => true,
            'created_at' => CarbonImmutable::now()->toJSON(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function saveContact(?int $id, array $data): array
    {
        $current = $id === null ? null : $this->find('contacts', $id);
        $now = CarbonImmutable::now()->toJSON();
        $groupIds = array_map('intval', (array) ($data['group_ids'] ?? array_column($current['groups'] ?? [], 'id')));

        $attributes = [
            'name' => (string) $data['name'],
            'phone' => (string) $data['phone'],
            'tags' => array_values((array) ($data['tags'] ?? $current['tags'] ?? [])),
            'custom_fields' => (array) ($data['custom_fields'] ?? $current['custom_fields'] ?? []),
            'groups' => array_values(array_map(fn (int $groupId): array => $this->find('contact-groups', $groupId), $groupIds)),
            'created_at' => $current['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        $contact = $id === null
            ? $this->store('contacts', $attributes)
            : $this->records['contacts'][$id] = ['id' => $id] + $attributes;

        $this->refreshGroups();

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function saveGroup(?int $id, array $data): array
    {
        $current = $id === null ? null : $this->find('contact-groups', $id);

        $attributes = [
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'contacts_count' => $current['contacts_count'] ?? 0,
            'created_at' => $current['created_at'] ?? CarbonImmutable::now()->toJSON(),
        ];

        return $id === null
            ? $this->store('contact-groups', $attributes)
            : $this->records['contact-groups'][$id] = ['id' => $id] + $attributes;
    }

    /**
     * @param  array<int, mixed>  $contactIds
     * @return array<string, mixed>
     */
    private function syncGroup(int $groupId, array $contactIds, bool $attach): array
    {
        $group = $this->find('contact-groups', $groupId);

        foreach (array_map('intval', $contactIds) as $contactId) {
            $contact = $this->find('contacts', $contactId);
            $groups = array_values(array_filter($contact['groups'], fn (array $existing): bool => $existing['id'] !== $groupId));

            if ($attach) {
                $groups[] = $group;
            }

            $this->records['contacts'][$contactId]['groups'] = $groups;
        }

        $this->refreshGroups();

        return $this->records['contact-groups'][$groupId];
    }

    /**
     * Recalcule le nombre de contacts de chaque groupe et retire des
     * contacts les groupes supprimés.
     */
    private function refreshGroups(): void
    {
        $groups = $this->records['contact-groups'] ?? [];

        foreach ($this->records['contacts'] ?? [] as $contactId => $contact) {
            $this->records['contacts'][$contactId]['groups'] = array_values(array_filter(
                $contact['groups'],
                fn (array $group): bool => isset($groups[$group['id']]),
            ));
        }

        foreach ($groups as $groupId => $group) {
            $this->records['contact-groups'][$groupId]['contacts_count'] = count(array_filter(
                $this->records['contacts'] ?? [],
                fn (array $contact): bool => in_array($groupId, array_column($contact['groups'], 'id'), true),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function saveTemplate(?int $id, array $data): array
    {
        $current = $id === null ? null : $this->find('templates', $id);
        $now = CarbonImmutable::now()->toJSON();

        $attributes = [
            'name' => (string) $data['name'],
            'content' => (string) $data['content'],
            'created_at' => $current['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        return $id === null
            ? $this->store('templates', $attributes)
            : $this->records['templates'][$id] = ['id' => $id] + $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentFees(): array
    {
        return [
            'min_amount' => 500.0,
            'provider_fee_percentage' => 0.035,
            'provider_fee_fixed' => 25.0,
            'tax_vat_percentage' => 0.18,
            'tax_ca_percentage' => 0.05,
            'country' => 'cg',
            'currency' => 'XAF',
            'phone_country_code' => '+242',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function initiatePayment(array $data): array
    {
        $fees = $this->paymentFees();
        $amount = (float) $data['amount'];
        $taxes = round($amount * $fees['tax_vat_percentage'] * (1 + $fees['tax_ca_percentage']), 4);
        $providerFee = round(($amount + $taxes) * $fees['provider_fee_percentage'], 4) + $fees['provider_fee_fixed'];
        $now = CarbonImmutable::now()->toIso8601String();

        return $this->store('payments', [
            'reference' => 'FAKE-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'status_label' => 'En attente',
            'base_amount' => number_format($amount, 4, '.', ''),
            'service_fee' => number_format($providerFee, 4, '.', ''),
            'transaction_fee' => number_format($taxes, 4, '.', ''),
            'amount' => number_format($amount + $providerFee + $taxes, 4, '.', ''),
            'currency' => 'XAF',
            'phone_number' => (string) $data['phone_number'],
            'operator' => (string) $data['operator'],
            'country' => 'cg',
            'failure_message' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Closure(array<string, mixed>): bool
     */
    private function contactFilter(array $data): Closure
    {
        $search = $this->searchFilter($data, 'name', 'phone');
        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
        $tag = isset($data['tag']) ? (string) $data['tag'] : null;

        return fn (array $contact): bool => ($search === null || $search($contact))
            && ($groupId === null || in_array($groupId, array_column($contact['groups'], 'id'), true))
            && ($tag === null || in_array($tag, $contact['tags'], true));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return (Closure(array<string, mixed>): bool)|null
     */
    private function searchFilter(array $data, string ...$fields): ?Closure
    {
        $search = isset($data['search']) ? Str::lower((string) $data['search']) : null;

        if ($search === null || $search === '') {
            return null;
        }

        return fn (array $record): bool => array_filter(
            $fields,
            fn (string $field): bool => str_contains(Str::lower((string) ($record[$field] ?? '')), $search),
        ) !== [];
    }

    /**
     * Les plus récents d'abord, 25 par page comme l'API.
     *
     * @param  array<string, mixed>  $query
     * @param  (Closure(array<string, mixed>): bool)|null  $filter
     * @return array<string, mixed>
     */
    private function paginate(string $collection, array $query, ?Closure $filter = null): array
    {
        $records = array_reverse(array_values($this->records[$collection] ?? []));

        if ($filter !== null) {
            $records = array_values(array_filter($records, $filter));
        }

        $perPage = max(1, (int) ($query['per_page'] ?? 25));
        $page = max(1, (int) ($query['page'] ?? 1));
        $total = count($records);

        return [
            'data' => array_slice($records, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function store(string $collection, array $attributes): array
    {
        $id = $this->lastIds[$collection] = ($this->lastIds[$collection] ?? 0) + 1;

        return $this->records[$collection][$id] = ['id' => $id] + $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function find(string $collection, int $id): array
    {
        return $this->records[$collection][$id] ?? throw $this->error(404, 'Ressource introuvable.');
    }

    /**
     * @return array<string, mixed>
     */
    private function destroy(string $collection, int $id): array
    {
        $this->find($collection, $id);
        unset($this->records[$collection][$id]);
        $this->refreshGroups();

        return [];
    }

    /**
     * L'exception que lèverait le client pour cette réponse de l'API.
     */
    private function error(int $status, string $message): ApiException
    {
        return ApiException::fromResponse(new Response(new Psr7Response(
            $status,
            ['Content-Type' => 'application/json'],
            (string) json_encode(['message' => $message]),
        )));
    }
}

<?php

namespace Gesflow\Sender\Webhooks;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Signature des webhooks GESFLOW SENDER.
 *
 * L'en-tête X-Gesflow-Signature contient « sha256= » suivi du HMAC-SHA256
 * hexadécimal de « {X-Gesflow-Timestamp}.{corps brut} », calculé avec le
 * secret de signature de votre compte.
 */
final class WebhookSignature
{
    public const EVENT_HEADER = 'X-Gesflow-Event';

    public const DELIVERY_HEADER = 'X-Gesflow-Delivery';

    public const TIMESTAMP_HEADER = 'X-Gesflow-Timestamp';

    public const SIGNATURE_HEADER = 'X-Gesflow-Signature';

    /**
     * Signature with the previous secret, sent for 24 hours after the
     * secret is regenerated: an application still configured with the old
     * secret keeps accepting the webhooks until it is updated.
     */
    public const PREVIOUS_SIGNATURE_HEADER = 'X-Gesflow-Previous-Signature';

    /**
     * La valeur attendue de l'en-tête X-Gesflow-Signature.
     */
    public static function compute(string $payload, string $timestamp, #[SensitiveParameter] string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    /**
     * Vérifie la signature et la fraîcheur d'un webhook : un webhook plus
     * vieux que $tolerance secondes est refusé, pour empêcher son rejeu.
     */
    public static function verify(
        string $payload,
        ?string $signature,
        ?string $timestamp,
        #[SensitiveParameter] string $secret,
        int $tolerance = 300,
    ): bool {
        if ($secret === '' || $signature === null || $timestamp === null || ! ctype_digit($timestamp)) {
            return false;
        }

        if ($tolerance > 0 && abs(Carbon::now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::compute($payload, $timestamp, $secret), $signature);
    }

    /**
     * Vérifie un webhook contre plusieurs signatures (actuelle et précédente)
     * et plusieurs secrets (un par compte, ou ancien et nouveau secret).
     *
     * @param  list<string|null>  $signatures
     * @param  list<string>  $secrets
     */
    public static function verifyAny(string $payload, array $signatures, ?string $timestamp, #[SensitiveParameter] array $secrets, int $tolerance = 300): bool
    {
        foreach ($secrets as $secret) {
            foreach ($signatures as $signature) {
                if (self::verify($payload, $signature, $timestamp, $secret, $tolerance)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Les secrets configurés : une chaîne (plusieurs secrets séparés par des
     * virgules) ou une liste.
     *
     * @return list<string>
     */
    public static function secrets(#[SensitiveParameter] mixed $configured): array
    {
        $secrets = is_array($configured) ? $configured : explode(',', is_string($configured) ? $configured : '');

        return array_values(array_filter(
            array_map(fn (mixed $secret): string => is_string($secret) ? trim($secret) : '', $secrets),
            fn (string $secret): bool => $secret !== '',
        ));
    }

    /**
     * Les en-têtes d'un webhook signé, pour tester votre intégration :
     *
     *     $this->postJson('/gesflow-sender/webhook', $payload, WebhookSignature::headers(
     *         json_encode($payload), 'message.status', config('gesflow-sender.webhooks.secret'),
     *     ));
     *
     * @return array<string, string>
     */
    public static function headers(string $payload, string $event, #[SensitiveParameter] string $secret, ?string $deliveryId = null, ?int $timestamp = null): array
    {
        $timestamp = (string) ($timestamp ?? Carbon::now()->getTimestamp());
        $secret = self::secrets($secret)[0] ?? $secret;

        return [
            self::EVENT_HEADER => $event,
            self::DELIVERY_HEADER => $deliveryId ?? (string) Str::uuid(),
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => self::compute($payload, $timestamp, $secret),
        ];
    }
}

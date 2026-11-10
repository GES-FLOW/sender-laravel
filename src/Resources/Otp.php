<?php

namespace Gesflow\Sender\Resources;

use Gesflow\Sender\Data\OtpCheck;
use Gesflow\Sender\Data\OtpVerification;
use Gesflow\Sender\Enums\Channel;
use Gesflow\Sender\Http\Connector;
use Gesflow\Sender\Support\Cast;

/**
 * Codes de vérification à usage unique (OTP), générés et vérifiés par la
 * plateforme.
 */
class Otp extends Resource
{
    public function __construct(Connector $connector, protected ?string $defaultSenderId = null)
    {
        parent::__construct($connector);
    }

    /**
     * Génère un code et l'envoie au numéro.
     *
     * @param  int|null  $codeLength  4 à 8 chiffres (6 par défaut)
     * @param  int|null  $expiresIn  Durée de validité en secondes, 60 à 3 600 (300 par défaut)
     * @param  string|null  $template  Texte contenant {{ code }} et éventuellement {{ minutes }}
     */
    public function send(
        string $phone,
        Channel|string $channel = Channel::Sms,
        ?int $codeLength = null,
        ?int $expiresIn = null,
        ?string $template = null,
        ?string $senderId = null,
    ): OtpVerification {
        $channel = $channel instanceof Channel ? $channel : Channel::from($channel);

        $payload = array_filter([
            'phone' => Cast::phone($phone),
            'channel' => $channel->value,
            'code_length' => $codeLength,
            'expires_in' => $expiresIn,
            'template' => $template,
            'sender_id' => $channel === Channel::Sms ? ($senderId ?? $this->defaultSenderId) : null,
        ], fn (mixed $value): bool => $value !== null);

        return OtpVerification::fromArray((array) $this->connector->post('verify/send', $payload)['data']);
    }

    /**
     * Vérifie le code saisi par l'utilisateur.
     */
    public function check(string $phone, string $code): OtpCheck
    {
        return OtpCheck::fromArray((array) $this->connector->post('verify/check', [
            'phone' => Cast::phone($phone),
            'code' => trim($code),
        ])['data']);
    }
}

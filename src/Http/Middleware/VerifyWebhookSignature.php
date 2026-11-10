<?php

namespace Gesflow\Sender\Http\Middleware;

use Closure;
use Gesflow\Sender\Webhooks\WebhookSignature;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Refuse (403) tout webhook qui n'est pas signé par GESFLOW SENDER avec un
 * des secrets configurés, ou qui est trop ancien. Sans secret configuré,
 * tous les webhooks sont refusés.
 *
 * Plusieurs secrets séparés par des virgules sont acceptés : un par compte
 * GESFLOW SENDER qui appelle l'application. Après la régénération d'un
 * secret, la plateforme signe aussi avec l'ancien pendant 24 heures
 * (X-Gesflow-Previous-Signature) : l'application peut être mise à jour
 * sans perdre de webhook.
 */
class VerifyWebhookSignature
{
    public function __construct(protected Repository $config) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secrets = WebhookSignature::secrets($this->config->get('gesflow-sender.webhooks.secret'));

        if ($secrets === []) {
            throw new AccessDeniedHttpException('Secret des webhooks non configuré (GESFLOW_SENDER_WEBHOOK_SECRET).');
        }

        $isValid = WebhookSignature::verifyAny(
            $request->getContent(),
            [
                $request->headers->get(WebhookSignature::SIGNATURE_HEADER),
                $request->headers->get(WebhookSignature::PREVIOUS_SIGNATURE_HEADER),
            ],
            $request->headers->get(WebhookSignature::TIMESTAMP_HEADER),
            $secrets,
            (int) $this->config->get('gesflow-sender.webhooks.tolerance', 300),
        );

        if (! $isValid) {
            throw new AccessDeniedHttpException('Signature du webhook invalide ou expirée.');
        }

        return $next($request);
    }
}

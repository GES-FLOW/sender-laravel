<?php

namespace Gesflow\Sender\Http\Middleware;

use Closure;
use Gesflow\Sender\Webhooks\WebhookSignature;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Refuse (403) tout webhook qui n'est pas signé par GESFLOW SENDER avec le
 * secret configuré, ou qui est trop ancien. Sans secret configuré, tous
 * les webhooks sont refusés.
 */
class VerifyWebhookSignature
{
    public function __construct(protected Repository $config) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $this->config->get('gesflow-sender.webhooks.secret');

        if (! is_string($secret) || $secret === '') {
            throw new AccessDeniedHttpException('Secret des webhooks non configuré (GESFLOW_SENDER_WEBHOOK_SECRET).');
        }

        $isValid = WebhookSignature::verify(
            $request->getContent(),
            $request->headers->get(WebhookSignature::SIGNATURE_HEADER),
            $request->headers->get(WebhookSignature::TIMESTAMP_HEADER),
            $secret,
            (int) $this->config->get('gesflow-sender.webhooks.tolerance', 300),
        );

        if (! $isValid) {
            throw new AccessDeniedHttpException('Signature du webhook invalide ou expirée.');
        }

        return $next($request);
    }
}

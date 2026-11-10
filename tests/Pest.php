<?php

use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Full URL of an API endpoint in the tests.
 */
function apiUrl(string $path): string
{
    return TestCase::BASE_URL.'/'.ltrim($path, '/');
}

/**
 * Change the package configuration and rebuild the client with it.
 *
 * @param  array<string, mixed>  $config
 */
function reconfigure(array $config): void
{
    foreach ($config as $key => $value) {
        config()->set("gesflow-sender.{$key}", $value);
    }

    app()->forgetInstance('gesflow-sender');
    GesflowSender::clearResolvedInstance('gesflow-sender');
}

/**
 * A message as the API returns it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function messageData(array $overrides = []): array
{
    return array_merge([
        'id' => 42,
        'campaign_id' => null,
        'sandbox' => false,
        'channel' => 'sms',
        'phone' => '242066123456',
        'text' => 'Votre colis est en route.',
        'media_url' => null,
        'media_type' => null,
        'interactive_type' => null,
        'interactive_payload' => null,
        'from' => 'MaBoutique',
        'status' => 'processing',
        'failure_reason' => null,
        'price' => '25.0000',
        'currency' => 'XAF',
        'scheduled_at' => null,
        'deferred_until' => null,
        'sent_at' => null,
        'replayed_at' => null,
        'replayable' => false,
        'created_at' => '2026-11-10T09:30:00.000000Z',
    ], $overrides);
}

/**
 * A paginated API response.
 *
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function pageOf(array $items, int $currentPage = 1, int $lastPage = 1, int $total = 0): array
{
    return [
        'data' => $items,
        'links' => [],
        'meta' => [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => 25,
            'total' => $total > 0 ? $total : count($items),
        ],
    ];
}

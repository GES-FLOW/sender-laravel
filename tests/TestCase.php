<?php

namespace Gesflow\Sender\Tests;

use Gesflow\Sender\Facades\GesflowSender;
use Gesflow\Sender\GesflowSenderServiceProvider;
use Gesflow\Sender\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const BASE_URL = 'https://api.gesflow.test/api/v1';

    public const API_KEY = '12|test-secret';

    public const WEBHOOK_SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        PhoneNumber::flush();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [GesflowSenderServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['GesflowSender' => GesflowSender::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gesflow-sender.api_key', self::API_KEY);
        $app['config']->set('gesflow-sender.base_url', self::BASE_URL);
        $app['config']->set('gesflow-sender.retry_delay', 0);
        $app['config']->set('gesflow-sender.webhooks.secret', self::WEBHOOK_SECRET);
        $app['config']->set('cache.default', 'array');
    }
}

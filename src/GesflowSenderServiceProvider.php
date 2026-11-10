<?php

namespace Gesflow\Sender;

use Gesflow\Sender\Console\CheckCommand;
use Gesflow\Sender\Http\Connector;
use Gesflow\Sender\Http\Controllers\WebhookController;
use Gesflow\Sender\Http\Middleware\VerifyWebhookSignature;
use Gesflow\Sender\Notifications\GesflowSenderChannel;
use Gesflow\Sender\Support\PhoneNumber;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Client\Factory;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GesflowSenderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gesflow-sender.php', 'gesflow-sender');

        $this->app->singleton('gesflow-sender', function (Application $app): GesflowSender {
            /** @var array<string, mixed> $config */
            $config = $app->make('config')->get('gesflow-sender', []);

            return new GesflowSender(
                new Connector(
                    http: $app->make(Factory::class),
                    baseUrl: (string) $config['base_url'],
                    apiKey: filled($config['api_key'] ?? null) ? (string) $config['api_key'] : null,
                    timeout: (int) ($config['timeout'] ?? 30),
                    connectTimeout: (int) ($config['connect_timeout'] ?? 10),
                    retries: (int) ($config['retries'] ?? 2),
                    retryDelay: (int) ($config['retry_delay'] ?? 500),
                ),
                defaultSenderId: filled($config['sender_id'] ?? null) ? (string) $config['sender_id'] : null,
            );
        });

        $this->app->alias('gesflow-sender', GesflowSender::class);

        Notification::resolved(function (ChannelManager $channels): void {
            $channels->extend('gesflow-sender', fn (Application $app): GesflowSenderChannel => $app->make(GesflowSenderChannel::class));
        });
    }

    public function boot(): void
    {
        $config = $this->app->make('config');
        $countryCode = $config->get('gesflow-sender.default_country_code');

        PhoneNumber::defaultCountryCode(is_scalar($countryCode) ? (string) $countryCode : null);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gesflow-sender.php' => $this->app->configPath('gesflow-sender.php'),
            ], 'gesflow-sender-config');

            $this->commands([CheckCommand::class]);
        }

        $this->registerWebhookRoute();
        $this->registerAboutSection();
    }

    /**
     * The SDK's section of « php artisan about ».
     */
    protected function registerAboutSection(): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('GESFLOW SENDER', function (): array {
            $config = $this->app->make('config');
            $path = $config->get('gesflow-sender.webhooks.path');
            $configured = fn (mixed $value): string => filled($value) ? '<fg=green;options=bold>CONFIGURÉ</>' : '<fg=yellow;options=bold>ABSENT</>';

            return [
                'Version' => Connector::version(),
                'URL de l\'API' => (string) $config->get('gesflow-sender.base_url'),
                'Clé API' => $configured($config->get('gesflow-sender.api_key')),
                'Sender ID' => (string) ($config->get('gesflow-sender.sender_id') ?: '-'),
                'Webhooks' => is_string($path) && trim($path, '/') !== '' ? '/'.trim($path, '/') : 'DÉSACTIVÉS',
                'Secret des webhooks' => $configured($config->get('gesflow-sender.webhooks.secret')),
            ];
        });
    }

    /**
     * POST route receiving the webhooks (message statuses and received
     * messages), outside the "web" group: no session, no CSRF token. The
     * signature middleware rejects anything the platform did not sign.
     */
    protected function registerWebhookRoute(): void
    {
        $path = $this->app->make('config')->get('gesflow-sender.webhooks.path');

        if (! is_string($path) || trim($path, '/') === '' || ($this->app instanceof CachesRoutes && $this->app->routesAreCached())) {
            return;
        }

        Route::post(trim($path, '/'), WebhookController::class)
            ->middleware(VerifyWebhookSignature::class)
            ->name('gesflow-sender.webhook');
    }
}

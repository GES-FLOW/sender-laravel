<?php

use Gesflow\Sender\Facades\GesflowSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    URL::forceRootUrl('https://boutique.example');
    URL::forceScheme('https');
    config()->set('cache.stores.redis-like', ['driver' => 'file', 'path' => sys_get_temp_dir()]);
    config()->set('gesflow-sender.webhooks.cache_store', 'redis-like');
});

it('validates a complete configuration', function () {
    reconfigure(['sender_id' => 'GESFLOW']);
    GesflowSender::fake()->withBalance(12500);

    $this->artisan('gesflow-sender:check')
        ->expectsOutputToContain('Compte de test')
        ->expectsOutputToContain('12 500 FCFA')
        ->expectsOutputToContain('https://boutique.example/gesflow-sender/webhook')
        ->expectsOutputToContain('Configuration valide')
        ->assertSuccessful();
});

it('sends a test SMS', function () {
    $fake = GesflowSender::fake();

    $this->artisan('gesflow-sender:check', ['--to' => '+242 06 612 34 56'])
        ->expectsOutputToContain('vers 242066123456 : sent')
        ->assertSuccessful();

    $fake->assertSentTo('242066123456');
});

it('reports the problems', function () {
    reconfigure(['sender_id' => 'INCONNU']);
    config()->set('gesflow-sender.webhooks.secret', null);
    Http::fake([
        apiUrl('account') => Http::response(['data' => ['id' => 3, 'name' => 'Ma Boutique', 'credit_balance' => '0.0000']]),
        apiUrl('sender-ids') => Http::response(['data' => [['id' => 1, 'value' => 'MaBoutique', 'is_default' => true]]]),
    ]);

    $this->artisan('gesflow-sender:check')
        ->expectsOutputToContain('« INCONNU »')
        ->expectsOutputToContain('Ajoutez GESFLOW_SENDER_WEBHOOK_SECRET')
        ->expectsOutputToContain('2 problème(s)')
        ->assertFailed();
});

it('reports a refused or missing API key', function () {
    Http::fake([apiUrl('account') => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $this->artisan('gesflow-sender:check')->expectsOutputToContain('Clé API refusée')->assertFailed();

    reconfigure(['api_key' => null]);

    $this->artisan('gesflow-sender:check')->expectsOutputToContain('Ajoutez GESFLOW_SENDER_API_KEY')->assertFailed();
});

it('warns about a local webhook address and a volatile cache', function () {
    URL::forceRootUrl('http://localhost:8000');
    config()->set('gesflow-sender.webhooks.cache_store', 'array');
    GesflowSender::fake();

    $this->artisan('gesflow-sender:check')
        ->expectsOutputToContain('n\'est pas une adresse publique')
        ->expectsOutputToContain('ne garde rien entre deux requêtes')
        ->assertSuccessful();
});

it('adds a section to the about command', function () {
    $this->artisan('about', ['--only' => 'gesflow_sender'])
        ->expectsOutputToContain('GESFLOW SENDER')
        ->expectsOutputToContain('https://api.gesflow.test/api/v1')
        ->assertSuccessful();
});

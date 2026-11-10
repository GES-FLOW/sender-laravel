<?php

namespace Gesflow\Sender\Console;

use Gesflow\Sender\Exceptions\AuthenticationException;
use Gesflow\Sender\Exceptions\GesflowSenderException;
use Gesflow\Sender\GesflowSender;
use Gesflow\Sender\Http\Connector;
use Gesflow\Sender\Webhooks\WebhookSignature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

/**
 * Vérifie la configuration du SDK : clé API, compte, Sender ID, webhooks ;
 * envoie au besoin un SMS de test.
 */
class CheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'gesflow-sender:check
        {--to= : Envoie un SMS de test à ce numéro}';

    /**
     * @var string
     */
    protected $description = 'Vérifie la configuration de GESFLOW SENDER : clé API, Sender ID, webhooks.';

    protected int $problems = 0;

    public function handle(Repository $config): int
    {
        $this->newLine();
        $this->components->twoColumnDetail('<options=bold>GESFLOW SENDER</>', Connector::PACKAGE.' '.Connector::version());

        $apiKey = $config->get('gesflow-sender.api_key');
        $senderId = $config->get('gesflow-sender.sender_id');

        $this->components->twoColumnDetail('URL de l\'API', (string) $config->get('gesflow-sender.base_url'));
        $this->components->twoColumnDetail('Clé API', is_string($apiKey) && $apiKey !== '' ? $this->mask($apiKey) : '<fg=red>absente</>');
        $this->components->twoColumnDetail('Sender ID par défaut', is_string($senderId) && $senderId !== '' ? $senderId : 'celui du compte');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->problem('Ajoutez GESFLOW_SENDER_API_KEY à votre .env (clé créée dans Compte → Clés API).');
        } else {
            $this->checkAccount(is_string($senderId) && $senderId !== '' ? $senderId : null);
        }

        $this->checkWebhooks($config);

        $this->newLine();

        if ($this->problems > 0) {
            $this->components->error("{$this->problems} problème(s) à corriger.");

            return self::FAILURE;
        }

        $this->components->info('Configuration valide.');

        return self::SUCCESS;
    }

    protected function checkAccount(?string $senderId): void
    {
        $client = $this->client();

        try {
            $account = $client->account();
            $senderIds = array_map(fn ($sender): string => $sender->value, $client->senderIds());
        } catch (AuthenticationException) {
            $this->problem('Clé API refusée : elle est invalide, expirée ou révoquée.');

            return;
        } catch (GesflowSenderException $exception) {
            $this->problem('Connexion à l\'API impossible : '.$exception->getMessage());

            return;
        }

        $this->components->twoColumnDetail('Compte', "{$account->name} (#{$account->id})");
        $this->components->twoColumnDetail('Solde', number_format($account->balance, 0, ',', ' ').' FCFA');
        $this->components->twoColumnDetail('Sender IDs actifs', $senderIds === [] ? '<fg=yellow>aucun</>' : implode(', ', $senderIds));

        if ($senderIds === []) {
            $this->components->warn('Aucun Sender ID actif : les SMS seront refusés (WhatsApp reste possible).');
        }

        if ($senderId !== null && ! in_array($senderId, $senderIds, true)) {
            $this->problem("Le Sender ID « {$senderId} » (GESFLOW_SENDER_SENDER_ID) n'est pas actif sur ce compte.");
        }

        $to = $this->option('to');

        if (is_string($to) && $to !== '') {
            $this->sendTestMessage($client, $to);
        }
    }

    protected function sendTestMessage(GesflowSender $client, string $to): void
    {
        try {
            $message = $client->sms($to, 'Test de configuration GESFLOW SENDER : votre intégration fonctionne.')->send();
        } catch (GesflowSenderException $exception) {
            $this->problem('Envoi du SMS de test impossible : '.$exception->getMessage());

            return;
        }

        $this->components->twoColumnDetail('SMS de test', "#{$message->id} vers {$message->phone} : {$message->status->value}".($message->sandbox ? ' (sandbox)' : ''));
    }

    protected function checkWebhooks(Repository $config): void
    {
        $path = $config->get('gesflow-sender.webhooks.path');

        if (! is_string($path) || trim($path, '/') === '') {
            $this->components->twoColumnDetail('Webhooks', 'route désactivée');

            return;
        }

        $url = url(trim($path, '/'));
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $secrets = WebhookSignature::secrets($config->get('gesflow-sender.webhooks.secret'));

        $this->components->twoColumnDetail('URL des webhooks', $url);
        $this->components->twoColumnDetail('Secret des webhooks', match (count($secrets)) {
            0 => '<fg=red>absent</>',
            1 => 'configuré',
            default => count($secrets).' secrets configurés',
        });

        if ($secrets === []) {
            $this->problem('Ajoutez GESFLOW_SENDER_WEBHOOK_SECRET (Compte → Entreprise) : sans lui, tous les webhooks sont refusés.');
        }

        if ($this->isPrivateHost($host)) {
            $this->components->warn("{$url} n'est pas une adresse publique : la plateforme ne l'appellera pas. En développement, exposez l'application avec un tunnel (Expose, ngrok…).");
        } elseif (! Str::startsWith($url, 'https://')) {
            $this->components->warn('L\'URL des messages entrants doit être en HTTPS : vérifiez APP_URL.');
        }

        $store = $config->get('gesflow-sender.webhooks.cache_store') ?? $config->get('cache.default');

        if ($config->get("cache.stores.{$store}.driver") === 'array') {
            $this->components->warn("Le cache « {$store} » ne garde rien entre deux requêtes : les webhooks renvoyés seront traités deux fois. Choisissez un autre store (GESFLOW_SENDER_WEBHOOK_CACHE_STORE).");
        }
    }

    /**
     * Addresses the platform refuses to call: local names, private and
     * reserved IP addresses.
     */
    protected function isPrivateHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost' || Str::endsWith($host, ['.localhost', '.local', '.test', '.internal', '.lan', '.home.arpa'])) {
            return true;
        }

        $address = trim($host, '[]');

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    protected function problem(string $message): void
    {
        $this->problems++;
        $this->components->error($message);
    }

    protected function client(): GesflowSender
    {
        return $this->laravel->make('gesflow-sender');
    }

    protected function mask(string $apiKey): string
    {
        return Str::mask($apiKey, '•', min(6, intdiv(strlen($apiKey), 3)));
    }
}

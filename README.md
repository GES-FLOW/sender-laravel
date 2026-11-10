# GESFLOW SENDER pour Laravel

SDK Laravel officiel de [GESFLOW SENDER](https://sender.gesflow.com) : envoyez des SMS et des messages WhatsApp, des codes de vérification (OTP), recevez les statuts de remise et les réponses de vos clients, depuis votre application Laravel.

- Envoi unitaire et en masse, SMS et WhatsApp (images, documents, vidéos, notes vocales, positions, fiches contact)
- Canal de notification Laravel `gesflow-sender`
- Codes OTP générés et vérifiés par la plateforme
- Webhooks signés transformés en événements Laravel
- Clé d'idempotence automatique et nouvelles tentatives sûres : un message n'est jamais envoyé deux fois
- `GesflowSender::fake()` pour tester votre code sans rien envoyer

Laravel 11 à 13, PHP 8.2 ou plus récent.

## Installation

```bash
composer require gesflow/sender-laravel
```

Créez une clé API dans votre espace GESFLOW SENDER (**Compte → Clés API**), puis ajoutez-la à votre `.env` :

```dotenv
GESFLOW_SENDER_API_KEY=12|votre-cle-secrete
GESFLOW_SENDER_SENDER_ID=MaMarque
```

`GESFLOW_SENDER_SENDER_ID` est facultatif : sans lui, la plateforme utilise le Sender ID par défaut de votre compte.

Commencez avec une **clé de test (sandbox)** : les envois sont simulés, rien n'est débité, et le numéro `242000000000` échoue toujours pour tester vos cas d'erreur.

Pour modifier les autres réglages, publiez le fichier de configuration :

```bash
php artisan vendor:publish --tag=gesflow-sender-config
```

## Envoyer un SMS

```php
use Gesflow\Sender\Facades\GesflowSender;

$message = GesflowSender::sms('242066123456', 'Votre commande est en route.')->send();

$message->id;      // 1042
$message->status;  // MessageStatus::Sent, ::Processing, ::Scheduled ou ::Failed
$message->price;   // 25.0 (FCFA)
```

Les numéros s'écrivent avec l'indicatif du pays ; espaces et `+` sont acceptés (`+242 06 612 34 56`).

Options disponibles avant `send()` :

```php
GesflowSender::sms('242066123456', 'Rappel : rendez-vous demain à 10 h.')
    ->from('MaMarque')                                    // Sender ID actif de votre compte
    ->at(now()->addDay()->setTime(9, 0))                  // envoi programmé
    ->callbackUrl(route('gesflow-sender.webhook'))       // statuts de remise (voir Webhooks)
    ->idempotencyKey("rappel-{$rendezVous->id}")          // jamais deux fois le même rappel
    ->send();
```

`flash()` envoie un SMS « flash », affiché directement à l'écran du destinataire.

## Envoyer un message WhatsApp

Les messages WhatsApp partent du numéro WhatsApp appairé à votre compte.

```php
GesflowSender::whatsapp('242066123456', 'Bonjour ! Votre facture est disponible.')->send();

GesflowSender::whatsapp('242066123456')->image('https://monsite.cg/promo.jpg', 'Nouveautés de la semaine')->send();
GesflowSender::whatsapp('242066123456')->document('https://monsite.cg/factures/F-1042.pdf', 'Facture F-1042')->send();
GesflowSender::whatsapp('242066123456')->video('https://monsite.cg/demo.mp4')->send();
GesflowSender::whatsapp('242066123456')->voice('https://monsite.cg/message.ogg')->send();
GesflowSender::whatsapp('242066123456')->location(-4.2634, 15.2429, 'Notre agence de Brazzaville')->send();
GesflowSender::whatsapp('242066123456')->contact('Service client', '+242 06 600 00 00', 'Ma Boutique')->send();
```

Les médias doivent être accessibles publiquement par leur URL. `GesflowSender::hasWhatsapp('242066123456')` indique si un numéro utilise WhatsApp.

## Envoi en masse

Un seul appel pour jusqu'à 10 000 destinataires en SMS (500 sur WhatsApp). Les destinataires viennent de numéros, de groupes de contacts ou de tags, sans doublon :

```php
$batch = GesflowSender::bulkSms('Bonjour {{ prenom|cher client }}, -20 % ce week-end !')
    ->to(['242066000001', '242066000002'])
    ->toGroups([4])
    ->toTags(['vip'])
    ->name('Promo du week-end')
    ->send();

$batch->count;       // messages créés
$batch->campaignId;  // campagne créée (null en sandbox)
foreach ($batch as $message) {
    // ...
}
```

Les variables `{{ nom }}`, `{{ prenom }}`, `{{ telephone }}` et les champs personnalisés de vos contacts (`{{ ville }}`…) sont remplacés pour chaque destinataire ; `{{ variable|texte }}` donne une valeur par défaut.

`GesflowSender::bulkWhatsapp('...')->media(MediaType::Image, $url)` envoie un média à chaque destinataire.

## Notifications Laravel

Ajoutez le canal `gesflow-sender` à vos notifications :

```php
use Gesflow\Sender\Messages\GesflowMessage;
use Illuminate\Notifications\Notification;

class CommandeExpediee extends Notification
{
    public function __construct(public Commande $commande) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'gesflow-sender'];
    }

    public function toGesflowSender(object $notifiable): GesflowMessage
    {
        return GesflowMessage::sms("Votre commande {$this->commande->numero} est en route.");
    }
}
```

`toGesflowSender()` peut aussi retourner une simple chaîne (le texte d'un SMS) ou un message WhatsApp : `GesflowMessage::whatsapp('...')->document($url)`.

Le numéro vient de votre modèle :

```php
class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForGesflowSender(Notification $notification): ?string
    {
        return $this->telephone;
    }
}
```

Un notifiable sans numéro est ignoré. Pour un destinataire qui n'est pas un modèle :

```php
Notification::route('gesflow-sender', '242066123456')->notify(new CommandeExpediee($commande));
```

Une notification mise en file d'attente (`ShouldQueue`) peut être relancée sans risque : son identifiant sert de clé d'idempotence, le SMS n'est envoyé qu'une fois.

## Codes de vérification (OTP)

La plateforme génère le code, l'envoie et le vérifie :

```php
use Gesflow\Sender\Enums\OtpStatus;

GesflowSender::otp()->send('242066123456');

// Plus tard, avec le code saisi par l'utilisateur :
$check = GesflowSender::otp()->check('242066123456', $request->input('code'));

if ($check->valid) {
    // numéro vérifié
}

match ($check->status) {
    OtpStatus::Approved => '...',
    OtpStatus::Invalid => "Code incorrect, encore {$check->attemptsRemaining} essai(s).",
    OtpStatus::Expired => 'Code expiré : demandez-en un nouveau.',
    OtpStatus::MaxAttemptsReached => 'Trop d\'essais : demandez un nouveau code.',
    OtpStatus::NotFound => 'Aucun code en attente pour ce numéro.',
};
```

Options de `send()` : `channel: 'whatsapp'`, `codeLength: 4` (4 à 8 chiffres), `expiresIn: 600` (60 à 3 600 secondes), `template: 'Votre code Ma Boutique : {{ code }} (valable {{ minutes }} min)'`, `senderId: 'MaMarque'`.

Avec une clé sandbox, le code est renvoyé dans `$verification->code`. Un même numéro reçoit au plus un code toutes les 30 secondes et 5 codes par 10 minutes (`RateLimitException` au-delà).

## Webhooks : statuts et réponses

Le paquet enregistre la route `POST /gesflow-sender/webhook`. Elle vérifie la signature de chaque appel puis déclenche un événement Laravel.

1. Copiez le secret de signature affiché dans **Compte → Entreprise** (« Signature des webhooks ») :

   ```dotenv
   GESFLOW_SENDER_WEBHOOK_SECRET=whsec_...
   ```

2. Indiquez l'URL `https://votre-site.cg/gesflow-sender/webhook` (`route('gesflow-sender.webhook')`) :
   - comme `callbackUrl()` de vos messages, pour recevoir leurs statuts ;
   - dans **Compte → Entreprise**, comme URL des messages entrants, pour recevoir les réponses.

3. Écoutez les événements :

```php
use Gesflow\Sender\Events\MessageReceived;
use Gesflow\Sender\Events\MessageStatusUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(function (MessageStatusUpdated $event) {
    if ($event->message->isFailed()) {
        Log::warning("SMS {$event->message->id} non remis : {$event->message->failureReason}");
    }
});

Event::listen(function (MessageReceived $event) {
    // $event->message->phone, ->text, ->channel, ->mediaUrl…
});
```

La route n'utilise ni session ni jeton CSRF. Une requête mal signée, ou vieille de plus de 5 minutes, est refusée (403) ; sans secret configuré, tous les webhooks sont refusés. Un webhook déjà traité avec succès (même `X-Gesflow-Delivery`) est ignoré ; si l'un de vos listeners lève une exception, la plateforme le renverra (3 tentatives, à 60 secondes d'intervalle).

`WebhookReceived` est déclenché pour tout webhook valide, avec son type et son contenu bruts.

Changez l'adresse avec `GESFLOW_SENDER_WEBHOOK_PATH`, ou désactivez la route en mettant `webhooks.path` à `null` dans `config/gesflow-sender.php` (vous pouvez alors utiliser le middleware `Gesflow\Sender\Http\Middleware\VerifyWebhookSignature` sur votre propre route).

Les webhooks ne sont envoyés qu'à des adresses publiques : une `callbackUrl()` en `localhost` est refusée. En développement local, exposez votre application avec un tunnel (Expose, ngrok…).

## Autres opérations

```php
GesflowSender::messages()->find(1042);           // un message et son statut
GesflowSender::messages()->list(page: 2);        // 25 par page
GesflowSender::messages()->cancel(1042);         // message programmé, recrédité

GesflowSender::account();                        // solde, prix, messages restants
GesflowSender::balance();                        // 12500.0
GesflowSender::transactions();                   // mouvements du solde
GesflowSender::senderIds();                      // Sender IDs actifs

GesflowSender::campaigns()->list();
GesflowSender::campaigns()->cancel(9);           // annule ses messages programmés

GesflowSender::contacts()->create('Awa Mabiala', '242066123456', tags: ['vip'], customFields: ['ville' => 'Pointe-Noire']);
GesflowSender::contacts()->list(search: 'Awa', groupId: 4, tag: 'vip');
GesflowSender::contactGroups()->create('Clients fidèles');
GesflowSender::contactGroups()->attach(4, [12, 13]);
GesflowSender::templates()->create('Relance', 'Bonjour {{ prenom }}, votre facture est disponible.');

GesflowSender::inboundMessages()->list(since: now()->subDay());

GesflowSender::payments()->fees()->totalFor(10000);          // montant total payé pour 10 000 FCFA crédités
GesflowSender::payments()->initiate(10000, '242066123456', 'mtn');  // recharge Mobile Money (mtn ou airtel)
```

Les listes retournent une `Page` : `items`, `total`, `currentPage`, `lastPage`, `hasMorePages()`, `nextPage()`. Chaque objet retourné expose aussi la réponse brute de l'API : `$message->get('deferred_until')`, `$message->toArray()`.

## Erreurs

Toutes les exceptions héritent de `Gesflow\Sender\Exceptions\GesflowSenderException`.

| Exception | Cas |
|---|---|
| `AuthenticationException` | Clé API invalide, expirée ou révoquée (401) |
| `NotFoundException` | Ressource introuvable (404) |
| `ValidationException` | Données invalides (422) : `$e->errors()` |
| `RequestRejectedException` | Règle métier (422) : solde insuffisant, Sender ID non configuré, session WhatsApp non connectée… |
| `RateLimitException` | Trop de requêtes (429) : `$e->retryAfter()` en secondes |
| `ConflictException` | Requête identique encore en cours de traitement (409) |
| `ServerException` | Erreur de la plateforme (5xx) |
| `ConnectionException` | Plateforme injoignable (réseau, délai dépassé) |
| `MissingApiKeyException` | `GESFLOW_SENDER_API_KEY` absente |

Les exceptions d'API exposent `$e->status()`, `$e->body()` et `$e->getMessage()`. Les messages des erreurs 422 et 429, en français, peuvent être montrés tels quels à vos utilisateurs.

```php
use Gesflow\Sender\Exceptions\RequestRejectedException;

try {
    GesflowSender::sms($telephone, $texte)->send();
} catch (RequestRejectedException $e) {
    return back()->withErrors(['sms' => $e->getMessage()]);
}
```

Les erreurs réseau et 5xx sont retentées automatiquement (2 fois par défaut) avec la même clé d'idempotence : la plateforme renvoie alors la réponse du premier essai au lieu d'envoyer un second message.

## Tests

`GesflowSender::fake()` remplace le client : aucune requête ne part, l'API est simulée en mémoire et vos envois sont enregistrés.

```php
use Gesflow\Sender\Data\Message;
use Gesflow\Sender\Facades\GesflowSender;

it('prévient le client quand sa commande part', function () {
    $fake = GesflowSender::fake();

    $commande->expedier();

    $fake->assertSentTo('242066123456', fn (Message $message) => str_contains($message->text, 'en route'));
    $fake->assertSentCount(1);
});
```

Assertions disponibles : `assertSent()`, `assertNotSent()`, `assertSentTo()`, `assertNotSentTo()`, `assertSentCount()`, `assertNothingSent()`, `assertBulkSent()`, `assertOtpSent()`. Les callbacks reçoivent le `Message` et le corps de la requête. `$fake->sent()` et `$fake->requests()` donnent accès à tout ce qui a été envoyé.

Comme avec une clé sandbox, les messages passent à `sent` (`scheduled` s'ils sont programmés) et le numéro `242000000000` échoue. Le code OTP est toujours `123456` (ou ses premiers chiffres selon `codeLength`). Contacts, groupes, modèles et paiements sont conservés en mémoire le temps du test.

Pour simuler une erreur de l'API, utilisez plutôt `Http::fake()`, que le client respecte :

```php
Http::fake(['*/api/v1/messages' => Http::response(['message' => 'Solde de crédits insuffisant.'], 422)]);
```

Pour tester vos listeners de webhooks, signez la requête avec `WebhookSignature::headers()` :

```php
use Gesflow\Sender\Webhooks\WebhookSignature;

$payload = ['id' => 1042, 'channel' => 'sms', 'phone' => '242066123456', 'status' => 'sent', /* ... */];

$this->postJson('/gesflow-sender/webhook', $payload, WebhookSignature::headers(
    json_encode($payload), 'message.status', config('gesflow-sender.webhooks.secret'),
))->assertOk();
```

## Configuration

| Variable | Défaut | Rôle |
|---|---|---|
| `GESFLOW_SENDER_API_KEY` | — | Clé API (production ou sandbox) |
| `GESFLOW_SENDER_SENDER_ID` | — | Sender ID par défaut des SMS |
| `GESFLOW_SENDER_BASE_URL` | `https://sender.gesflow.com/api/v1` | URL de l'API |
| `GESFLOW_SENDER_TIMEOUT` | `30` | Délai maximal d'une requête, en secondes |
| `GESFLOW_SENDER_CONNECT_TIMEOUT` | `10` | Délai maximal de connexion, en secondes |
| `GESFLOW_SENDER_RETRIES` | `2` | Nouvelles tentatives après une erreur réseau ou 5xx |
| `GESFLOW_SENDER_RETRY_DELAY` | `500` | Attente entre deux tentatives, en millisecondes |
| `GESFLOW_SENDER_WEBHOOK_SECRET` | — | Secret de signature des webhooks |
| `GESFLOW_SENDER_WEBHOOK_PATH` | `gesflow-sender/webhook` | Adresse de la route des webhooks |
| `GESFLOW_SENDER_WEBHOOK_TOLERANCE` | `300` | Âge maximal d'un webhook, en secondes |

Documentation complète de l'API : [sender.gesflow.com/docs/api](https://sender.gesflow.com/docs/api).

## Licence

MIT. Voir [LICENSE.md](LICENSE.md).

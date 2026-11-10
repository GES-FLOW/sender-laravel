# GESFLOW SENDER pour Laravel

SDK Laravel officiel de [GESFLOW SENDER](https://sender.gesflow.com) : envoyez des SMS et des messages WhatsApp, des codes de vérification (OTP), recevez les statuts de remise et les réponses de vos clients, depuis votre application Laravel.

- Envoi unitaire et en masse, SMS et WhatsApp (images, documents, vidéos, notes vocales, positions, fiches contact)
- Canal de notification Laravel `gesflow-sender`
- Codes OTP générés et vérifiés par la plateforme
- Webhooks signés transformés en événements Laravel
- Clé d'idempotence automatique et nouvelles tentatives sûres : un message n'est jamais envoyé deux fois
- Plusieurs comptes (une clé API par client), estimation du nombre de SMS facturés, envoi depuis un fichier CSV/XLSX
- `GesflowSender::fake()` pour tester votre code sans rien envoyer, et `php artisan gesflow-sender:check` pour vérifier l'installation

Laravel 12 ou 13, PHP 8.2 ou plus récent.

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

Vérifiez l'installation (clé API, Sender ID, webhooks) ; `--to` envoie en plus un SMS de test :

```bash
php artisan gesflow-sender:check --to=242066123456
```

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

Les numéros s'écrivent avec l'indicatif du pays ; espaces et `+` sont acceptés (`+242 06 612 34 56`). Si votre base contient des numéros au format local (`06 612 34 56`), définissez l'indicatif à leur ajouter :

```dotenv
GESFLOW_SENDER_DEFAULT_COUNTRY_CODE=242
```

Les numéros d'un autre pays doivent alors commencer par `+` ou `00`. Pour un plan de numérotation où le 0 initial disparaît à l'international, fournissez votre propre règle : `PhoneNumber::normalizeUsing(fn (string $numero) => ...)` (dans un service provider).

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

### Combien de SMS seront facturés ?

Un SMS est facturé par partie : 160 caractères tiennent dans un SMS (153 par partie au-delà), mais un seul caractère hors de l'alphabet GSM (« ê », « ç », emoji…) ramène la limite à 70 (67 par partie).

```php
use Gesflow\Sender\Support\SmsCounter;

$count = SmsCounter::count('Votre commande est prête.');

$count->segments;           // 1
$count->encoding;           // SmsEncoding::Ucs2, à cause du « ê »
$count->unicodeCharacters;  // ['ê']
$count->remaining;          // caractères restants avant le SMS suivant

GesflowSender::smsCost('Votre commande est prête.', recipients: 500);  // prix estimé au tarif de votre compte
```

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

Les destinataires peuvent aussi venir d'un fichier CSV ou XLSX (10 Mo au plus) : sur chaque ligne, la première cellule qui ressemble à un numéro est retenue. Un fichier envoyé par formulaire (`$request->file('contacts')`) est accepté tel quel.

```php
GesflowSender::bulkSms('Promo !')->toNumbersFromFile(storage_path('app/clients.csv'))->send();
```

## Plusieurs comptes

Si chacun de vos clients a son propre compte GESFLOW SENDER, envoyez avec sa clé API :

```php
GesflowSender::withApiKey($boutique->gesflow_api_key, $boutique->sender_id)
    ->sms('242066123456', 'Votre commande est en route.')
    ->send();
```

Le client retourné accepte toutes les opérations (`otp()`, `contacts()`, `account()`…) ; la configuration par défaut n'est pas modifiée.

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

Les webhooks déjà traités sont mémorisés 24 heures dans le cache : choisissez un store partagé et persistant (`redis`, `database`…) avec `GESFLOW_SENDER_WEBHOOK_CACHE_STORE` si votre store par défaut est `array`. La plateforme coupe la connexion au bout de 10 secondes : pour un traitement long, utilisez un listener en file d'attente (`implements ShouldQueue`).

**Changer de secret sans rien perdre.** Après une régénération du secret, la plateforme signe aussi chaque webhook avec l'ancien secret pendant 24 heures (en-tête `X-Gesflow-Previous-Signature`) : votre application continue d'accepter les webhooks le temps de mettre `GESFLOW_SENDER_WEBHOOK_SECRET` à jour.

**Plusieurs comptes.** Indiquez les secrets de tous vos comptes, séparés par des virgules : `GESFLOW_SENDER_WEBHOOK_SECRET=whsec_compte1,whsec_compte2`.

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

Les listes retournent une `Page` : `items`, `total`, `currentPage`, `lastPage`, `hasMorePages()`, `nextPage()`. Pour tout parcourir, `cursor()` charge les pages au fil de l'itération :

```php
GesflowSender::contacts()->cursor(tag: 'vip')->each(function (Contact $contact) {
    // ...
});

GesflowSender::transactionsCursor(TransactionType::MessageRefund)->sum(fn (CreditTransaction $t) => $t->amount);
```

`cursor()` existe pour les messages, campagnes, contacts, groupes, modèles, messages reçus et recharges.

Les statuts ont leurs enums : `$payment->paymentStatus()` (`PaymentStatus`), `$payment->isPending()`, `$transaction->transactionType()` (`TransactionType`), `MobileMoneyOperator::Mtn` pour `initiate()`. Chaque objet retourné expose aussi la réponse brute de l'API : `$message->get('interactive_payload')`, `$message->toArray()`.

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

Les erreurs réseau, les erreurs 502, 503 et 504 et les 409 sont retentées automatiquement (2 fois par défaut) avec la même clé d'idempotence : la plateforme renvoie alors la réponse du premier essai au lieu d'envoyer un second message. Une erreur 500 lors d'un envoi n'est pas retentée, car le message a pu être créé : vérifiez avec `messages()->list()` avant de renvoyer. Les lectures (GET) sont retentées sur toute erreur 5xx.

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

Comme avec une clé sandbox, les messages passent à `sent` (`scheduled` s'ils sont programmés) et le numéro `242000000000` échoue. Le code OTP est toujours `123456` (ou ses premiers chiffres selon `codeLength`). Contacts, groupes, modèles, paiements et numéros d'un fichier CSV sont conservés en mémoire le temps du test.

Le faux client simule aussi les erreurs, le solde et les webhooks :

```php
$fake = GesflowSender::fake()
    ->withBalance(500)
    ->failWith(422, 'Solde de crédits insuffisant pour envoyer ce message.', endpoint: 'POST messages*');

$fake->failWith(503, times: 1);                                          // la prochaine requête seulement
$fake->failWith(422, 'Numéro invalide.', ['phone' => ['Numéro invalide.']]);  // ValidationException
$fake->stopFailing();

$fake->updateStatus($message, MessageStatus::Failed, 'Abonné absent.');  // déclenche MessageStatusUpdated
$fake->receive('242066123456', 'STOP');                                 // déclenche MessageReceived
```

`Http::fake()` reste utilisable avec le vrai client, pour tester une réponse précise de l'API.

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
| `GESFLOW_SENDER_DEFAULT_COUNTRY_CODE` | — | Indicatif ajouté aux numéros au format local (`242`) |
| `GESFLOW_SENDER_BASE_URL` | `https://sender.gesflow.com/api/v1` | URL de l'API |
| `GESFLOW_SENDER_TIMEOUT` | `30` | Délai maximal d'une requête, en secondes |
| `GESFLOW_SENDER_CONNECT_TIMEOUT` | `10` | Délai maximal de connexion, en secondes |
| `GESFLOW_SENDER_RETRIES` | `2` | Nouvelles tentatives après une erreur réseau, 502, 503, 504 ou 409 |
| `GESFLOW_SENDER_RETRY_DELAY` | `500` | Attente entre deux tentatives, en millisecondes |
| `GESFLOW_SENDER_WEBHOOK_SECRET` | — | Secret(s) de signature des webhooks, séparés par des virgules |
| `GESFLOW_SENDER_WEBHOOK_PATH` | `gesflow-sender/webhook` | Adresse de la route des webhooks |
| `GESFLOW_SENDER_WEBHOOK_TOLERANCE` | `300` | Âge maximal d'un webhook, en secondes |
| `GESFLOW_SENDER_WEBHOOK_CACHE_STORE` | store par défaut | Cache des webhooks déjà traités |

Documentation complète de l'API : [sender.gesflow.com/docs/api](https://sender.gesflow.com/docs/api).

## Licence

MIT. Voir [LICENSE.md](LICENSE.md).

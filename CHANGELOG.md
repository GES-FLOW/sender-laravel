# Changelog

Toutes les évolutions notables de `gesflow/sender-laravel` sont consignées ici.

## 1.1.0 — 2026-11-10

### Ajouté

- `GesflowSender::withApiKey()` : envoyer pour plusieurs comptes GESFLOW SENDER (une clé API par client).
- `SmsCounter::count()` et `GesflowSender::smsCost()` : nombre de SMS facturés pour un texte (GSM-7 ou UCS-2) et prix estimé.
- `GESFLOW_SENDER_DEFAULT_COUNTRY_CODE` et `PhoneNumber::normalizeUsing()` : numéros au format local complétés par l'indicatif.
- `cursor()` sur les messages, campagnes, contacts, groupes, modèles, messages reçus et recharges, et `GesflowSender::transactionsCursor()` : parcours de toutes les pages.
- `toNumbersFromFile()` : envoi en masse vers les numéros d'un fichier CSV ou XLSX.
- Webhooks : plusieurs secrets acceptés (séparés par des virgules), signature avec l'ancien secret (`X-Gesflow-Previous-Signature`) acceptée après une régénération, store de cache configurable (`GESFLOW_SENDER_WEBHOOK_CACHE_STORE`).
- Faux client : `failWith()`, `stopFailing()`, `withBalance()`, `updateStatus()` et `receive()` (qui déclenchent les événements des webhooks), lecture des fichiers CSV.
- `php artisan gesflow-sender:check` et section GESFLOW SENDER dans `php artisan about`.
- Enums `PaymentStatus`, `TransactionType`, `MobileMoneyOperator`, `SmsEncoding` ; `Payment::paymentStatus()`, `isPending()`, `isFailed()` ; `CreditTransaction::transactionType()` ; nouveaux champs de `Message` (`currency`, `deferredUntil`, `interactiveType`, `replayable`, `replayedAt`) et `OtpVerification::$status`.
- Version du SDK dans l'en-tête User-Agent.

### Corrigé

- Une erreur 500 lors d'un envoi n'est plus retentée : la plateforme oublie la clé d'idempotence d'une requête en erreur, une nouvelle tentative pouvait donc créer un second message. Seules les erreurs réseau, 502, 503, 504 et 409 sont retentées pour les écritures ; les lectures le restent sur toute erreur 5xx.

## 1.0.0 — 2026-11-10

Première version, pour Laravel 12 et 13 (PHP 8.2 ou plus récent).

- Envoi de SMS et de messages WhatsApp (texte, image, document, vidéo, note vocale, position, fiches contact), unitaire ou en masse (numéros, groupes, tags).
- Canal de notification Laravel `gesflow-sender`, avec clé d'idempotence tirée de l'identifiant de la notification.
- Codes OTP : envoi et vérification.
- Messages, campagnes, contacts, groupes, modèles, messages reçus, compte, Sender IDs, recharges Mobile Money.
- Webhooks signés (`message.status`, `message.received`) : route, vérification de la signature, dédoublonnage et événements Laravel.
- Clé d'idempotence automatique, nouvelles tentatives sûres sur erreur réseau, 5xx et 409.
- Exceptions typées par cas d'erreur.
- `GesflowSender::fake()` et ses assertions pour les tests.

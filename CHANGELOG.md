# Changelog

Toutes les évolutions notables de `gesflow/sender-laravel` sont consignées ici.

## 1.0.0 — 2026-11-10

Première version.

- Envoi de SMS et de messages WhatsApp (texte, image, document, vidéo, note vocale, position, fiches contact), unitaire ou en masse (numéros, groupes, tags).
- Canal de notification Laravel `gesflow-sender`, avec clé d'idempotence tirée de l'identifiant de la notification.
- Codes OTP : envoi et vérification.
- Messages, campagnes, contacts, groupes, modèles, messages reçus, compte, Sender IDs, recharges Mobile Money.
- Webhooks signés (`message.status`, `message.received`) : route, vérification de la signature, dédoublonnage et événements Laravel.
- Clé d'idempotence automatique, nouvelles tentatives sûres sur erreur réseau, 5xx et 409.
- Exceptions typées par cas d'erreur.
- `GesflowSender::fake()` et ses assertions pour les tests.

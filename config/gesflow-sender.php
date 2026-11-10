<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clé API
    |--------------------------------------------------------------------------
    |
    | Créée dans votre espace GESFLOW SENDER (Compte → Clés API). Une clé de
    | test (sandbox) simule les envois sans rien débiter ; une clé de
    | production envoie réellement les messages.
    |
    */

    'api_key' => env('GESFLOW_SENDER_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | URL de l'API
    |--------------------------------------------------------------------------
    */

    'base_url' => env('GESFLOW_SENDER_BASE_URL', 'https://sender.gesflow.com/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Sender ID par défaut
    |--------------------------------------------------------------------------
    |
    | Utilisé pour les SMS envoyés sans Sender ID explicite. Laissé vide, la
    | plateforme utilise le Sender ID par défaut de votre compte.
    |
    */

    'sender_id' => env('GESFLOW_SENDER_SENDER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Délais et nouvelles tentatives
    |--------------------------------------------------------------------------
    |
    | En cas d'erreur réseau ou d'erreur 5xx, la requête est renvoyée avec la
    | même clé d'idempotence : un message n'est jamais envoyé deux fois.
    |
    */

    'timeout' => (int) env('GESFLOW_SENDER_TIMEOUT', 30),

    'connect_timeout' => (int) env('GESFLOW_SENDER_CONNECT_TIMEOUT', 10),

    'retries' => (int) env('GESFLOW_SENDER_RETRIES', 2),

    'retry_delay' => (int) env('GESFLOW_SENDER_RETRY_DELAY', 500),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | secret : affiché dans Compte → Entreprise (« Signature des webhooks »).
    | path : URL de réception enregistrée par le paquet (POST), à indiquer
    | comme callback_url ou comme URL des messages entrants. null la désactive.
    | tolerance : âge maximal d'un webhook, en secondes.
    |
    */

    'webhooks' => [
        'secret' => env('GESFLOW_SENDER_WEBHOOK_SECRET'),
        'path' => env('GESFLOW_SENDER_WEBHOOK_PATH', 'gesflow-sender/webhook'),
        'tolerance' => (int) env('GESFLOW_SENDER_WEBHOOK_TOLERANCE', 300),
    ],

];

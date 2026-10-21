<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp_node' => [
        'url'     => env('WHATSAPP_NODE_URL', 'http://127.0.0.1:3000'),
        'api_key' => env('WHATSAPP_NODE_API_KEY', ''),
        'timeout' => env('WHATSAPP_NODE_TIMEOUT', 10),
    ],

    'whatsapp_node_grupos' => [
        'url'     => env('WHATSAPP_NODE_GRUPOS_URL', 'http://127.0.0.1:3001'),
        'api_key' => env('WHATSAPP_NODE_GRUPOS_API_KEY', ''),
        'timeout' => env('WHATSAPP_NODE_GRUPOS_TIMEOUT', 10),
    ],

    'whatsapp' => [
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN', ''),
        // Número impresso no cabeçalho do catálogo em PDF (ver
        // ProductAdminController@exportPdf).
        'catalogo' => env('WHATSAPP_CATALOGO', '+595 976 883 793'),
    ],

    'clientbank' => [
        'url'      => env('CLIENTBANK_URL', 'https://client-bank.spacedati.com'),
        'name'     => env('CLIENTBANK_NAME'),
        'password' => env('CLIENTBANK_PASSWORD'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 500),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
    ],

    // API pública somente-leitura consumida pelo frontend Orbita (Next.js),
    // autenticada via header X-Orbita-Token. Ver routes/api.php.
    //
    // O caminho de volta é o revalidate: o Orbita cacheia as respostas da API
    // (ISR), então quando um preço muda aqui avisamos ele pra jogar o cache
    // fora na hora — ver App\Services\Orbita\OrbitaRevalidator e, no outro
    // repositório, web/src/app/api/revalidate/route.ts. Vazio desliga o aviso
    // (local/testes), e aí o cache do Orbita só vence pelo tempo.
    'orbita' => [
        'token' => env('ORBITA_API_TOKEN'),
        'revalidate_url' => env('ORBITA_REVALIDATE_URL'),
        'revalidate_secret' => env('ORBITA_REVALIDATE_SECRET'),
    ],

];

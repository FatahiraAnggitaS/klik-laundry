<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'duitku' => [
        'environment' => env('DUITKU_ENVIRONMENT', 'sandbox'),
        'production_enabled' => (bool) env('DUITKU_PRODUCTION_ENABLED', false),
        'merchant_code' => env('DUITKU_MERCHANT_CODE'),
        'api_key' => env('DUITKU_API_KEY'),
        'callback_url' => env('DUITKU_CALLBACK_URL'),
        'return_url' => env('DUITKU_RETURN_URL'),
        'create_invoice_url' => env('DUITKU_CREATE_INVOICE_URL', 'https://api-sandbox.duitku.com/api/merchant/createInvoice'),
        'inquiry_url' => env('DUITKU_INQUIRY_URL', 'https://sandbox.duitku.com/webapi/api/merchant/transactionStatus'),
        'connect_timeout_seconds' => (int) env('DUITKU_CONNECT_TIMEOUT_SECONDS', 5),
        'timeout_seconds' => (int) env('DUITKU_TIMEOUT_SECONDS', 15),
        'payment_url_hosts' => [
            'sandbox' => 'app-sandbox.duitku.com',
            'production' => 'app-prod.duitku.com',
        ],
    ],

];

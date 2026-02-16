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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mytextng' => [
        'key' => env('TEXT_NG_API_KEY'),
        'sender' => env('TEXT_NG_SENDER_NAME', 'Shanono'),
        'route' => env('TEXTNG_ROUTE', '3'),
        'bypass_code' => env('TEXTNG_BYPASS_CODE'),
        'base_url'    => env('TEXTNG_BASE_URL', 'https://api.textng.xyz'),
    ],

    'textng' => [
        'api_key'     => env('TEXT_NG_API_KEY', '2d27732e81bf160f6d0ff245e02ba177111'),
        'bypass_code' => env('TEXT_NG_BYPASS_CODE'),
        'base_url'    => env('TEXT_NG_BASE_URL', 'https://api.textng.xyz'),
        'sender'      => env('TEXT_NG_SENDER_NAME', 'Shanono'),
    ],

    'smile_identity' => [
        'partner_id' => env('SMILE_PARTNER_ID'),
        'api_key' => env('SMILE_API_KEY'),
        'sid_server' => env('SMILE_ENV', '0') === '1' ? 'sandbox' : 'production',
        'callback_url' => env('SMILE_ID_CALLBACK'),
        'base_url' => env('SMILE_IDENTITY_BASE_URL'),
    ],

    'termii' => [
        'api_key'   => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID'),
        'base_url'  => env('TERMII_BASE_URL', 'https://v3.api.termii.com'),
    ],


    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'shanono' => [
        'public' => env('SHANONO_PUBLIC_KEY'),
        'secret'     => env('SHANONO_SECRET_KEY'),
        'encryption' => env('SHANONO_ENCRYPTION'),
        'base_url' => env('SHANONO_BASE_URL', 'https://shanono-bank.vercel.app/'),
    ],

    'shanono_bank' => [
        'auth_base_url' => env('SHANONO_BANK_AUTH_BASE_URL'),
        'base_url' => env('SHANONO_BANK_API_BASE_URL'),
        'debit_url' => env('SHANONO_BANK_DEBIT_URL'),
        'client_id' => env('SHANONO_BANK_CLIENT_ID'),
        'client_secret' => env('SHANONO_BANK_CLIENT_SECRET'),
        'merchant_user_id' => env('SHANONO_BANK_MERCHANT_USER_ID'),
        'username' => env('SHANONO_BANK_USERNAME'),
        'password' => env('SHANONO_BANK_PASSWORD'),
        'account_number' => env('SHANONO_BANK_MERCHANT_ACCOUNT_NUMBER'),
        'webhook_secret_staging' => env('SHANONO_BANK_WEBHOOK_SECRET_STAGING'),
        'webhook_secret_production' => env('SHANONO_BANK_WEBHOOK_SECRET_PRODUCTION'),
        'transaction_pin' => env('SHANONO_BANK_MERCHANT_TRANSACTION_PIN'),
        'app_staging_url' => env('APP_WEBHOOK_STAGING_URL'),
        'app_production_url' => env('APP_WEBHOOK_PRODUCTION_URL'),
    ],

    // 'openrouteservice' => [
    //     'key' => env('ORS_API_KEY'),
    // ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_KEY'),
    ],

    'logistics' => [
        'bank_client_id' => env('LoopFreight_BANK_CLIENT_ID'),
        'bank_customer_id'  => env('LoopFreight_BANK_CUSTOMER_ID'),
        'logistics_admins' => explode(',', env('LoopFreight_ADMIN_EMAILS', '')),
    ],

    'tnt_logistics' => [
        'tnt_client_id' => env('LoopFreight_TNT_CLIENT_ID'),
        'tnt_customer_id'  => env('LoopFreight_TNT_CUSTOMER_ID'),
        'tnt_logistics_admins' => explode(',', env('LoopFreight_TNT_ADMIN_EMAILS', '')),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET'),
    ],

    'flutterwave' => [
        'secret_key' => env('FLUTTERWAVE_SECRET'),
    ],

    'ngbnk' => [
        'base_url' => env('NGBANKSAPI_URL')
    ],


    'app' => [
        'url' => env('APP_URL'),
    ]

];

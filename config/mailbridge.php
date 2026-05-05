<?php

return [
    'default' => [
        'transactional' => env('MAILBRIDGE_TRANSACTIONAL', 'log'),
        'marketing' => env('MAILBRIDGE_MARKETING', 'array'),
    ],

    'fallbacks' => [
        'transactional' => array_filter(explode(',', env('MAILBRIDGE_TRANSACTIONAL_FALLBACKS', ''))),
        'marketing' => array_filter(explode(',', env('MAILBRIDGE_MARKETING_FALLBACKS', ''))),
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS'),
        'name' => env('MAIL_FROM_NAME'),
    ],

    'unsupported' => env('MAILBRIDGE_UNSUPPORTED', 'throw'),

    'templates' => [
        // 'welcome' => ['brevo' => 123, 'postmark' => 'welcome-alias'],
    ],

    'lists' => [
        // 'signup' => ['brevo' => 456, 'mailerlite' => 'group-id'],
    ],

    'providers' => [
        'log' => [
            'driver' => 'log',
            'sdk' => null,
            'version' => null,
            'install' => null,
            'capabilities' => ['transactional.raw', 'transactional.templates', 'marketing.subscribers', 'marketing.campaigns'],
        ],
        'array' => [
            'driver' => 'array',
            'sdk' => null,
            'version' => null,
            'install' => null,
            'capabilities' => ['transactional.raw', 'transactional.templates', 'marketing.subscribers', 'marketing.campaigns'],
        ],
        'brevo' => [
            'driver' => 'brevo',
            'sdk' => 'getbrevo/brevo-php',
            'version' => '2.0.14',
            'install' => 'composer require getbrevo/brevo-php:2.0.14',
            'api_key' => env('BREVO_API_KEY'),
            'capabilities' => [
                'transactional.raw',
                'transactional.templates',
                'transactional.tags',
                'marketing.contacts',
                'marketing.lists',
                'marketing.subscribers.lookup',
                'marketing.subscribers.delete',
                'marketing.campaigns',
                'webhooks.transactional',
                'webhooks.marketing',
            ],
        ],
        'mailersend' => [
            'driver' => 'mailersend',
            'sdk' => 'mailersend/laravel-driver',
            'version' => '3.1.0',
            'install' => 'composer require mailersend/laravel-driver:3.1.0',
            'api_key' => env('MAILERSEND_API_KEY'),
            'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags'],
        ],
        'resend' => [
            'driver' => 'resend',
            'sdk' => 'resend/resend-php',
            'version' => '1.1.0',
            'install' => 'composer require resend/resend-php:1.1.0',
            'api_key' => env('RESEND_API_KEY'),
            'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags'],
        ],
        'postmark' => [
            'driver' => 'postmark',
            'sdk' => 'wildbit/postmark-php',
            'version' => '7.0.0',
            'install' => 'composer require wildbit/postmark-php:7.0.0',
            'server_token' => env('POSTMARK_SERVER_TOKEN'),
            'capabilities' => [
                'transactional.raw',
                'transactional.templates',
                'transactional.metadata',
                'transactional.message_streams',
                'webhooks.transactional',
            ],
        ],
        'mailgun' => [
            'driver' => 'mailgun',
            'sdk' => 'mailgun/mailgun-php',
            'version' => '4.4.0',
            'install' => 'composer require mailgun/mailgun-php:4.4.0 symfony/http-client:7.4.8 nyholm/psr7:1.8.2',
            'api_key' => env('MAILGUN_API_KEY'),
            'domain' => env('MAILGUN_DOMAIN'),
            'capabilities' => [
                'transactional.raw',
                'transactional.templates',
                'transactional.tags',
                'transactional.metadata',
                'webhooks.transactional',
            ],
        ],
        'mailerlite' => [
            'driver' => 'mailerlite',
            'sdk' => 'mailerlite/mailerlite-php',
            'version' => '1.0.5',
            'install' => 'composer require mailerlite/mailerlite-php:1.0.5',
            'api_key' => env('MAILERLITE_API_KEY'),
            'capabilities' => [
                'marketing.subscribers',
                'marketing.groups',
                'marketing.fields',
                'marketing.subscribers.lookup',
                'marketing.subscribers.delete',
                'marketing.campaigns',
                'webhooks.marketing',
            ],
        ],
    ],
];

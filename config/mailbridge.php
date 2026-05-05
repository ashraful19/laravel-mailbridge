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
        // 'welcome' => ['sendgrid' => 'd-template', 'ses' => 'welcome', 'brevo' => 123, 'postmark' => 'welcome-alias', 'mailgun' => 'welcome', 'mailjet' => 123456],
    ],

    'lists' => [
        // 'signup' => ['brevo' => 456, 'mailerlite' => 'group-id', 'mailjet' => 789],
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
        'sendgrid' => [
            'driver' => 'sendgrid',
            'sdk' => 'sendgrid/sendgrid',
            'version' => '8.1.11',
            'install' => 'composer require sendgrid/sendgrid:8.1.11',
            'api_key' => env('SENDGRID_API_KEY'),
            'capabilities' => [
                'transactional.raw',
                'transactional.templates',
                'transactional.tags',
                'transactional.metadata',
            ],
        ],
        'ses' => [
            'driver' => 'ses',
            'sdk' => 'aws/aws-sdk-php',
            'version' => '3.379.0',
            'install' => 'composer require aws/aws-sdk-php:3.379.0',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'capabilities' => [
                'transactional.raw',
                'transactional.templates',
                'transactional.tags',
                'transactional.metadata',
            ],
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
        'mailjet' => [
            'driver' => 'mailjet',
            'sdk' => 'mailjet/mailjet-apiv3-php',
            'version' => '1.6.6',
            'install' => 'composer require mailjet/mailjet-apiv3-php:1.6.6',
            'api_key' => env('MAILJET_API_KEY'),
            'secret_key' => env('MAILJET_SECRET_KEY'),
            'capabilities' => [
                'transactional.raw',
                'transactional.templates',
                'transactional.metadata',
                'marketing.contacts',
                'marketing.lists',
                'marketing.subscribers.lookup',
                'marketing.subscribers.delete',
                'marketing.campaigns',
            ],
        ],
    ],
];

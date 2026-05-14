<?php

return [
    // Default provider keys used when no provider is passed at runtime.
    // Possible values: log, array, sendgrid, ses, brevo, mailersend, resend, postmark, mailerlite, mailchimp, kit, mailgun, mailjet, autosend.
    'default' => [
        // Transactional lane default provider.
        'transactional' => env('MAILBRIDGE_TRANSACTIONAL', 'log'),
        // Marketing lane default provider.
        'marketing' => env('MAILBRIDGE_MARKETING', 'array'),
    ],

    // Optional fallback provider chain per lane.
    // ENV format: comma-separated provider keys, for example: postmark,resend
    'fallbacks' => [
        'transactional' => array_filter(explode(',', env('MAILBRIDGE_TRANSACTIONAL_FALLBACKS', ''))),
        'marketing' => array_filter(explode(',', env('MAILBRIDGE_MARKETING_FALLBACKS', ''))),
    ],

    // Global sender used when a transactional message does not call from().
    'from' => [
        // Sender email address. Example: hello@example.com
        'address' => env('MAIL_FROM_ADDRESS'),
        // Sender display name. Example: Example App
        'name' => env('MAIL_FROM_NAME'),
    ],

    // Behavior when a selected provider does not support a requested feature.
    // Possible values:
    // - throw: throw an exception (default)
    // - ignore: silently ignore unsupported operations
    'unsupported' => env('MAILBRIDGE_UNSUPPORTED', 'throw'),

    // Template alias map used by template('alias').
    // Rule: mapping values must be present and non-empty for the selected provider.
    // Value type depends on provider: string or int.
    'templates' => [
        // 'welcome' => ['sendgrid' => 'd-template', 'ses' => 'welcome', 'brevo' => 123, 'postmark' => 'welcome-alias', 'mailchimp' => 'welcome-template', 'mailgun' => 'welcome', 'mailjet' => 123456, 'autosend' => 'A-welcome123'],
    ],

    // Marketing list/group alias map used by list('alias').
    // Value format is provider-specific (numeric ids, audience ids, or typed values like kit tag:123).
    'lists' => [
        // 'signup' => ['brevo' => 456, 'mailchimp' => 'audience-id', 'kit' => 'tag:123', 'mailerlite' => 'group-id', 'mailjet' => 789, 'autosend' => 'list_abc123'],
    ],

    // Runtime provider credentials/options.
    // Only application-level values belong here.
    // Optional per-provider default sender:
    // 'from' => ['address' => 'billing@example.com', 'name' => 'Billing Team']
    'providers' => [
        'sendgrid' => [
            // ENV: SENDGRID_API_KEY
            'api_key' => env('SENDGRID_API_KEY'),
            // ENV: SENDGRID_MARKETING_SENDER_ID (needed for marketing campaigns)
            'marketing_sender_id' => env('SENDGRID_MARKETING_SENDER_ID'),
        ],

        'ses' => [
            // ENV: AWS_ACCESS_KEY_ID
            'key' => env('AWS_ACCESS_KEY_ID'),
            // ENV: AWS_SECRET_ACCESS_KEY
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            // ENV: AWS_DEFAULT_REGION (example: us-east-1)
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],

        'brevo' => [
            // ENV: BREVO_API_KEY
            'api_key' => env('BREVO_API_KEY'),
        ],

        'mailersend' => [
            // ENV: MAILERSEND_API_KEY
            'api_key' => env('MAILERSEND_API_KEY'),
        ],

        'resend' => [
            // ENV: RESEND_API_KEY
            'api_key' => env('RESEND_API_KEY'),
        ],

        'postmark' => [
            // ENV: POSTMARK_SERVER_TOKEN
            'server_token' => env('POSTMARK_SERVER_TOKEN'),
        ],

        'mailerlite' => [
            // ENV: MAILERLITE_API_KEY
            'api_key' => env('MAILERLITE_API_KEY'),
        ],

        'mailchimp' => [
            // ENV: MAILCHIMP_API_KEY
            'api_key' => env('MAILCHIMP_API_KEY'),
            // ENV: MAILCHIMP_SERVER_PREFIX (example: us1)
            'server' => env('MAILCHIMP_SERVER_PREFIX'),
            // ENV: MAILCHIMP_AUDIENCE_ID
            'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
            // ENV: MAILCHIMP_TRANSACTIONAL_API_KEY
            'transactional_api_key' => env('MAILCHIMP_TRANSACTIONAL_API_KEY'),
        ],

        'kit' => [
            // ENV: KIT_API_KEY
            'api_key' => env('KIT_API_KEY'),
        ],

        'mailgun' => [
            // ENV: MAILGUN_API_KEY
            'api_key' => env('MAILGUN_API_KEY'),
            // ENV: MAILGUN_DOMAIN (example: mg.example.com)
            'domain' => env('MAILGUN_DOMAIN'),
        ],

        'mailjet' => [
            // ENV: MAILJET_API_KEY
            'api_key' => env('MAILJET_API_KEY'),
            // ENV: MAILJET_SECRET_KEY
            'secret_key' => env('MAILJET_SECRET_KEY'),
        ],

        'autosend' => [
            // ENV: AUTOSEND_API_KEY
            'api_key' => env('AUTOSEND_API_KEY'),
        ],
    ],
];

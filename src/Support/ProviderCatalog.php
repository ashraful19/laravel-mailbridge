<?php

namespace Ashraful19\LaravelMailbridge\Support;

final class ProviderCatalog
{
    public static function all(): array
    {
        return [
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
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags', 'transactional.metadata', 'marketing.contacts', 'marketing.lists', 'marketing.subscribers.lookup', 'marketing.subscribers.delete', 'marketing.campaigns'],
            ],
            'ses' => [
                'driver' => 'ses',
                'sdk' => 'aws/aws-sdk-php',
                'version' => '3.379.0',
                'install' => 'composer require aws/aws-sdk-php:3.379.0',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags', 'transactional.metadata'],
            ],
            'brevo' => [
                'driver' => 'brevo',
                'sdk' => 'getbrevo/brevo-php',
                'version' => '2.0.14',
                'install' => 'composer require getbrevo/brevo-php:2.0.14',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags', 'marketing.contacts', 'marketing.lists', 'marketing.subscribers.lookup', 'marketing.subscribers.delete', 'marketing.campaigns', 'webhooks.transactional', 'webhooks.marketing'],
            ],
            'mailersend' => [
                'driver' => 'mailersend',
                'sdk' => 'mailersend/laravel-driver',
                'version' => '3.1.0',
                'install' => 'composer require mailersend/laravel-driver:3.1.0',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags'],
            ],
            'resend' => [
                'driver' => 'resend',
                'sdk' => 'resend/resend-php',
                'version' => '1.1.0',
                'install' => 'composer require resend/resend-php:1.1.0',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags'],
            ],
            'postmark' => [
                'driver' => 'postmark',
                'sdk' => 'wildbit/postmark-php',
                'version' => '7.0.0',
                'install' => 'composer require wildbit/postmark-php:7.0.0',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.metadata', 'transactional.message_streams', 'webhooks.transactional'],
            ],
            'mailerlite' => [
                'driver' => 'mailerlite',
                'sdk' => 'mailerlite/mailerlite-php',
                'version' => '1.0.5',
                'install' => 'composer require mailerlite/mailerlite-php:1.0.5',
                'capabilities' => ['marketing.subscribers', 'marketing.groups', 'marketing.fields', 'marketing.subscribers.lookup', 'marketing.subscribers.delete', 'marketing.campaigns', 'webhooks.marketing'],
            ],
            'mailchimp' => [
                'driver' => 'mailchimp',
                'sdk' => 'mailchimp/marketing',
                'version' => '3.0.80',
                'sdk_packages' => [
                    'mailchimp/marketing' => '3.0.80',
                    'mailchimp/transactional' => '1.4.1',
                ],
                'install' => 'composer require mailchimp/marketing:3.0.80 mailchimp/transactional:1.4.1',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags', 'transactional.metadata', 'marketing.contacts', 'marketing.lists', 'marketing.subscribers.lookup', 'marketing.subscribers.delete', 'marketing.campaigns'],
            ],
            'kit' => [
                'driver' => 'kit',
                'sdk' => 'convertkit/convertkitapi',
                'version' => '2.4',
                'install' => 'composer require convertkit/convertkitapi:2.4',
                'capabilities' => ['marketing.contacts', 'marketing.tags', 'marketing.forms', 'marketing.sequences', 'marketing.subscribers.lookup', 'marketing.campaigns'],
            ],
            'mailgun' => [
                'driver' => 'mailgun',
                'sdk' => 'mailgun/mailgun-php',
                'version' => '4.4.0',
                'install' => 'composer require mailgun/mailgun-php:4.4.0 symfony/http-client:7.4.8 nyholm/psr7:1.8.2',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.tags', 'transactional.metadata', 'webhooks.transactional'],
            ],
            'mailjet' => [
                'driver' => 'mailjet',
                'sdk' => 'mailjet/mailjet-apiv3-php',
                'version' => '1.6.6',
                'install' => 'composer require mailjet/mailjet-apiv3-php:1.6.6',
                'capabilities' => ['transactional.raw', 'transactional.templates', 'transactional.metadata', 'marketing.contacts', 'marketing.lists', 'marketing.subscribers.lookup', 'marketing.subscribers.delete', 'marketing.campaigns'],
            ],
        ];
    }
}

# Laravel MailBridge

[![Tests](https://github.com/ashraful19/laravel-mailbridge/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/ashraful19/laravel-mailbridge/actions/workflows/tests.yml)
[![Docs](https://github.com/ashraful19/laravel-mailbridge/actions/workflows/docs.yml/badge.svg?branch=main)](https://github.com/ashraful19/laravel-mailbridge/actions/workflows/docs.yml)
[![Latest Version](https://img.shields.io/packagist/v/ashraful19/laravel-mailbridge.svg)](https://packagist.org/packages/ashraful19/laravel-mailbridge)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)](composer.json)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E12%7C%5E13-ff2d20.svg)](composer.json)
[![License: MIT](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)

Provider-neutral transactional and marketing email for Laravel 12+.

MailBridge keeps your application code stable while providers, SDKs, template ids, and marketing APIs vary behind adapters. Use normal Laravel mail when you want it. Use MailBridge when you need provider-hosted templates, marketing lists, capability checks, tested SDK pins, and provider fallback.

## Documentation

Full docs: https://ashraful19.github.io/laravel-mailbridge/

Source docs:

- [Installation](docs/guide/installation.md)
- [Provider install](docs/guide/provider-install.md)
- [Normal Laravel mail](docs/guide/laravel-mail.md)
- [Transactional email](docs/guide/transactional.md)
- [Hosted templates](docs/guide/templates.md)
- [Marketing email](docs/guide/marketing.md)
- [Fallback](docs/guide/fallback.md)
- [Testing](docs/guide/testing.md)
- [Security](docs/guide/security.md)
- [Capabilities](docs/guide/capabilities.md)

## Quick Start

```bash
composer require ashraful19/laravel-mailbridge
php artisan vendor:publish --tag=mailbridge-config
php artisan mailbridge:install
```

`mailbridge:install` opens a terminal checklist so you can install one or more provider SDKs. The base package does not install every SDK.

## Basic Usage

Existing Laravel mail stays unchanged:

```php
Mail::to($user)->send(new WelcomeMail($user));
```

Send a Laravel `Mailable` through MailBridge:

```php
MailBridge::transactional()
    ->to($user->email, $user->name)
    ->send(new WelcomeMail($user));
```

Send a provider-hosted template:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->send();
```

Use provider-specific template data when providers expect different variable names:

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->dataFor('brevo', ['FIRSTNAME' => $user->name])
    ->dataFor('postmark', ['name' => $user->name])
    ->send();
```

Send through one provider for this request:

```php
MailBridge::transactional('postmark')
    ->withFallback(false)
    ->to($user->email)
    ->subject('Welcome')
    ->text('Hello')
    ->send();
```

Subscribe a marketing contact:

```php
use Ashraful19\LaravelMailbridge\Data\Subscriber;

MailBridge::marketing()
    ->list('signup')
    ->subscribe(Subscriber::make($user->email)->name($user->name));
```

## Providers

Provider SDKs are installed only when selected, and each install command uses the exact SDK version tested by MailBridge.

| Provider | Lane | Install |
| --- | --- | --- |
| Brevo | Transactional + marketing | `php artisan mailbridge:install brevo` |
| MailerSend | Transactional | `php artisan mailbridge:install mailersend` |
| Resend | Transactional | `php artisan mailbridge:install resend` |
| Postmark | Transactional | `php artisan mailbridge:install postmark` |
| Mailgun | Transactional | `php artisan mailbridge:install mailgun` |
| MailerLite | Marketing | `php artisan mailbridge:install mailerlite` |

Run health checks anytime:

```bash
php artisan mailbridge:doctor
```

## Features

Transactional email:

| Feature | Purpose |
| --- | --- |
| Raw HTML/text send | Send simple app-rendered messages without a Laravel `Mailable`. |
| Laravel `Mailable` send | Keep existing Laravel mail classes and route them through MailBridge. |
| Provider-hosted templates | Send by config alias with `template('welcome')` or direct id with `templateId(...)`. |
| Provider-specific template data | Use `data()` for common variables and `dataFor()` for provider overrides. |
| Recipients | Normalize `to`, `cc`, `bcc`, `from`, and `replyTo` across providers. |
| Attachments | Support provider attachment APIs where available. |
| Tags/categories | Attach provider analytics tags where supported. |
| Metadata/custom args | Attach safe message metadata for provider analytics and webhook correlation. |
| Provider override | Send one message through a specific provider without changing config. |
| Fallback control | Use `withFallback()` or `withFallback(false)` per send. |
| Testing fake | Assert transactional sends without touching provider APIs. |

Marketing email:

| Feature | Purpose |
| --- | --- |
| Subscriber upsert | Create or update marketing contacts. |
| Subscriber lookup/delete | Manage contacts where provider supports it. |
| List/group subscribe | Subscribe contacts to configured list aliases. |
| Unsubscribe | Remove contacts from lists or marketing audiences. |
| Fields/attributes | Sync custom subscriber profile data. |
| Tags/groups | Attach provider grouping/tag concepts where supported. |
| Campaign operations | Create, update, send, schedule, and read reports where supported. |
| Provider override | Run one marketing operation through a specific provider. |
| Fallback control | Retry transient provider/network failures through configured fallbacks. |
| Testing fake | Assert marketing subscriptions without provider calls. |

## Why MailBridge

| Need | MailBridge behavior |
| --- | --- |
| Keep Laravel `Mailable` classes | `Mail::send()` still works; MailBridge can also send `Mailable` objects. |
| Avoid installing every SDK | Provider SDKs are optional and installed per provider. |
| Prevent surprise SDK breaks | Install commands use tested exact versions. |
| Use hosted templates | Send by `template('welcome')` alias or direct `templateId('...')`. |
| Switch provider per send | Pass provider name: `MailBridge::transactional('postmark')`. |
| Control fallback | Use `withFallback()` or `withFallback(false)` per send. |
| Test safely | Use `MailBridge::fake()` and assertions. |

## License

Laravel MailBridge is open-sourced software licensed under the [MIT license](LICENSE.md).

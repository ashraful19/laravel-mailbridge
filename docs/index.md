---
layout: home

hero:
  name: Laravel MailBridge
  text: Provider-neutral email for Laravel.
  tagline: Send transactional mail, hosted templates, and marketing contacts through one stable Laravel API.
  actions:
    - theme: brand
      text: Get Started
      link: /guide/installation
    - theme: alt
      text: View Capabilities
      link: /guide/capabilities

features:
  - title: One API over provider SDKs
    details: Your Laravel app calls MailBridge methods while each adapter uses the provider's official SDK internally.
  - title: Provider SDKs, pinned
    details: Install only the SDKs you use, at versions tested by the package.
  - title: Transactional + marketing
    details: Keep email sends, hosted templates, subscribers, and lists in separate clean lanes.
  - title: Template data per provider
    details: Use common template data once, then override only the variables that differ for SendGrid, Brevo, Postmark, Mailgun, or another provider.
  - title: Fallback control
    details: Retry transient SDK, template, and marketing provider failures through configured fallback providers.
  - title: Testable by design
    details: Fake provider support and assertions make app tests independent from provider APIs.
  - title: Security-minded
    details: Redacted context, webhook verification plan, and no default logging of sensitive payloads.
---

## One API, multiple providers

```php
MailBridge::transactional()
    ->template('welcome')
    ->to($user->email)
    ->data(['name' => $user->name])
    ->dataFor('brevo', ['FIRSTNAME' => $user->name])
    ->dataFor('postmark', ['name' => $user->name])
    ->send();
```

`data()` is the baseline. `dataFor()` is provider-specific and wins when that provider sends the message.

## Laravel Mailables Still Work

```php
MailBridge::transactional()
    ->to($user->email)
    ->send(new WelcomeMail($user));
```

Plain Laravel `Mail::send()` remains untouched for apps that still use it.

## Install only what you use

```bash
php artisan mailbridge:install
php artisan mailbridge:doctor
```

Current SDK-backed providers include SendGrid, Amazon SES, Brevo, MailerSend, Resend, Postmark, MailerLite, Mailgun, and Mailjet.

## Feature coverage

| Lane | Common features |
| --- | --- |
| Transactional | HTML/text, Laravel Mailables, hosted templates, provider-specific template data, attachments, cc/bcc/reply-to, tags, metadata, provider override, fallback, fake assertions |
| Marketing | subscribe/unsubscribe, lookup/delete, lists/groups, fields/attributes, campaigns, provider override, fallback, fake assertions |

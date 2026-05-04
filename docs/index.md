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
  - title: Laravel-native
    details: Keep normal Mail::send() flows and Laravel Mailable classes. Add MailBridge only where provider abstraction helps.
  - title: Provider SDKs, pinned
    details: Install only the SDKs you use, at versions tested by the package.
  - title: Transactional + marketing
    details: Keep email sends, hosted templates, subscribers, lists, and campaigns in separate clean lanes.
  - title: Fallback control
    details: Use Laravel mail failover for normal mail, and MailBridge fallback for SDK/template/marketing paths.
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
    ->send();
```

## Existing Laravel mail still works

```php
Mail::to($user)->send(new WelcomeMail($user));
```

## Install only what you use

```bash
php artisan mailbridge:install
php artisan mailbridge:doctor
```

## Feature coverage

| Lane | Common features |
| --- | --- |
| Transactional | HTML/text, Laravel Mailables, hosted templates, attachments, cc/bcc/reply-to, tags, metadata, provider override, fallback, fake assertions |
| Marketing | subscribers, lists/groups, unsubscribe, fields, tags/groups, campaigns, reports, provider override, fallback, fake assertions |

# Upgrade Guide

This guide covers breaking upgrades to Laravel MailBridge. For non-breaking
releases, the regular `composer update` flow is sufficient.

## MailBridge 1.x → 2.x

MailBridge 2.0 ships with the Brevo provider rewritten against
`getbrevo/brevo-php:5.0.1`. The 5.x SDK is a complete rewrite of the 2.x line and
the two are not interchangeable.

### Who is affected

You are affected by this breaking change if **any** of the following are true:

- Your app sends transactional email through the Brevo provider.
- Your app manages subscribers, lists, or campaigns through the Brevo provider.
- Your `composer.json` directly requires `getbrevo/brevo-php`.
- Your `config/mailbridge.php` sets `MAILBRIDGE_TRANSACTIONAL=brevo` or
  `MAILBRIDGE_MARKETING=brevo`.

You are **not** affected if you never installed the Brevo SDK and never configured
the Brevo provider. A plain `composer update ashraful19/laravel-mailbridge` is
enough.

### Why this is breaking

The Brevo 5.x SDK removes the `Brevo\Client\*` namespace entirely. MailBridge 2.0
now imports from the new `Brevo\Brevo` unified client and the per-resource
`Requests\*` / `Types\*` namespaces. If MailBridge 2.0 is loaded while the 2.x SDK
is still installed, every call into `BrevoProvider` will fail the internal
`class_exists(\Brevo\Brevo::class)` guard and throw
`Ashraful19\LaravelMailbridge\Exceptions\MissingSdkException`.

The provider's public surface (method names, payload contracts, returned
`SendResult` / `MarketingResult` shapes) is unchanged. Only the underlying SDK
calls move.

### Upgrade steps

Run these in order in the project that consumes MailBridge.

#### 1. Bump MailBridge

```bash
composer require ashraful19/laravel-mailbridge:^2.0
```

#### 2. Bump the Brevo SDK

Pick one of the two equivalent commands. The install command is preferred because
it uses the exact tested pin recorded in `ProviderCatalog`.

```bash
# recommended
php artisan mailbridge:install brevo

# or, manual
composer require getbrevo/brevo-php:5.0.1
```

If Composer reports a dependency conflict on `getbrevo/brevo-php`, another package
in your project is constraining the SDK to 2.x. Inspect the conflict with:

```bash
composer why-not getbrevo/brevo-php 5.0.1
```

Resolve the conflict before continuing. MailBridge does not constrain the SDK in
its own `require` (only `require-dev`), so it will not be the source of the
conflict.

#### 3. Verify the install

```bash
php artisan mailbridge:doctor
php artisan mailbridge:verify brevo
```

`doctor` checks that the installed Brevo SDK matches the tested pin.
`verify` confirms the API key is reachable and the provider can construct a
client.

#### 4. Smoke-test a live send

The unit test suite covers payload shape, not live API calls. After upgrading,
send one real transactional email and exercise one marketing operation
(`subscribe`, `createCampaign`, etc.) against your Brevo account to confirm
end-to-end behavior.

### What changed in your code

Nothing. The `MailBridge` facade, the `TransactionalMessage` / `Subscriber` /
`Campaign` builders, the `SendResult` / `MarketingResult` return shapes, and the
config keys are all unchanged. Application code that called the Brevo provider on
1.x will work unchanged once the SDK is bumped.

### Rollback

To revert to 1.x behavior:

```bash
composer require ashraful19/laravel-mailbridge:^1.5
composer require getbrevo/brevo-php:2.0.14
```

Both constraints must move together. MailBridge 1.x and 2.x are not compatible
with the opposite Brevo SDK major.

### Reference

- Brevo 5.x release notes: <https://github.com/getbrevo/brevo-php/releases>
- Brevo 5.x migration guide (v1 → v5): <https://github.com/getbrevo/brevo-php#migration-from-v1x>
- Project changelog: [`CHANGELOG.md`](https://github.com/ashraful19/laravel-mailbridge/blob/main/CHANGELOG.md)

# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.5] - 2026-07-17

### Fixed

- **Brevo typed objects for transactional payload.** Brevo SDK 5.x requires typed
  objects (`SendTransacEmailRequestSender`, `SendTransacEmailRequestToItem`, etc.)
  instead of plain arrays for transactional email payloads.

## [2.0.4] - 2026-07-17

### Fixed

- **Brevo template params TypeError.** Removed `(object)` cast on template `params`
  in `BrevoProvider::transactionalPayload()`. Brevo SDK 5.x expects `params` as
  `?array`, not `stdClass`.

## [2.0.3] - earlier

### Changed — breaking

- **Brevo SDK upgraded from `2.0.14` to `5.0.1`.** The `getbrevo/brevo-php` package
  is now pinned at `5.0.1` across `composer.json`, `ProviderCatalog`, the install
  command, and the provider-install docs. The Brevo 5.x SDK is a complete rewrite of
  the 2.x line: the `Brevo\Client\*` namespace is removed and replaced by the unified
  `Brevo\Brevo` client with namespaced resources (`$client->transactionalEmails`,
  `$client->contacts`, `$client->emailCampaigns`).

  - `src/Providers/BrevoProvider.php` rewritten to use the new SDK. Public provider
    API (`send()`, `subscribe()`, `unsubscribe()`, `getSubscriber()`, `deleteSubscriber()`,
    `createCampaign()`, `sendCampaign()`, `scheduleCampaign()`, `getCampaign()`,
    `deleteCampaign()`, `transactionalPayload()`, `campaignPayload()`) is unchanged.
  - `src/Commands/VerifyCommand.php` now probes `\Brevo\Brevo::class` for SDK detection.
  - `src/Support/ProviderCatalog.php` brevo `version` and `install` strings bumped.
  - `docs/guide/provider-install.md` brevo pin bumped.
  - `tests/Unit/ProviderMetadataTest.php` brevo install assertion bumped.

### Removed

- `Brevo\Client\*` imports and the private `configuration()` helper in `BrevoProvider`.
  Both referenced SDK symbols that no longer exist in 5.x.

### Upgrade path

See [`docs/guide/upgrade.md`](docs/guide/upgrade.md). Consumers using the Brevo
provider **must** bump their installed `getbrevo/brevo-php` to `5.0.1` alongside the
MailBridge update; otherwise the Brevo provider will throw `MissingSdkException` at
runtime because the 2.x classes it now looks for are absent.

## [1.5.0] - earlier

See git history for releases prior to the Brevo 5.x migration.

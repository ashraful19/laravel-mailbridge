# Brevo SDK 2.x → 5.x Migration Design

**Date:** 2026-07-17
**Status:** Draft
**Scope:** `getbrevo/brevo-php` upgrade from `2.0.14` to `5.0.1` (exact pin).

## 1. Background

The project pins `getbrevo/brevo-php:2.0.14` across `composer.json`, `ProviderCatalog`, the install command, and docs. Brevo's 5.x release is a complete SDK rewrite:

- `Brevo\Client\*` namespace removed.
- Unified `Brevo\Brevo` client with namespaced resources (`$client->transactionalEmails`, `$client->contacts`, `$client->emailCampaigns`).
- `Configuration::getDefaultConfiguration()` removed — API key passed directly to the client constructor.
- Model classes (`Brevo\Client\Model\*`) replaced by per-resource `Requests\*` and `Types\*` classes.
- Native PSR-18 HTTP client auto-discovery via `php-http/discovery`.
- Exceptions unified under `Brevo\Exceptions\BrevoApiException` / `BrevoException`.

Method names for the 10 endpoints used by this project are preserved across the rewrite. Only invocation pattern, namespaces, and Request class names change.

## 2. Goals

- Upgrade Brevo SDK to `5.0.1` with minimal-shape rewrite (Approach A).
- Preserve existing payload-builder contracts (no payload array shape changes).
- Keep test suite green without expanding SDK-call coverage.
- Maintain project convention of exact version pinning.

## 3. Non-Goals

- Backward compatibility with Brevo 2.x. Hard cutover.
- Mocked SDK-call tests for brevo or other providers.
- Upgrades of other SDKs (separate effort).
- Adapter/abstraction layer for SDK calls.

## 4. Approach — Minimal-shape rewrite

Keep `BrevoProvider` structure unchanged. Swap imports, client initialization, and Request class names. Payload arrays returned by `transactionalPayload()` and `campaignPayload()` remain identical and continue to feed the new Request constructors.

Rationale: payload builders already isolate the SDK from message-shape concerns; the only delta is the SDK's invocation pattern. Other providers in the codebase follow the same direct-call style, so an adapter layer would break consistency.

## 5. Design

### 5.1 Imports — `src/Providers/BrevoProvider.php`

Remove:

```php
use Brevo\Client\Api\ContactsApi;
use Brevo\Client\Api\EmailCampaignsApi;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\AddContactToList;
use Brevo\Client\Model\CreateContact;
use Brevo\Client\Model\CreateEmailCampaign;
use Brevo\Client\Model\CreateEmailCampaignRecipients;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Model\RemoveContactFromList;
```

Add:

```php
use Brevo\Brevo;
use Brevo\Contacts\Requests\CreateContactRequest;
use Brevo\Contacts\Requests\RemoveContactFromListRequest;
use Brevo\EmailCampaigns\Requests\CreateEmailCampaignRequest;
use Brevo\EmailCampaigns\Requests\UpdateEmailCampaignRequest;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
```

`AddContactToList` and `CreateEmailCampaignRecipients` were imported but never referenced in 2.x code — they are dropped, not migrated.

### 5.2 Client initialization

The `configuration()` private method (`BrevoProvider.php:245-248`) is removed entirely.

The three private client factories (`transactionalClient()`, `contactsClient()`, `campaignsClient()` at `:206-219`) are rewritten to return resource properties of a single cached `Brevo` instance:

```php
private ?Brevo $cachedBrevo = null;

private function brevo(): Brevo
{
    return $this->cachedBrevo ??= new Brevo($this->requireConfig('api_key'));
}

private function transactionalClient(): mixed
{
    return $this->transactionalApi ?? $this->brevo()->transactionalEmails;
}

private function contactsClient(): mixed
{
    return $this->contactsApi ?? $this->brevo()->contacts;
}

private function campaignsClient(): mixed
{
    return $this->campaignsApi ?? $this->brevo()->emailCampaigns;
}
```

Constructor signature stays as-is (three optional injected API clients). The `?Brevo $brevo` injection is not added — the injected-client seams remain at the resource level, preserving existing test patterns that mock individual API resources.

### 5.3 API call sites

| Method | Line | Change |
|---|---|---|
| `send()` | 51 | `new SendSmtpEmail($payload)` → `new SendTransacEmailRequest($payload)`. Response access changes from getters to public properties: `$response->getMessageId()` → `$response->messageId`, `$response->getMessageIds()` → `$response->messageIds` (5.x `SendTransacEmailResponse` exposes public props, no getters). |
| `subscribe()` | 77 | `new CreateContact($payload)` → `new CreateContactRequest($payload)`. |
| `unsubscribe()` | 88 | `new RemoveContactFromList(['emails' => [...]])` → `new RemoveContactFromListRequest(['emails' => [...]])`. **Arg order flipped:** `removeContactFromList($listId, $request)` instead of `($request, $listId)`. |
| `getSubscriber()` | 99 | `getContactInfo($email)` — unchanged. Optional second `$request` arg omitted. |
| `deleteSubscriber()` | 110 | `deleteContact($email)` — unchanged. |
| `createCampaign()` | 121 | `new CreateEmailCampaign($payload)` → `new CreateEmailCampaignRequest($payload)`. Response access changes: `method_exists($response, 'getId') ? $response->getId() : null` → `$response?->id` (5.x `CreateEmailCampaignResponse` exposes public `int $id`, no `getId()`). |
| `sendCampaign()` | 132 | `sendEmailCampaignNow((int) $campaignId)` — unchanged. |
| `scheduleCampaign()` | 143 | `new \Brevo\Client\Model\UpdateEmailCampaign([...])` → `new UpdateEmailCampaignRequest([...])`. **Arg order flipped:** `updateEmailCampaign($campaignId, $request)`. |
| `getCampaign()` | 156 | `getEmailCampaign((int) $campaignId, 'globalStats')` — unchanged. |
| `deleteCampaign()` | 167 | `deleteEmailCampaign((int) $campaignId)` — unchanged. |

### 5.4 Payload builders

`transactionalPayload()` (`:175-204`) and `numericId()` remain byte-identical.

`campaignPayload()` (`:221-234`) requires one targeted change at `:231`: the `CreateEmailCampaignRecipients` model wrapper is renamed in 5.x to `CreateEmailCampaignRequestRecipients` (under `Brevo\EmailCampaigns\Types\`). Use the FQCN `\Brevo\EmailCampaigns\Types\CreateEmailCampaignRequestRecipients` inline (no additional `use` statement) to preserve the import block as specified in §5.1. This is required because 5.x `CreateEmailCampaignRequest` types its `recipients` property as `?CreateEmailCampaignRequestRecipients` — a plain array would raise a `TypeError` at runtime.

### 5.5 Error handling

No change. `ProviderFailureHandler::throw($name, $context, $exception)` accepts `Throwable`, which covers both `BrevoApiException` and `BrevoException` from 5.x. Existing try/catch blocks at all 10 call sites remain valid.

### 5.6 Auxiliary files

| File | Change |
|---|---|
| `composer.json:41` | `"getbrevo/brevo-php": "2.0.14"` → `"5.0.1"` |
| `src/Support/ProviderCatalog.php:41` | `'version' => '2.0.14'` → `'5.0.1'` |
| `src/Support/ProviderCatalog.php:42` | `'install' => 'composer require getbrevo/brevo-php:2.0.14'` → `'...:5.0.1'` |
| `src/Commands/VerifyCommand.php:516` | `\Brevo\Client\Api\TransactionalEmailsApi::class` → `\Brevo\Brevo::class` |
| `docs/guide/provider-install.md:48` | `getbrevo/brevo-php:2.0.14` → `:5.0.1` |
| `tests/Unit/ProviderMetadataTest.php:16` | assertion string bump |
| `composer.lock` | regenerated via `composer update getbrevo/brevo-php --with-dependencies` |

## 6. Dependencies

- **PHP:** 5.x requires `^8.1`. Project minimum is `^8.2`. No conflict.
- **HTTP client:** 5.x uses `php-http/discovery` for PSR-18 auto-discovery. `symfony/http-client` + `nyholm/psr7` are already in `require-dev` (transitive via mailgun). No new explicit dependency required.

## 7. Testing strategy

- Existing tests in `tests/Unit/ProviderAdapterTest.php` only exercise `campaignPayload()` and `transactionalPayload()` — pure array builders with no SDK coupling. These remain green without modification.
- `tests/Unit/ProviderMetadataTest.php` asserts the install string for brevo. One-line assertion bump.
- SDK call sites (10 methods) are covered by static review against the brevo 5.x reference (`reference.md`). No new mocked-SDK tests added (per scope decision).

## 8. Verification steps

1. `composer update getbrevo/brevo-php --with-dependencies` — refresh lockfile.
2. `composer dump-autoload` — rebuild autoload map.
3. `vendor/bin/phpunit` — full suite must pass.
4. `php -l src/Providers/BrevoProvider.php` — lint check.
5. `php -l src/Commands/VerifyCommand.php` — lint check.

## 9. Risks

- **Arg order flips on `removeContactFromList` and `updateEmailCampaign`.** Static analysis catchable. Called out in §5.3.
- **`SendTransacEmailResponse` field access.** 5.x exposes public properties (`$messageId`, `$messageIds`) rather than getters — handled in §5.3. If the field names themselves were to drift in a future 5.x patch, `send()` would fatal — caught by smoke test against live API (manual).
- **Composer transitive constraint conflicts.** Unlikely — brevo 5.x has minimal hard deps. `composer update --with-dependencies` will surface any conflict.

## 10. Out of scope (future work)

- Bump remaining 8 SDKs (resend, mailersend, mailgun, convertkit, aws, mailjet, symfony/http-client 7→8, etc).
- Add mocked-SDK test coverage across all providers.
- Widen version pins to ranges once SDK versions stabilize.

# Provider Verification

`mailbridge:verify` smoke-tests real provider APIs through the same public API your application uses. It is not a unit test — it hits live endpoints.

Use it when setting up a new provider, upgrading an SDK, or confirming credentials work before a release.

## Running the command

```bash
php artisan mailbridge:verify --provider=brevo --email=test@example.com
```

```bash
# From the package repo (no Laravel app required)
./bin/verify --provider=brevo --email=test@example.com
```

## Options

| Option | Description |
| --- | --- |
| `--provider` | Test one provider only. Omit to test all configured providers. |
| `--email` | Recipient for all test sends. Also used as sender fallback. |
| `--auto` | Non-interactive mode. Skips providers with missing config. |
| `--cleanup` | Removes test data after creating it (unsubscribe, delete campaigns). |
| `--dry-run` | Shows what would be tested without calling APIs. |

## What it tests per provider

For each provider it checks:

1. SDK installed and version matches
2. Credentials configured (or prompts interactively)
3. Transactional raw send
4. Transactional template send (if `*_TEST_TEMPLATE_ID` provided)
5. Marketing subscribe, lookup, unsubscribe
6. Campaign create and delete

Skipped if capability not supported by provider or test data missing.

## Interactive vs automatic mode

**Interactive** (default):
- Reads credentials from `.env` / `config/mailbridge.php`
- Prompts for missing API keys inline
- Prompts for optional template IDs and list IDs

**Automatic** (`--auto`):
- Reads credentials from `.env` only
- Skips provider if anything missing
- Zero prompts — good for CI or quick checks

## Environment variables

Set these in `.env` for non-interactive testing:

```dotenv
MAILBRIDGE_VERIFY_EMAIL=test@example.com
BREVO_API_KEY=xxx
BREVO_TEST_TEMPLATE_ID=123
BREVO_TEST_LIST_ID=456
SENDGRID_API_KEY=xxx
SENDGRID_TEST_TEMPLATE_ID=d-xxx
SENDGRID_TEST_LIST_ID=789
```

## Example output

```
brevo
  ✓ SDK installed (2.0.14).
  ✓ Credentials OK.
  ✓ Transactional raw → messageId: abc123
  ✓ Transactional template [123] → messageId: def456
  ✓ Marketing subscribe → subscribe
      metadata: {"list":"456"}
  ✓ Marketing subscriber lookup → found
  ✓ Marketing unsubscribe (cleanup) → unsubscribe
  ✓ Campaign create → id: 789
  ✓ Campaign delete (cleanup) → campaign_delete

sendgrid
  ✓ SDK installed (8.1.11).
  ✗ Missing: api_key
  ✗ Credentials missing — skipped.

Summary

  ✓ brevo: 7 passed, 0 failed, 0 warned
  ✗ sendgrid: 0 passed, 0 failed, 0 warned (skipped)
```

## Important notes

- This command hits **real APIs**. Do not run it in CI as a test.
- Transactional sends deliver real emails to `--email`.
- Subscribers and campaigns are created and optionally cleaned up.
- If `--email` is not set, it falls back to `MAILBRIDGE_VERIFY_EMAIL` in `.env`.
- Sender address for campaigns comes from `config('mailbridge.from.address')` (`MAIL_FROM_ADDRESS`).
- Template and list IDs must exist in the provider account.

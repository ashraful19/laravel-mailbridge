# Provider Guides

Use this page for provider-specific setup, mapping rules, and caveats.  
Core package behavior stays in the main guides.

## SendGrid

- Lanes: transactional + marketing
- Env: `SENDGRID_API_KEY`, `SENDGRID_MARKETING_SENDER_ID` (for campaigns)
- Mapping: marketing lists map to numeric ContactDB list IDs.
- Caveat: campaign sender id is required unless passed with `Campaign::option('sender_id', ...)`.

```php
Campaign::make('Launch')->list('signup')->option('sender_id', 123456);
```

## Amazon SES

- Lanes: transactional
- Env: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`
- Mapping: template IDs map to SES template names.
- Caveat: attachments use raw MIME path.

## Brevo

- Lanes: transactional + marketing
- Env: `BREVO_API_KEY`
- Mapping: marketing lists map to numeric list IDs.
- Caveat: invalid non-numeric list IDs throw `MailbridgeValidationException`.

## MailerSend

- Lanes: transactional
- Env: `MAILERSEND_API_KEY`
- Mapping: template IDs map directly to MailerSend template IDs.

## Resend

- Lanes: transactional
- Env: `RESEND_API_KEY`
- Mapping: supports raw and template payload path through Resend SDK.

## Postmark

- Lanes: transactional
- Env: `POSTMARK_SERVER_TOKEN`
- Mapping: template IDs map to template alias/id.
- Caveat: first tag maps to Postmark tag slot.

## Mailchimp

- Lanes: transactional + marketing
- Env: `MAILCHIMP_API_KEY`, `MAILCHIMP_SERVER_PREFIX`, `MAILCHIMP_AUDIENCE_ID`, `MAILCHIMP_TRANSACTIONAL_KEY`
- Mapping: marketing lists map to audiences; transactional uses separate Mailchimp Transactional product.

## Kit

- Lanes: marketing
- Env: `KIT_API_KEY`
- Mapping: list aliases must be `tag:<id>`, `form:<id>`, `sequence:<id>`, or numeric tag id.
- Caveat: `unsubscribe()` removes tag for `tag:*`; `form:*`/`sequence:*` path performs global unsubscribe.
- Caveat: campaigns map to broadcasts.

```php
'lists' => [
    'signup' => ['kit' => 'tag:123'],
];
```

## MailerLite

- Lanes: marketing
- Env: `MAILERLITE_API_KEY`
- Mapping: list aliases map to group IDs.
- Caveat: `sendCampaign()` is unsupported; schedule/get/delete are supported.

## Mailgun

- Lanes: transactional
- Env: `MAILGUN_API_KEY`, `MAILGUN_DOMAIN`
- Mapping: template sends merge template data + metadata into Mailgun variables payload.

## Mailjet

- Lanes: transactional + marketing
- Env: `MAILJET_API_KEY`, `MAILJET_SECRET_KEY`
- Mapping: marketing list IDs and campaign `ContactsListID` are numeric.
- Caveat: invalid non-numeric list IDs throw `MailbridgeValidationException`.

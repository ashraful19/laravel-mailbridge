# Security

MailBridge must not log API keys, tokens, rendered email bodies, full template data, subscriber PII, or raw provider payloads by default.

Webhook payloads must be verified before normalization when the provider supports signatures or shared secrets.

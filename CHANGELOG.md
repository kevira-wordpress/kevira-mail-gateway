# Changelog

User-facing changes to Kevira Mail Gateway are recorded here.

## [0.1.0] - 2026-08-31

### Added

- Secure interception of WordPress transactional email through the versioned Gateway API.
- HMAC-SHA256 authentication with nonce, timestamp and stable idempotency keys.
- Encrypted, bounded and single-worker outbox for temporary delivery failures.
- Modern Kevira dashboard, health checks, test delivery and retry controls.
- Site Health and WP-CLI operational commands.

### Security

- Secrets are loaded only from a server-side file and never stored in WordPress.
- Attachments, recipients and supported mail headers are validated before delivery.
- Direct PHP/SMTP fallback is intentionally disabled to prevent policy bypass.

### Action required

- Configure the four documented server constants and provision the client secret file before production activation.

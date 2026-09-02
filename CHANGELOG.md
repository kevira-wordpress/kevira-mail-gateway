# Changelog

User-facing changes to Kevira Mail Gateway are recorded here.

## [0.2.0] - 2026-09-02

### Changed

- Adopted the canonical Gateway v1 request and response format, including the Gateway `id` field and structured error codes.
- Limited each message to 10 recipients, 200 Unicode subject characters and a 128 KiB encoded request.
- Added the required independent `KEVIRA_MAIL_QUEUE_KEY_FILE` encryption key for queued message bodies.
- Made failed-message retries bounded and queue claims atomic, and extended the renewable worker lock.

### Security

- Key files must be root-owned, regular, non-symlink, non-writable files outside WordPress, uploads and the plugin directory.
- Attachments are rejected explicitly without reading or transmitting the referenced files.
- Destructive CLI purges now require confirmation, and the 500-row cap includes failed records.
- Gateway errors are classified without logging response bodies, signatures, recipients or message content.

### Action required

- Before upgrading, define `KEVIRA_MAIL_QUEUE_KEY_FILE` with a protected 32-byte, 64-character hexadecimal or base64-encoded 32-byte key.
- Keep the existing HMAC secret unchanged while old `0.1.0` queue records drain. Rotate either key only after the queue is empty.
- Confirm that the Gateway accepts the strict v1 payload and returns `{ "id": "...", "status": "queued" }` for both HTTP 202 and idempotent HTTP 200 responses.

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

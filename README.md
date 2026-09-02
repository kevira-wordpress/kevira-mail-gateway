# Kevira Mail Gateway

Production WordPress client for the separately operated Kevira Mail Gateway service. The plugin short-circuits `wp_mail()`, authenticates exact request bodies with HMAC-SHA256, and maintains a bounded independently encrypted outbox for transient failures.

## Requirements

- WordPress 6.9+ (tested up to 7.1)
- PHP 8.1+
- HTTPS Gateway implementing the documented `/v1/messages` contract
- A protected HMAC secret file and a separate protected 32-byte queue-key file

## Server configuration

Define these constants outside the WordPress database:

```php
define( 'KEVIRA_MAIL_GATEWAY_URL', 'https://mail-gateway.example.com' );
define( 'KEVIRA_MAIL_CLIENT_ID', 'site-production' );
define( 'KEVIRA_MAIL_SECRET_FILE', '/run/secrets/kevira_mail_gateway_token' );
define( 'KEVIRA_MAIL_QUEUE_KEY_FILE', '/run/secrets/kevira_mail_queue_key' );
define( 'KEVIRA_MAIL_SENDER_PROFILE', 'transactional' );
```

The client ID must contain 3–64 lowercase characters from `[a-z0-9_-]`. The HMAC secret must contain 32–4096 bytes. The queue key must be exactly 32 raw bytes, 64 hexadecimal characters, or strict base64 that decodes to 32 bytes.

Both files must be root-owned, regular, non-symlink, readable files that are not group-writable or world-writable. They must remain outside `ABSPATH`, `WP_CONTENT_DIR`, uploads and the plugin directory. Their contents are never stored or displayed by WordPress. Missing or invalid configuration disables delivery safely and never falls back to PHP mail or SMTP.

## Gateway v1 behavior

- Messages are sent to `POST /v1/messages`; health uses `GET /v1/health`.
- HTTP 202 is a newly queued message and HTTP 200 is an idempotent duplicate; both must return an `id` and `status: queued`.
- Retries preserve the exact JSON body and idempotency key while generating a fresh timestamp, nonce and signature.
- Attachments are unsupported. A mail containing attachments fails through `wp_mail_failed` before any attachment file is read.
- Gateway availability is required. There is no native PHP mail, SMTP or provider fallback.

## Upgrading from 0.1.0

Provision `KEVIRA_MAIL_QUEUE_KEY_FILE` before activating 0.2.0. Version 0.2.0 encrypts every new queue row with that independent key. Existing 0.1.0 rows can be decrypted temporarily using the unchanged HMAC secret and are never silently removed. Drain the queue before rotating either the old HMAC secret or the queue key; rows that cannot be authenticated remain failed and are reported as `payload_decryption_failed`.

See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for deployment and health-verification steps and [docs/gateway-contract.md](docs/gateway-contract.md) for the exact signed contract.

## Development

Run `composer install` and then `composer check`. Development occurs on `deploy`; production releases are cut from `main` as `mail-gateway-vX.Y.Z` with `kevira-mail-gateway.zip`.

## Naming decision

The original functional specification used “Kevira Mail Client.” The repository and requested product are named “Kevira Mail Gateway”; this plugin remains the WordPress client and never contains the provider-side Gateway.

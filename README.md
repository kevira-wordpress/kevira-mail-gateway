# Kevira Mail Gateway

Production WordPress client for the separately operated Kevira Mail Gateway service. The plugin short-circuits `wp_mail()`, authenticates requests with HMAC-SHA256, and maintains a bounded encrypted outbox for transient failures.

## Requirements

- WordPress 6.9+ (tested up to 7.1)
- PHP 8.1+
- HTTPS Gateway implementing the documented `/v1/messages` contract
- A readable secret file outside the web root

## Server configuration

Define these constants outside the WordPress database:

```php
define( 'KEVIRA_MAIL_GATEWAY_URL', 'https://mail-gateway.example.com' );
define( 'KEVIRA_MAIL_CLIENT_ID', 'site-production' );
define( 'KEVIRA_MAIL_SECRET_FILE', '/run/secrets/kevira_mail_gateway_token' );
define( 'KEVIRA_MAIL_SENDER_PROFILE', 'transactional' );
```

The secret file must contain at least 32 bytes. Its contents are never stored or displayed by WordPress. Missing or invalid configuration disables delivery safely and never falls back to PHP mail or an SMTP plugin.

## Development

Run `composer install` and then `composer check`. Development occurs on `deploy`; production releases are cut from `main` as `mail-gateway-vX.Y.Z` with `kevira-mail-gateway.zip`.

## Naming decision

The original functional specification used “Kevira Mail Client.” The repository and requested product are named “Kevira Mail Gateway”; this plugin remains the WordPress client and never contains the provider-side Gateway.

=== Kevira Mail Gateway ===
Contributors: k1mirani
Tags: email, transactional email, security, gateway, outbox
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later

Securely submits WordPress transactional email to a separate Kevira Mail Gateway and retries temporary failures from an encrypted outbox.

== Description ==

Kevira Mail Gateway replaces direct WordPress email transport with a signed HTTPS request. It validates recipients, headers and attachments, stores retryable messages in a bounded encrypted queue, and exposes safe operational status in Kevira and Site Health.

No mail-provider credentials are stored in WordPress. The plugin does not contain SMTP transport, external telemetry or a silent fallback.

== Installation ==

1. Configure the required server constants and secret file.
2. Install and activate the plugin through Kevira Hub or WordPress.
3. Open Kevira > Mail Gateway and verify connection health.
4. Send a test message.

== Changelog ==

= 0.1.0 =
* Initial secure client, signed delivery, encrypted outbox, admin dashboard, Site Health and WP-CLI support.

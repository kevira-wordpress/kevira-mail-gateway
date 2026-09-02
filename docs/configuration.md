# Configuration

The plugin requires `KEVIRA_MAIL_GATEWAY_URL`, `KEVIRA_MAIL_CLIENT_ID`, `KEVIRA_MAIL_SECRET_FILE`, `KEVIRA_MAIL_QUEUE_KEY_FILE` and `KEVIRA_MAIL_SENDER_PROFILE`. They must be defined outside the database. The default HMAC secret path is `/run/secrets/kevira_mail_gateway_token`; the queue-key path has no default and is always explicit.

Production URLs must use HTTPS and cannot contain credentials, query strings or fragments. Client IDs must match `[a-z0-9_-]{3,64}`. The HMAC secret must contain 32–4096 bytes. The independent queue key must be 32 raw bytes, 64 hexadecimal characters, or strict base64 decoding to 32 bytes.

Both key files must be root-owned regular files, not symlinks, readable by PHP, and not group-writable or world-writable. They must be provisioned outside `ABSPATH`, `WP_CONTENT_DIR`, uploads and the plugin directory, with only the PHP runtime group granted read access when required.

The UI is intentionally read-only. If configuration is missing, `wp_mail()` returns failure and fires `wp_mail_failed`; it never falls back to another transport.

# Deployment

## Required server configuration

Define all five constants outside WordPress-managed settings:

```php
define( 'KEVIRA_MAIL_GATEWAY_URL', 'https://mail-gateway.example.com' );
define( 'KEVIRA_MAIL_CLIENT_ID', 'site-production' );
define( 'KEVIRA_MAIL_SECRET_FILE', '/run/secrets/kevira_mail_gateway_token' );
define( 'KEVIRA_MAIL_QUEUE_KEY_FILE', '/run/secrets/kevira_mail_queue_key' );
define( 'KEVIRA_MAIL_SENDER_PROFILE', 'transactional' );
```

Create the HMAC secret and queue key as separate root-owned files. The queue key may be generated as 32 random bytes, 64 hexadecimal characters, or strict base64 encoding of 32 random bytes. Do not put either value in `wp-config.php`, the database, uploads, `WP_CONTENT_DIR`, `ABSPATH` or the plugin directory. Use a regular non-symlink file that is not writable by group or other users, with narrowly scoped runtime read permission.

## Upgrade from 0.1.0

1. Inspect `wp kevira-mail status` and drain the current queue where possible.
2. Keep the existing HMAC secret in place.
3. Provision `KEVIRA_MAIL_QUEUE_KEY_FILE` before activating 0.2.0.
4. Activate 0.2.0 and process any legacy records. They use a migration-only v1 decryption path and are re-sent with the unchanged body and idempotency key.
5. Confirm the queue is empty before rotating either key. This release does not implement a current-plus-previous keyring.

Records that cannot be decrypted are retained as failed with `payload_decryption_failed`; they are never silently destroyed.

## Safe verification

Open **Kevira → Mail Gateway** or run:

```text
wp kevira-mail status
```

The health probe calls only `GET /v1/health` and does not send authentication material. The output contains configuration state, HTTP health and aggregate queue counts only. It must not include request bodies, recipient addresses, nonces, signatures or key material.

After health succeeds, send one non-sensitive test message from the dashboard or with `wp kevira-mail test --to=controlled@example.com`. Gateway availability is mandatory: there is no PHP mail or SMTP fallback.

## Gateway v1 limitations

- Maximum 10 recipients across To, Cc and Bcc.
- Maximum 200 Unicode characters in the subject.
- Maximum 128 KiB for the complete encoded JSON request.
- UTF-8 only.
- Attachments are unsupported and cause `wp_mail_failed`.

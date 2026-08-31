# Configuration

The plugin requires `KEVIRA_MAIL_GATEWAY_URL`, `KEVIRA_MAIL_CLIENT_ID`, `KEVIRA_MAIL_SECRET_FILE` and `KEVIRA_MAIL_SENDER_PROFILE`. They must be defined outside the database. The default secret path is `/run/secrets/kevira_mail_gateway_token`.

Production URLs must use HTTPS and cannot contain credentials, query strings or fragments. The secret file must be readable by PHP, contain 32–4096 bytes, and remain outside the uploads and plugin directories.

The UI is intentionally read-only. If configuration is missing, `wp_mail()` returns failure and fires `wp_mail_failed`; it never falls back to another transport.

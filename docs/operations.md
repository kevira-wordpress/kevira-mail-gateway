# Operations

The Kevira > Mail Gateway screen reports configuration health, queue counts and recent accepted/failure timestamps. It can send a test email, retry failed records and refresh health. All operations require `manage_options` and a valid nonce.

CLI commands:

```text
wp kevira-mail status
wp kevira-mail test --to=user@example.com
wp kevira-mail process
wp kevira-mail queue list
wp kevira-mail queue retry
wp kevira-mail queue purge-failed
```

Queue output contains only record ID, state, attempt count, next availability and sanitized error code. It never prints recipients or message content.

The first production rollout target is `fardacafe-shop` at `fardaroastery.ir`, only after the server-side Gateway and its per-site secret are provisioned. This repository does not perform that rollout.

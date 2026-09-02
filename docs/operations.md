# Operations

The Kevira > Mail Gateway screen reports configuration health, queue counts and recent accepted/failure timestamps. It can send a test email, retry failed records and refresh health. All operations require `manage_options` and a valid nonce.

CLI commands:

```text
wp kevira-mail status
wp kevira-mail test --to=user@example.com
wp kevira-mail process
wp kevira-mail queue list
wp kevira-mail queue retry --limit=50
wp kevira-mail queue purge-failed
wp kevira-mail queue purge-failed --yes
```

Queue output contains only record ID, state, attempt count, next availability and sanitized error code. It never prints recipients or message content.

At most 50 failed records can be returned to the queue per admin or CLI request. `purge-failed` prompts for confirmation unless the operator explicitly supplies `--yes`. The outbox holds at most 500 total rows, including failed rows, and expired failed rows are retained for no more than seven days.

A `payload_decryption_failed` record means its authenticated queue payload could not be opened, normally because a required old key was rotated too early or the record was corrupted. It remains visible as failed and is not silently deleted. Drain the queue before rotating keys.

The first production rollout target is `fardacafe-shop` at `fardaroastery.ir`, only after the server-side Gateway and its per-site secret are provisioned. This repository does not perform that rollout.

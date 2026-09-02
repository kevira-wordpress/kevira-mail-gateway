# Gateway contract v1

## Submit a message

The client sends `POST /v1/messages` with the exact deterministic JSON body and these headers:

- `Content-Type: application/json`
- `X-Kevira-Client-Id`
- `X-Kevira-Timestamp`
- `X-Kevira-Nonce`
- `X-Kevira-Signature`
- `Idempotency-Key`

The signature is `v1=` followed by lowercase HMAC-SHA256 of:

```text
POST
/v1/messages
{timestamp}
{nonce}
{idempotency-key}
{sha256-of-exact-body}
```

Lines are separated by one LF byte and the canonical string has no trailing newline. The HMAC secret comes only from `KEVIRA_MAIL_SECRET_FILE`.

The JSON object contains only:

```json
{
  "sender_profile": "transactional",
  "recipients": {
    "to": ["recipient@example.com"],
    "cc": [],
    "bcc": []
  },
  "reply_to": "reply@example.com",
  "subject": "Subject",
  "text": "Plain text body",
  "html": "<p>HTML body</p>",
  "charset": "UTF-8"
}
```

The combined recipient limit is 10, the Unicode subject limit is 200 characters, and the encoded request limit is 128 KiB. `reply_to` is one valid email or `null`. At least one of `text` or `html` must contain content. Caller metadata, caller-controlled From identities and attachments are never sent. The Gateway maps `sender_profile` to the actual sender.

HTTP 202 represents a newly queued message and HTTP 200 represents an idempotent duplicate. Both return:

```json
{"id":"gateway-message-uuid","status":"queued"}
```

Malformed or duplicate-key success JSON is rejected. The returned `id` is stored as the latest Gateway message identifier.

Structured failures use an `error.code` plus an optional `request_id`. The client recognizes `nonce_replay`, `idempotency_conflict`, `idempotency_in_progress`, `rate_limited`, `daily_quota_exceeded`, `invalid_signature`, `stale_timestamp`, `invalid_payload` and `sender_profile_not_allowed` without exposing the body. A 409 is not assumed to be nonce replay.

Network errors, 408, 425, 429, 5xx, rate limits, daily quota exhaustion, idempotency-in-progress and nonce replay are retryable message outcomes. A retried nonce-replay message uses a fresh nonce while preserving its exact body and idempotency key. Idempotency conflicts and the remaining documented validation/authentication errors are permanent.

## Health and client status

The unauthenticated health endpoint is `GET /v1/health`. A future signed `GET /v1/client/status` adapter is exposed through `kevira_mail_gateway_client_status_v1`; the plugin does not invent a canonical signing format until the server contract is confirmed.

Retries must preserve the exact request body and idempotency key. Timestamp, nonce and signature are regenerated for each HTTP attempt.

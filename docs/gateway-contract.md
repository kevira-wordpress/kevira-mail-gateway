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

HTTP 200 and 202 mean accepted. Network errors, 429 and 5xx are transient. Other 4xx responses are permanent, including 400, 401, 403, 409 and 413.

## Health and client status

The unauthenticated health endpoint is `GET /v1/health`. A future signed `GET /v1/client/status` adapter is exposed through `kevira_mail_gateway_client_status_v1`; the plugin does not invent a canonical signing format until the server contract is confirmed.

Retries must preserve the exact request body and idempotency key. Timestamp, nonce and signature are regenerated for each HTTP attempt.

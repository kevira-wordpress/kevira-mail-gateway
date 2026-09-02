# Architecture

`wp_mail()` is intercepted with the supported `pre_wp_mail` filter. A message factory normalizes recipients, supported headers and content into one deterministic JSON body. Attachments are rejected before files are read. The signer authenticates that exact body and the Gateway response is classified as accepted, transient or permanent.

Transient failures are encrypted with the independent queue key before database storage. New rows use the v2 encryption envelope; the unchanged HMAC secret can decrypt legacy v1 rows only during the 0.1.0 upgrade drain. A renewable 150-second lock protects the worker, and each row is claimed with a conditional pending-to-processing state transition. The worker reuses the original JSON body and idempotency key, generates a new nonce and timestamp for every request, and applies exponential backoff with jitter for at most five attempts.

Accepted rows are deleted immediately. Permanent and exhausted failures remain visible for seven days. The queue is capped at 500 rows and a batch is limited to five rows. Action Scheduler is used when available; otherwise a single WP-Cron event is scheduled.

The plugin intentionally does not implement SMTP, provider APIs, secret editing, arbitrary outgoing headers, remote code loading, telemetry, or a fallback transport.

# Architecture

`wp_mail()` is intercepted with the supported `pre_wp_mail` filter. A message factory normalizes recipients, supported headers, content and attachments into one deterministic JSON body. The signer authenticates that exact body and the Gateway response is classified as accepted, transient or permanent.

Transient failures are encrypted before database storage. A single expiring lock protects the worker. The worker reuses the original JSON body and idempotency key, generates a new nonce and timestamp for every request, and applies exponential backoff with jitter for at most five attempts.

Accepted rows are deleted immediately. Permanent and exhausted failures remain visible for seven days. The queue is capped at 500 rows and a batch is limited to five rows. Action Scheduler is used when available; otherwise a single WP-Cron event is scheduled.

Attachments are accepted only from the site's uploads directory or WordPress temporary directory. Paths are resolved before use, and the server secret path is never an allowed attachment root.

The plugin intentionally does not implement SMTP, provider APIs, secret editing, arbitrary outgoing headers, remote code loading, telemetry, or a fallback transport.

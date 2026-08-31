# Security policy

Report suspected vulnerabilities privately to the repository owner. Do not include production secrets, message bodies, recipient lists or signed headers in an issue.

## Security boundaries

- WordPress holds only the client identifier, Gateway URL and sender-profile name as server constants. The HMAC secret is read from a file outside the web root.
- The plugin never connects directly to an SMTP server or mail provider.
- The Gateway URL is validated, fixed by server configuration and restricted to versioned paths. TLS verification and redirect blocking remain enabled.
- Queue payloads use authenticated encryption with a derived, context-specific key.
- Operational screens and CLI output exclude message content, recipients, secrets and signatures.

Activation and deactivation retain queued data. Uninstall also retains data unless `KEVIRA_MAIL_CLEANUP_ON_UNINSTALL` is explicitly set to boolean `true`.

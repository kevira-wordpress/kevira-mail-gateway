# Security policy

Report suspected vulnerabilities privately to the repository owner. Do not include production secrets, message bodies, recipient lists or signed headers in an issue.

## Security boundaries

- WordPress holds only the client identifier, Gateway URL and sender-profile name as server constants. The HMAC secret and independent queue encryption key are read from protected root-owned files outside WordPress and are never stored in the database.
- The plugin never connects directly to an SMTP server or mail provider.
- The Gateway URL is validated, fixed by server configuration and restricted to versioned paths. TLS verification and redirect blocking remain enabled.
- Queue payloads use authenticated encryption with a dedicated 32-byte key. The HMAC secret is used only for request signing and temporary decryption of legacy 0.1.0 rows during upgrade.
- Attachments are unsupported and fail closed before the referenced file is read.
- Operational screens and CLI output exclude message content, recipients, secrets and signatures.

Activation and deactivation retain queued data. Uninstall also retains data unless `KEVIRA_MAIL_CLEANUP_ON_UNINSTALL` is explicitly set to boolean `true`.

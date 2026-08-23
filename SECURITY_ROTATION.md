# Security rotation checklist

This is an operational checklist. Do not paste secret values into this file, tickets, logs, screenshots, or chat.

## Scope

Rotate any value that was present in the inspected archive or a copy of it:

- APP_KEY
- database username/password and connection credentials
- mail credentials
- CRON_SECRET
- Jitsi application ID/secret and recording-service credentials
- Cloudflare Turnstile secret
- blob/storage read-write tokens
- payment/provider keys and webhook signing secrets
- any administrator password that was stored in, or used with, the archive

## Safe sequence

1. Inventory active deployments, queues, cron jobs, storage providers, payment webhooks, and Jitsi services.
2. Create replacement credentials in each provider without printing them.
3. Update the approved secret manager and deployment environment.
4. Deploy and verify health, login, payment review, receipt delivery, recording, and email paths.
5. Revoke the old credentials and invalidate old webhook signing secrets.
6. Review provider access logs for unexpected use of the old values.
7. Preserve only an encrypted, access-controlled evidence copy if retention is required; otherwise securely remove the archive and local copies.

The current local archive was not deleted automatically. Its scan found two environment files, a SQLite database, a Laravel log, and private uploaded files. Treat it as sensitive until the rotation and retention steps are completed.

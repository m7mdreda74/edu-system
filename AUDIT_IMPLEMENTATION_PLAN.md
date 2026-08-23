# Altafawwuq audit implementation plan

Status date: 2026-08-23

## Completed in the codebase

- Arabic validation, authentication, password, HTTP, and unexpected-exception responses are localized without exposing internal exception text.
- Security headers are applied centrally. Inline theme and Ziggy scripts receive the request CSP nonce; script-src does not use unsafe-inline.
- Audit events are written for sensitive mutations. Production now fails closed if the audit_events table is unavailable.
- Vodafone Cash receipts use MIME and size validation, SHA-256 duplicate detection, teacher/commission snapshots, transactional subscription locking, and a unique idempotency key.
- A read-only payment reconciliation command checks paid payment splits, teacher snapshots, commission snapshots, and invoices.
- Tracked-file and release-archive scans detect environment files, SQLite databases, logs, private uploads, and Git metadata.

## Remaining before production approval

1. Rotate every secret that was ever present in the local archive or its copied evidence, then remove or quarantine the archive according to the approved retention policy.
2. Apply all pending migrations to staging first, then production with a verified backup:
   - 2026_08_22_000100_create_audit_events_table
   - 2026_08_22_000200_add_payment_integrity_fields
   - 2026_08_22_000300_add_current_query_indexes
   - 2026_08_23_000100_add_payment_idempotency_key
   - 2026_08_23_000200_add_payment_query_indexes
3. Run the real-domain Chromium smoke matrix and record CSP violations, auth/RBAC results, payment retry behavior, live-room behavior, and responsive/accessibility findings.
4. Run the production-readiness, payment-reconciliation, query-plan, backup-restore, and secret-scan checks using an approved operator.

No production database or deployment was changed by this local implementation pass.

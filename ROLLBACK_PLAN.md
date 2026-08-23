# Altafawwuq audit rollback plan

Rollback requires an approved release owner and a verified database backup. Never use migrate:fresh, delete payments, delete receipts, or delete audit events as a rollback mechanism.

## Application rollback

1. Put the deployment in the approved maintenance or read-only mode.
2. Record the current release, migration output, queue state, and backup identifier.
3. Revert the application release to the last compatible version.
4. Keep the idempotency-aware version running until all in-flight checkout requests have settled, unless the release owner explicitly accepts retry risk.

## Schema rollback

Only if the previous application does not understand these columns, and only after confirming no new release traffic is using them, roll back in reverse order:

1. 2026_08_23_000200_add_payment_query_indexes
2. 2026_08_23_000100_add_payment_idempotency_key
3. 2026_08_22_000300_add_current_query_indexes
4. 2026_08_22_000200_add_payment_integrity_fields
5. 2026_08_22_000100_create_audit_events_table

Run the rollback against staging first, restore a copy from backup, and verify payment counts, receipt references, subscriptions, invoices, and audit evidence before any production action.

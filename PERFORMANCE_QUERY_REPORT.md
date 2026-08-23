# Performance query report

Status date: 2026-08-23

The repository contains indexes for the high-volume payment, report, search, and scheduling paths, including payment status/date, user/status, gateway reference, payment payout/status access, and the current payment owner snapshot.

The migration 2026_08_23_000200_add_payment_query_indexes adds:

- status plus created_at for admin pending-payment ordering;
- user_id plus status plus created_at for student payment history;
- teacher_id plus status plus teacher_payout_id plus paid_at for payout selection.

The read-only script scripts/explain-queries.php records the plans without printing credentials or row contents.

## Required production-like verification

Local MySQL verification after applying the new indexes showed no filesort for admin pending payments or student payment history, and the payout query selected the composite teacher/payout/date index. The teacher contains-search still uses a full scan, which is expected for a leading wildcard and should be revisited with full-text or prefix search if the teacher table grows substantially.

Run the following on a staging database with representative row counts and the same database engine/version as production:

1. EXPLAIN the admin pending-payment query filtered by status and ordered by creation date.
2. EXPLAIN the student payment history filtered by user and status.
3. EXPLAIN payout aggregation filtered by paid status and assignment teacher.
4. EXPLAIN search/autocomplete queries with the normal prefix and result limit.
5. Record execution time, examined rows, selected index, and temporary/filesort indicators.

Do not claim the performance item complete from migration presence alone. The query plan evidence must be captured from a production-like dataset.

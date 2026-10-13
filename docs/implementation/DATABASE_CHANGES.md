# Database Changes

Date: 2026-10-13
Migration: `2026_10_13_000100_add_parent_student_link_lifecycle`

## Migration contents

The migration extends `parent_student_links` with:

- `status` (`pending`, `approved`, `rejected`, `revoked`, `expired`), default `pending`.
- Nullable `requested_by_user_id` foreign key to `users`, null-on-delete.
- Nullable `expires_at` and `responded_at` timestamps.
- Lifecycle indexes covering parent/status and student/status lookups.

Existing rows with a non-null `verified_at` are backfilled to `status=approved`. The original `verified_at` column and existing relationship data are preserved for compatibility.

## Verification

`php artisan migrate:status --database=mysql` was run read-only against the local MySQL connection. All old migrations were reported as Ran and this migration as Pending. No `migrate`, `migrate --force`, reset, or destructive schema command was executed.

## Release requirements

Before staging or production, review the additive migration, take/verify a backup, run it through the approved release path, verify indexes and backfill counts, and smoke-test parent request, student approval, rejection, expiry, and revocation. The migration must be applied before relying on lifecycle columns in a deployed build.

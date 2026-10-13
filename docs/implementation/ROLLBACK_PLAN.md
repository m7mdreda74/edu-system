# Rollback Plan

## Application rollback

1. Stop promotion of `feature/lms-dashboard-v2` and retain the previous application release.
2. Roll back application code through the normal host release mechanism.
3. Keep the additive lifecycle columns in place if the previous release ignores them; the original `verified_at` column remains authoritative for compatibility.
4. Disable new parent-link UI/routes at the application release layer if an emergency feature flag or route gate is introduced.

## Database handling

- Do not use `migrate:fresh`, resets, or record deletion.
- Because the migration is additive, application rollback should not require an immediate down migration.
- If a down migration is specifically approved after data review, first back up the lifecycle columns and audit evidence, then use the migration's `down()` through the controlled release process. Never remove columns while a deployed build still reads them.

## Data safety

Approved and revoked relationships remain recoverable through the lifecycle row and audit trail. Preserve those rows during rollback. Review any requests created during the release window before disabling the feature.

# Production Release Plan

The application is not approved for production release yet. Use this sequence after staging evidence is complete.

1. Freeze the reviewed commit and record deployment, PHP/runtime, Vercel, database, queue, cache, and Blob configuration identifiers.
2. Run `php artisan audit:production-readiness` and resolve every failure; do not treat local secrets/artifacts as production evidence.
3. Take a production database backup and verify restore metadata.
4. Confirm the final brand decision and content/settings migration plan.
5. Verify shared cache/locks and queue delivery before enabling traffic.
6. Apply migrations only with explicit approval and confirm every migration status.
7. Deploy the exact commit and run authenticated read-only smoke tests.
8. Run browser/mobile checks for student, teacher, parent, and admin journeys.
9. Monitor errors, queue depth, latency, cache failures, storage failures, and authentication for the agreed observation window.
10. Record the release evidence and close the open-issues table.

No production deployment, migration, credential rotation, or destructive operation was performed during this checkpoint.

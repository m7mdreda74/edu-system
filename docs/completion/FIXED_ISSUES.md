# Fixed Issues in This Checkpoint

## Student assignment deep-link

Assignment cards now carry the authorized worksheet, lesson, and term context. Opening an assignment resolves to the matching lesson and opens its files tab. The server only accepts a worksheet found inside the already authorized, published learning tree; forged or unrelated worksheet IDs fall back safely.

Evidence: `AssignmentsTest` and the full suite pass.

## Teacher group roster payload

The teacher groups index no longer eager-loads and serializes every student into every group card. It now returns aggregate counts, while the roster is loaded through a teacher-owned, searchable, paginated JSON endpoint.

Evidence: `TeacherGroupWorkspaceTest`, the migrated legacy group test, and the full suite pass.

## Teacher group workspace

Each owned group has a dedicated workspace with overview statistics, schedule, students, curriculum, materials, worksheets, live sessions, analytics, and announcements links. A separate roster page consumes the paginated endpoint instead of linking users to raw JSON.

## Navigation gaps

Teacher navigation now exposes gradebook and announcements, and student navigation exposes assignments. Group cards link to their own workspace; the workspace has a real students anchor and roster page.

## Regression contract

The previous test that expected an unbounded `students` array was updated to assert the new summary-plus-paginated-roster contract. This preserves the behavior change as an explicit regression boundary.

## Release preflight coverage

`audit:production-readiness` now fails explicitly when production uses a non-durable queue, lacks an externally confirmed queue worker, uses file/array cache for distributed locks and rate limits, or enables serverless Blob without a store and credential. It reports only presence/status and never prints credential values.

The Vercel deployment configuration now selects the existing shared database cache table instead of an instance-local `/tmp` file cache. Multi-instance lock/rate-limit behavior still requires staging verification.

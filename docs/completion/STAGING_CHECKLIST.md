# Staging Checklist

Run in an approved staging environment before production release.

## Build and data

- [ ] Deploy the exact reviewed commit and record its immutable deployment identifier.
- [ ] Confirm environment variables and secret presence without printing values.
- [ ] Inspect migration status; take a database backup before any approved migration.
- [ ] Run migrations only after explicit release approval, then run the application smoke suite.
- [ ] Confirm queue connection and observe a queued notification from enqueue to delivery.

## Cache and storage

- [ ] Verify shared cache and lock behavior across at least two application instances.
- [ ] Upload a private curriculum file through the real Blob/OIDC path.
- [ ] Confirm persisted retrieval after a new deployment and deny an unrelated user’s retrieval.

## Product flows

- [ ] Student: login, assignments, assignment deep-link, lesson/files, submission, and unauthorized worksheet fallback.
- [ ] Teacher: groups, group workspace, roster search/pagination, gradebook, announcements, materials, worksheets, and live-session links.
- [ ] Parent: linked-child dashboard, authorization boundaries, payments, and notifications.
- [ ] Admin: settings, users, reports, moderation, and audit events.
- [ ] Run browser/mobile QA with screenshots, console, network, RTL, dark mode, and keyboard checks.

## Release gate

- [ ] No P0/P1 blocker remains open.
- [ ] Product owner confirms the final live brand/title.
- [ ] Release owner signs off on production deployment and any migration.

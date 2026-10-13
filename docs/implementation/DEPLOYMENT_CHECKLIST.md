# Deployment Checklist

Status: **NOT READY — BLOCKERS REMAIN**

## Before staging

- Review the feature branch diff and the additive parent-link migration.
- Confirm backup/restore availability for `parent_student_links`.
- Apply the migration only through the approved staging/deployment process; do not run production migrations from this workspace.
- Clear/cache Laravel configuration and routes according to the host release process.
- Build and publish the matching Vite manifest/assets.
- Confirm Vercel Blob private-store/OIDC settings for existing curriculum uploads; verify private upload and retrieval after redeploy.

## Staging smoke journeys

- Parent requests a link with a valid and an unknown phone; confirm generic response and throttling.
- Student approves, rejects, and allows a request to expire; confirm parent data visibility changes only after approval.
- Student opens Continue Learning and Assignment Center with an active subscription.
- Student submits through the existing Learn flow; teacher grades through existing Worksheets; student and verified parent see released feedback.
- Teacher sees pending grading count scoped to owned assignments.
- Exercise existing login, booking, subscription, payment, live room, recorded lesson, protected file, and renewal flows.

## Release gates

- Full test suite reviewed with baseline failures resolved or explicitly accepted.
- PHP GD/test environment parity addressed for image tests.
- Isolated MySQL migration and concurrency checks pass.
- Browser desktop/mobile smoke test passes with no console or failed-network errors.
- Secrets and environment configuration reviewed; no production credential is used locally.
- Explicit production approval obtained. No production deployment is authorized by this branch task.

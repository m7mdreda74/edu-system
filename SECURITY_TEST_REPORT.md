# Security test report

Status date: 2026-08-23

## Local evidence

- Full PHP suite: 261 tests and 1,683 assertions passed.
- Security feature tests: passed, including CSP nonce authorization, security headers, private material authorization, inactive-user session enforcement, and fail-closed audit logging.
- Manual payment feature tests: passed, including Vodafone Cash validation, receipt hashing, duplicate protection, approval/rejection, and idempotency replay.
- Frontend production build: passed with the existing unresolved-at-build-time home hero image warning; Vite leaves that public path to resolve at runtime.
- Local Chromium smoke: 18 checks passed for home, login, and registration at 320, 375, 414, 768, 1024, and 1440 pixels; no app responses, page errors, CSP violations, external failures, or horizontal overflow were observed.
- Tracked-file secret scan: passed without printing values.
- PHP syntax checks: passed for all files changed in this pass.
- Archive artifact scan: intentionally failed for the local archive because it contains two environment files, a SQLite database, a Laravel log, and private uploaded files. This is a required finding, not a test defect.

## Release evidence still required

- Full CI test and frontend build on the commit intended for deployment.
- Chromium checks on the real HTTPS domain at 320, 375, 414, 768, 1024, and 1440 pixels.
- CSP console check on home, login, registration, checkout, live room, YouTube playback, Turnstile, and admin pages.
- RBAC/IDOR checks for every role and protected receipt/material endpoint.
- Backup restore proof, migration output, secret rotation proof, and malware scan of uploaded receipts where required by policy.

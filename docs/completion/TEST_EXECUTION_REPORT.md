# Test Execution Report

**Date:** 2026-10-15

## Results

| Command | Result |
| --- | --- |
| `php artisan test --compact --display-incomplete` | **PASS WITH ENVIRONMENT SKIPS** — 371 passed, 5 skipped, 2,732 assertions |
| Focused assignment/group/preflight tests | **PASS** — 5 tests, 110 assertions |
| `php artisan test tests/Feature/Console/ProductionReadinessCommandTest.php` | **PASS** — 1 test, 8 assertions |
| `npm.cmd run build -- --logLevel warn` | **PASS** — Vite production build |
| Targeted `vendor/bin/pint --test` | **PASS** — focused admin/test files; existing style findings remain in the touched route/config files |
| `composer validate --no-check-publish` | **PASS** |
| `git diff --check` | **PASS** |
| `php artisan migrate:status --database=mysql` | **PASS** — 91 ran, 0 pending locally |
| `php artisan route:list --name=teacher.groups` | **PASS** — 4 routes |
| `node --check qa-playwright/*.js` and no-target guard checks | **PASS** — scripts refuse implicit hosts and credentials |
| Local Playwright read-only smoke | **PASS** — seeded student login, 11 routes, 0 console errors, 0 network failures; upload skipped by mutation guard |
| Local Playwright role smoke | **PASS** — student (7 routes), teacher (15 routes), admin (2 routes), and parent (1 route); all returned HTTP 200 with no console/network issues |
| `php artisan audit:production-readiness` with temporary production process flags | **EXPECTED FAIL** — core queue/cache/schema checks passed; release gates remain for worker confirmation, GD, CRON/Jitsi/Turnstile configuration, and deployment artifacts |
| `php scripts/scan-secrets.php` | **PASS** — no potential secrets in tracked files |
| `php scripts/scan-archive.php` on local release archives | **EXPECTED FAIL** — both local archives contain sensitive SQLite/private/log/environment entries and must not be released |
| `qa-playwright/deployment-smoke.js` syntax and target guard | **PASS** — read-only deployment parity harness refuses an unapproved target |
| Live deployment smoke | **EXPECTED FAIL** — public pages and health returned 200, but deployed autocomplete returned 404; local replay was blocked because MySQL was unavailable on port 3306 |

## Formatting note

Repository-wide Pint was also checked and reports a large pre-existing formatting backlog across unrelated files. It was not auto-fixed because that would create a broad, unrelated change. The files changed in this checkpoint pass targeted Pint.

## Not run

Approved browser/device E2E, queue delivery, shared-cache concurrency, private Blob persistence, and production migration verification were not run or are not available from this workspace. The Playwright harness is now target-gated and mutation-safe by default; the role smoke was run only against the local seeded environment.

The current CLI does not load PHP GD. The five image-upload tests are therefore marked skipped when GD is absent, and production preflight fails explicitly until GD is enabled. This is not treated as a production pass.

The GitHub Actions quality workflow now requests the `gd` extension so its image-upload coverage runs instead of being skipped on CI.

# Test Execution Report

**Date:** 2026-10-14

## Results

| Command | Result |
| --- | --- |
| `php artisan test --compact --display-incomplete` | **PASS WITH ENVIRONMENT SKIPS** — 363 passed, 6 skipped, 2,698 assertions |
| Focused assignment/group tests | **PASS** — 4 tests, 102 assertions |
| `php artisan test tests/Feature/Console/ProductionReadinessCommandTest.php` | **PASS** — 1 test, 7 assertions |
| `npm.cmd run build -- --logLevel warn` | **PASS** — Vite production build |
| Targeted `vendor/bin/pint --test` | **PASS** — changed PHP files |
| `composer validate --no-check-publish` | **PASS** |
| `git diff --check` | **PASS** |
| `php artisan migrate:status --database=mysql` | **PASS** — 91 ran, 0 pending locally |
| `php artisan route:list --name=teacher.groups` | **PASS** — 4 routes |

## Formatting note

Repository-wide Pint was also checked and reports a large pre-existing formatting backlog across unrelated files. It was not auto-fixed because that would create a broad, unrelated change. The files changed in this checkpoint pass targeted Pint.

## Not run

Browser/device E2E, production smoke tests, queue delivery, shared-cache concurrency, private Blob persistence, and production migration verification were not run or are not available from this workspace.

The current CLI does not load PHP GD. The six image-upload tests are therefore marked skipped when GD is absent, and production preflight fails explicitly until GD is enabled. This is not treated as a production pass.

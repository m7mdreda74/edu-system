# Baseline Test Results

Date: 2026-10-13
Branch: `feature/lms-dashboard-v2`
Commit: `08bc29c`

| Command | Result | Evidence / limitation |
| --- | --- | --- |
| `composer validate --no-check-publish` | PASS | `composer.json is valid`. |
| `php artisan route:list --except-vendor` | PASS | 219 application routes listed. |
| `npm.cmd run build` | PASS | Vite 7.3.6, 899 modules transformed, build completed in 11.34s. |
| `php artisan test` | BASELINE FAIL | 283 passed, 15 failed, 1,751 assertions. Failures include missing PHP GD, pre-existing report/fixture count differences, teacher directory fixture mismatch, parent checkout/chat fixture differences, and sensitive-settings expectation mismatch. |
| `vendor\\bin\\pint --test` | BASELINE FAIL | Existing repository-wide formatting drift across many files; this was present before implementation. New files will be formatted/checked separately. |
| `php artisan migrate:status --database=mysql` | PASS (read-only) | Local MySQL is reachable; all existing migrations are Ran and `2026_10_13_000100_add_parent_student_link_lifecycle` is Pending. No migration was executed. |

The baseline failures are not attributed to the LMS implementation. Focused tests added for each change are reported in `TEST_RESULTS.md`, and the full suite is rerun after implementation.

# Regression Report

Date: 2026-10-13
Branch: `feature/lms-dashboard-v2`

## Protected regression areas

- Payment, subscription, booking, live-session, and Blob upload implementations were not rewritten.
- Parent dashboard booking/payment routes remain in place; only link lifecycle and verified-data selection changed.
- Existing `WorksheetController` continues to own teacher worksheet creation and grading.
- Existing `LearnController::submitHomework()` remains the student submission path and uses `updateOrCreate` for retry-safe writes.
- Existing protected download routes are reused by the Assignment Center.

## Verified

- Parent-link approval/security focused suite passes.
- Student dashboard and assignment focused suites pass.
- Teacher Gradebook focused suite passes with teacher ownership filtering.
- Group Announcements focused suite passes with active-subscription and verified-parent recipient filtering.
- Teacher dashboard's pending-grading assertion passes inside the focused Admin suite.
- Frontend production build passes.

## Remaining failures or gates

- The full suite after the announcement checkpoint is 300 passed, 15 failed, 1,965 assertions; the same baseline failures remain, including PHP GD absence and stale fixture/count expectations. They are not silently reclassified as implementation passes.
- Parent portal still has known fixture failures around checkout/chat in the existing suite.
- Local migration is pending and must be verified on isolated MySQL after approval.
- No browser viewport/device smoke test was run, so responsive behavior is code/build-verified but not browser-verified.

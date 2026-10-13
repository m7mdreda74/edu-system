# Regression Report

Date: 2026-10-14
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
- Student Assessment Center focused suite passes with active-subscription and published-unit scoping.
- Teacher Gradebook focused suite passes with teacher ownership filtering.
- Group Announcements focused suite passes with active-subscription and verified-parent recipient filtering.
- Teacher Question Bank focused suite passes with owner/private/shared authorization, Quiz Builder copy, and snapshot persistence coverage.
- Teacher performance analytics focused suite passes with teacher ownership filtering, real roster metrics, trend aggregation, and explainable attention signals.
- Academic Alert focused suite passes with configurable event windows, verified-parent recipient scope, cooldown deduplication, live-reminder coordination, and cron-secret protection.
- Resource Library focused suite passes with local and direct Blob upload completion, normalized metadata, admin approval before cross-teacher reuse, protected student/verified-parent downloads, and Blob resource-kind validation.
- Release-hardening suite passes with seed-aware admin reports/subscription stats, public teacher directory, curriculum skeleton counts, recorded-report totals, parent checkout/support chat, free-intro ordering, and sensitive-admin confirmation coverage.
- Gradebook CSV export passes with teacher assignment ownership enforcement, UTF-8 output, stable columns, and spreadsheet-formula neutralization.
- Gradebook server-side pagination passes with unified worksheet/latest-quiz ordering, page boundaries, full-scope totals, and no cross-teacher rows.
- Weekly parent report focused test passes with real lesson, homework, quiz, attendance, daily, subject, and recent-activity records.
- Teacher dashboard's pending-grading assertion passes inside the focused Admin suite.
- Frontend production build passes.

## Remaining release gates

- The full suite after the P6.5 Gradebook pagination checkpoint is **332 passed, 0 failed, and 2,384 assertions**.
- Local migration is pending and must be verified on isolated MySQL after approval.
- No browser viewport/device smoke test was run, so responsive behavior is code/build-verified but not browser-verified.
- Private Vercel Blob/OIDC upload, persistence, protected retrieval, and redeploy behavior remain deployment gates.

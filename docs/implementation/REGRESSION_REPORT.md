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
- Announcement management passes with owner-only edit/delete, unchanged recipient behavior on edit, scoped CSV export, and spreadsheet-formula neutralization.
- Weekly parent report focused test passes with real lesson, homework, quiz, attendance, daily, subject, and recent-activity records.
- Parent progress comparison focused test passes with current/previous period deltas and explainable attention signals from real authorized records.
- Student dashboard achievement test passes with default-off behavior, active-subscription scoping, and distinct-action scoring.
- Learning Outcomes regression passes with teacher ownership checks, same-unit lesson/quiz targets, and published-student visibility through the authorized Study Room.
- Outcome status regression passes with completed lesson and mastered assessment states derived from real student records.
- Parent outcome-progress regression passes with verified-child scoping, active-subscription filtering, published-unit filtering, and read-only completed/mastered statuses.
- Teacher outcome-analytics regression passes with teacher assignment ownership, active-roster scoping, and aggregated lesson/quiz outcome counts.
- Teacher analytics export regression passes with the same ownership filters, CSV headers/BOM, null metric semantics, and formula neutralization.
- Quiz feedback regression passes with submitted-attempt validation, owning-teacher authorization, student/parent/Gradebook visibility, verified-parent notification scope, and duplicate suppression.
- Queued notification regression passes for all 19 application notification classes, while academic alerts, live reminders, payment notifications, renewal reminders, chat, and quiz feedback remain green.
- Teacher dashboard's pending-grading assertion passes inside the focused Admin suite.
- Frontend production build passes.

## Remaining release gates

- The full suite after the P7.9 queued notification checkpoint is **360 passed, 0 failed, and 2,588 assertions**.
- A queue worker and failed-job monitoring were not run locally and remain release gates.
- Local migration is pending and must be verified on isolated MySQL after approval.
- No browser viewport/device smoke test was run, so responsive behavior is code/build-verified but not browser-verified.
- Private Vercel Blob/OIDC upload, persistence, protected retrieval, and redeploy behavior remain deployment gates.

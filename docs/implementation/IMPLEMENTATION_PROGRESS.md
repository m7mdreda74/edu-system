# Implementation Progress

> **Current audit checkpoint (2026-10-14):** Continue from the current `main` workspace, not the historical branch metadata below. The seven migrations described in the earlier checkpoint now report **Ran** in both local/default and local MySQL status (91 ran, 0 pending). The latest full suite is **363 passed, 6 skipped, 2,698 assertions** because the current CLI lacks GD. Current release blockers are tracked in `docs/completion/OPEN_ISSUES.md`.

## 2026-10-14 - P0-P6.2 + P4.1 + P5.1-P5.3 + P6.3-P6.7 + P7.1-P7.11 local checkpoint

- Created branch `feature/lms-dashboard-v2` from `08bc29c`.
- Audited existing domain models, controllers, routes, protected learning access, dashboards, parent linking, quiz, worksheet, booking, subscription, and Blob storage paths.
- Recorded the baseline in `BASELINE_TEST_RESULTS.md`.
- Confirmed the first security gap: parent phone matching immediately created a verified link.
- Completed locally: additive parent-link lifecycle with pending approval, expiry, rate limiting, audit events, revocation, and IDOR tests.
- Added student Continue Learning from the first authorized incomplete lesson, with explicit lesson-level resume semantics.
- Added teacher pending-grading count backed by owned worksheet submissions.
- Added a student Assignment Center backed by active subscriptions, published homework, lesson progress, and real submissions.
- Added direct student-dashboard navigation to the Assignment Center.
- Added a teacher-scoped read-only Gradebook that combines submitted homework with each student's latest submitted quiz attempt, without double-counting attempts or treating missing work as zero.
- Added parent read-only homework monitoring for verified children, reusing active-subscription assignment statuses without exposing a student submission action.
- Added scoped group announcements: teachers can publish to their active groups, active students receive persistent notifications and dashboard history, and only verified linked parents receive the relevant notification.
- Added a student Assessment Center scoped to published quizzes unlocked by active subscriptions, including availability, attempt limits, in-progress state, and submitted results without exposing question content.
- Added a reusable teacher Question Bank with private/shared visibility, curriculum metadata, ordered answer options, filters, pagination, and Quiz Builder snapshot copying.
- Added explicit teacher ownership and shared-library authorization tests; student quiz payloads continue to omit answer keys.
- Added a parent rolling seven-day progress report scoped to the selected verified child and active subscriptions, using real lesson progress, worksheet submissions, quiz attempts, and live attendance records with daily, subject, and recent-activity summaries.
- Added teacher performance analytics with assignment/group/student/period filters, real roster scoping, attendance and study metrics, homework gaps, quiz history, trend buckets, and explainable attention signals.
- Added configurable Academic Alerts for missing/upcoming homework, upcoming classes, recent absences, declining assessment trends, and subscription expiration, with verified-parent fan-out, recipient cooldown keys, an Artisan command, and a secret-protected cron endpoint.
- Added the Resource Library: teacher-owned searchable resources, normalized tags/categories, local private uploads, direct private Vercel Blob uploads, assignment attachment/reuse, admin approval for shared resources, and protected student/verified-parent access.
- Completed local release hardening: scoped tests away from historical demo-seed assumptions, stabilized checkout/chat/free-intro/report/curriculum assertions, and verified the production sensitive-admin password-confirmation boundary.
- Added a teacher-scoped Gradebook CSV export with assignment ownership enforcement, UTF-8 spreadsheet compatibility, and formula-injection neutralization.
- Added database-level Gradebook pagination over normalized worksheet and latest-quiz rows, with full-scope summary totals and responsive Inertia pagination links.
- Added announcement editing/deletion with owner-only mutation checks, no duplicate notification on edit, and a teacher-scoped CSV export with formula neutralization.
- Added explainable parent progress comparison against the preceding seven-day period, with metric deltas and read-only attention signals based on real authorized records.
- Added optional student achievements behind `LEARNING_GAMIFICATION_ENABLED=false`: scoped non-repeat points, levels, milestones, badges, and private dashboard breakdown with no public leaderboard.
- Added lightweight Learning Outcomes: teacher-owned unit outcomes can target a lesson or electronic assessment, while subscribed students see only published outcomes in the Study Room.
- Added explainable Learning Outcome status derived from real lesson completion and quiz attempts: not started, in progress, completed, or mastered.
- Added a shared Learning Outcome progress service and a read-only parent summary scoped to the selected verified child's active subscriptions and published curriculum.
- Added teacher performance analytics for published outcomes, scoped to the teacher's active assignments and current active roster with aggregated status counts.
- Added teacher analytics CSV export with the same filters, UTF-8 spreadsheet support, null-preserving metrics, and formula neutralization.
- Added owner-scoped quiz teacher feedback for submitted attempts and exposed it consistently in Gradebook, student assessment views, Study Room, parent quiz history, and Gradebook CSV.
- Added owner-scoped quiz feedback notifications for the student and verified linked parents, with duplicate suppression and no notification when feedback is cleared.
- Enforced queued delivery for all 19 application notification classes and added an architectural regression guard for `ShouldQueue` compliance.
- Added per-user notification preferences with default-on behavior, profile UI, strict type/channel validation, and central `NotificationSending` suppression for supported database/mail channels.
- Added the read-only admin analytics dashboard at `admin.analytics`, with cached 12-month revenue, top group sales, lesson completion, three-month first-purchase churn, approved teacher ratings, and peak live-session buckets.
- Focused tests and the production frontend build pass for the new slices.

## Deferred after the local P6.3 checkpoint

- Shared dashboard component extraction beyond the existing reusable cards/layouts.
- External push/SMS and advanced notification scheduling.
- External notification scheduling.
- AI integrations and external data-driven recommendations.
- External announcement delivery and advanced notification scheduling remain follow-up hardening.
- Queue worker uptime, retry, and failed-job monitoring remain deployment gates; no worker was started from this workspace.
- Push/SMS and realtime notification channels remain deferred; only currently implemented channels are exposed in preferences.

## Schema status

The seven migrations listed in the earlier checkpoint were previously pending; the current read-only status now reports all seven as **Ran** on local MySQL. Phase 7.11 adds no migration. No migration has been run against any production or deployment database from this workspace.

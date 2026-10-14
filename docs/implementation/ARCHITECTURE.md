# LMS Architecture Checkpoint

Date: 2026-10-14
Branch: `feature/lms-dashboard-v2`

## Existing boundaries retained

- `app/Domain/*/Models` remains the source of truth for academic, learning, quiz, scheduling, subscription, payment, and user records.
- Controllers remain thin Inertia/HTTP adapters. New orchestration is in application services.
- Existing `SubscriptionService`, `SessionBookingService`, `QuizService`, `WorksheetController`, `ProtectedFileController`, `AuditLogger`, and database notifications are reused.
- Student content is still authorized by active subscription and publication state; protected files continue to re-check authorization server-side.

## New flows

### Parent linking

`ParentDashboardController` asks `ParentStudentLinkService::requestLink()` to create a pending, expiring request. The target student sees only their own pending requests and calls the approve/reject controller. Approved links retain `verified_at`; revocation clears it without deleting history. Parent and student relationships expose only rows with a non-null `verified_at`.

### Student learning

`Student/DashboardController` derives Continue Learning from active subscriptions, published curriculum units, and `LessonProgress`. It intentionally reports lesson-level continuation and does not claim exact video playback resume.

`StudentAssignmentService` derives the Assignment Center from active subscriptions and published homework. It eager-loads assignment metadata, material, and the student's submissions, then derives status without converting a missing submission into a zero.

### Teacher operations

`Teacher/DashboardController` counts ungraded submissions only inside assignments owned by the authenticated teacher. Existing worksheet creation and grading routes remain unchanged.

`TeacherGradebookService` reads only assignments owned by the authenticated teacher, merges submitted homework/paper exams with the latest submitted attempt per student and quiz, and returns derived totals. It does not create or mutate grades; the existing worksheet grading and quiz scoring services remain the mutation authorities.

### Parent monitoring

`ParentDashboardController` first scopes the selected child to a non-null `verified_at` link. Its read-only homework panel then reuses `StudentAssignmentService` for active-subscription/published-homework statuses. Parent booking, payment, and communication mutations remain separate controller/service paths, and no parent route submits work as the child.

`ParentProgressReportService` builds a rolling seven-day, read-only report for that same selected child. It scopes lesson progress, worksheet submissions, quiz attempts, and live-session attendance to active-subscription assignments, then returns summary metrics, daily activity, subject aggregates, and recent events for the existing parent dashboard.

`TeacherPerformanceAnalyticsService` builds a read-only view from the authenticated teacher's active assignments and current roster. It scopes lesson progress, homework, quiz attempts, and live sessions to that teacher, then derives attendance, study, submission, score, trend, and explainable attention metrics without mutating grades or student records.

`AcademicAlertService` evaluates active subscription rosters and confirmed session bookings against configurable windows. It sends only database-backed `AcademicAlertNotification` records, copies student alerts to verified linked parents with a parent-safe link, and stores a stable `alert_key` so repeat cron runs are suppressed per recipient. The existing `LiveSessionReminderService` shares the upcoming-class key to prevent cross-cron duplication.

`ResourceLibraryService` owns teacher resource metadata, tag normalization, local private-file persistence, direct Vercel Blob completion, assignment attachment, search/filter presentation, and administrative share moderation. `LearningResourcePolicy` keeps private resources owner/admin-only, exposes approved shared resources to teachers, and derives student/parent download access from the resource's assignment links plus active subscriptions and verified parent-child links. `ProtectedFileController` re-checks that policy before serving a local file or issuing a short-lived Blob download URL.

`AdminAnalyticsService` builds the administrator's read-only aggregate view from paid payments, subscription periods, lesson progress, approved teacher reviews, and scheduled live sessions. It uses database grouping for the rolling revenue, group sales, ratings, and session buckets, derives the first-purchase/no-renewal churn cohort from subscription periods, and caches the result for 15 minutes. No student-level rows are sent to the page, no snapshot table is introduced, and daily snapshots remain a future scaling option.

## Authorization surfaces

- Student parent-link actions scope the link by `student_user_id` before mutation.
- Parent revocation scopes by `parent_user_id` and verified state.
- Parent dashboard data continues to use verified links only.
- Assignment downloads point to existing protected routes; the browser never receives storage paths.
- Teacher pending-grading counts use `whereHas('worksheet.unit.assignment', teacher_id)`.
- Academic alert parent fan-out is limited to `parent_student_links.verified_at IS NOT NULL`; notification links are selected for the recipient role before persistence.
- Resource downloads never expose stored paths directly; teacher ownership, approved sharing, active subscriptions, and verified parent links are checked server-side on every request.

## Known architectural limits

- The migrations are additive but not applied in this workspace. Resource direct uploads additionally require a connected private Blob store and matching OIDC/token environment on the deployment.
- The gradebook is a teacher-scoped aggregate with CSV export, server-side pagination, owner-scoped quiz feedback, and in-app release notifications. Teacher analytics use a selectable 7/30/90-day window and explainable rules; external push/SMS delivery and deeper parent analytics remain open. Question Bank reuse is now implemented through teacher-owned private/shared records and immutable quiz snapshots.
- All application notifications implement `ShouldQueue`; the configured queue worker is required for database/mail delivery, with retry and failed-job monitoring handled by the deployment process.
- Notification preferences are default-on and enforced centrally at `NotificationSending`; push/SMS and realtime channels are intentionally not exposed until their delivery infrastructure exists.
- Admin analytics is intentionally read-only and aggregate-only; its 15-minute cache is suitable for operational reporting, while historical daily snapshots and external BI exports remain deferred.
- Resource metadata editing and remote Blob orphan cleanup after an abandoned direct upload are follow-up hardening items; local deletion is handled immediately and protected retrieval remains fail-closed.
- Browser/mobile E2E and production-like MySQL transaction/concurrency verification are still release gates.

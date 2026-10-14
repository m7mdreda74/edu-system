# Final Handover

Status: **NOT READY — BLOCKERS REMAIN**

Date: 2026-10-14
Branch: `feature/lms-dashboard-v2`
Base commit: `08bc29c`

## Implemented in this checkpoint

- Parent/student link lifecycle: pending request, student approval/rejection, expiry, revocation, generic enumeration-safe response, rate limiting, idempotent retry, audit events, notifications, and IDOR coverage.
- Student Continue Learning from real active-subscription progress.
- Student Assignment Center with active-subscription/published-homework scoping, actual submission/grade metadata, protected file links, status filters, and empty state.
- Teacher pending-grading dashboard metric scoped to the teacher's own assignments.
- Teacher read-only Gradebook combining submitted homework/paper exams and the latest submitted quiz attempt per student, with teacher ownership filtering.
- Parent read-only homework monitoring for verified children, including pending/late/in-progress/submitted/graded statuses derived from active subscriptions.
- Direct Assignment Center navigation from the student dashboard.
- Student Assessment Center with active-subscription/published-quiz scoping, availability windows, attempt state, result history, and no question/answer-key leakage.
- Teacher Question Bank with private/shared reusable questions, curriculum metadata, ordered options, owner/shared-library permissions, filtering, pagination, and Quiz Builder snapshot copying.
- Teacher-owned group announcements with persistent records, student dashboard history, active-student notifications, and verified-parent notifications.
- Teacher performance analytics with real roster-scoped attendance, lesson progress, homework gaps, quiz history, period trends, and explainable attention signals.
- Academic Alerts with configurable homework/class/absence/assessment/subscription rules, verified-parent recipient scoping, recipient-level deduplication, and protected Artisan/Vercel cron triggers.
- Educational Resource Library with teacher-owned searchable resources, normalized tags/categories, local private uploads, direct private Vercel Blob uploads, assignment attachment/reuse, admin share approval, and protected student/verified-parent downloads.
- Parent rolling seven-day progress report with real lesson, homework, quiz, and live-attendance records, daily/subject summaries, recent activity, and verified-child/active-subscription scoping.
- Local release hardening with deterministic seed-aware regression assertions and sensitive-admin password-confirmation coverage.
- Teacher-scoped Gradebook CSV export with assignment filtering, UTF-8 spreadsheet compatibility, and formula-injection neutralization.
- Database-level Gradebook pagination over worksheet submissions and latest submitted quiz attempts, with full-scope summary totals and ownership filtering.
- Announcement management with owner-only editing/deletion, no duplicate notifications on edit, and teacher-scoped CSV export with spreadsheet-formula neutralization.
- Parent progress comparison with current/previous seven-day metric deltas and explainable read-only attention signals.
- Optional student achievements behind `LEARNING_GAMIFICATION_ENABLED=false`, with active-subscription-scoped non-repeat points, levels, milestones, badges, and no public leaderboard.
- Lightweight Learning Outcomes in the curriculum builder, with lesson/assessment targets, teacher ownership checks, and published authorized display in the student Study Room.
- Explainable Learning Outcome status in the Study Room, derived from actual lesson completion and submitted quiz attempts without predictive or manually stored grades.
- Shared Learning Outcome progress service plus a read-only verified-parent summary for published outcomes in the selected child's active curriculum, including completed/mastered counts and progress percentage.
- Teacher Performance Analytics now includes read-only published-outcome progress aggregates for the teacher's active assignments and current roster.
- Teacher Performance Analytics includes a filtered CSV export with stable columns, UTF-8 BOM, null-preserving metrics, and formula neutralization.
- Submitted quiz attempts now support owner-scoped teacher feedback, visible in Gradebook, student assessments/Study Room, parent quiz history, and Gradebook CSV without changing scores.
- Publishing new quiz feedback sends one in-app notification to the student and verified linked parents; repeated saves and clearing feedback are deduplicated.
- All 19 application notification classes now implement `ShouldQueue`, preserving their existing channels and payloads while moving delivery behind Laravel Queue.
- Additive lifecycle migration and implementation documentation.

## Reused existing functionality

Existing subscription/access rules, protected file controller, Learn submission flow, Worksheet teacher grading, quiz engine, notifications, audit logger, payment, booking, live-session, and Blob upload paths were preserved.

## Deferred

External push/SMS, AI, browser E2E, and full shared-component extraction require separate checkpoints and additional business-rule verification. Existing worksheet grading, quiz submission/grading, and owner-scoped quiz feedback are the mutation workflows; teacher analytics, academic alerts, parent homework monitoring, the weekly report/comparison, achievements, Learning Outcomes display, the Assessment Center, and resource-library metadata remain read-only after creation.

## Changed areas

- Backend: parent-link service/model/controllers, `ParentProgressReportService`, `TeacherPerformanceAnalyticsService`, `AcademicAlertService`, `AcademicAlertNotification`, `ResourceLibraryService`, `LearningResource`, `LearningResourcePolicy`, resource controllers, student dashboard/assignment/assessment services and controllers, teacher analytics/dashboard/question bank/quiz builder, cron routes, Blob upload support, config, and additive migrations.
- Frontend: student/parent/teacher dashboards, the parent weekly-report panel, the optional student achievements panel, curriculum-builder outcome controls, Study Room outcome display, `Teacher/PerformanceAnalytics.vue`, `Student/Assignments.vue`, `Student/Assessments.vue`, `Teacher/QuestionBank.vue`, `Teacher/QuizBuilder.vue`, `Teacher/ResourceLibrary.vue`, `Admin/ResourceLibrary.vue`, and `Student/Resources.vue`.
- Tests: parent-link security, student dashboard/achievements, assignment center, assessment center, weekly parent report, teacher performance analytics, announcements, Question Bank ownership/snapshot coverage, resource-library upload/approval/access coverage, Blob-kind validation, and admin dashboard expectation.

## Routes and schema

- New route: `GET /teacher/gradebook` (`teacher.gradebook`).
- New route: `GET /teacher/gradebook/export` (`teacher.gradebook.export`) for the protected CSV download.
- New announcement routes: `teacher.announcements.update`, `teacher.announcements.destroy`, and `teacher.announcements.export`; edits keep the original group and do not resend notifications.
- Gradebook page results use a database-level worksheet/quiz union and return paginated `data`/`links`; CSV export intentionally remains an all-scoped-record download.
- New application service/controller/page: `TeacherGradebookService`, `Teacher/GradebookController`, and `Teacher/Gradebook.vue`.
- New route: `GET /my-assessments` (`student.assessments`) with `StudentAssessmentService`, `Student/AssessmentController`, and `Student/Assessments.vue`.
- New announcement service/controller/page: `GroupAnnouncementService`, `Teacher/AnnouncementController`, and `Teacher/Announcements.vue`; student dashboard history is read-only.
- New Question Bank routes: `teacher.question-bank`, its store/update/delete routes, and `teacher.quizzes.questions.from-bank`; Quiz Builder now receives eligible bank questions.
- The existing parent dashboard now includes a read-only rolling seven-day report; no new route or migration was required.
- New teacher route: `GET /teacher/analytics` (`teacher.analytics`) with `PerformanceAnalyticsController`, `TeacherPerformanceAnalyticsService`, and a read-only Vue dashboard; no migration was required.
- New academic alert triggers: `GET /api/cron/academic-alerts` (`cron.academic-alerts`) and `academic-alerts:send`; no migration was required, and `config/learning.php` exposes the alert windows/cooldown through environment variables.
- New resource routes include `teacher.resource-library`, `admin.resource-library`, `student.resources`, and the protected `learning.resource.download` route. Direct upload completion is scoped through `CurriculumBlobUpload::KIND_RESOURCE` and `/api/blob-upload`.
- New Learning Outcomes routes: `teacher.outcomes.store`, `teacher.outcomes.update`, and `teacher.outcomes.destroy`; student rendering remains inside the authorized `student.learn` Study Room response.
- New teacher analytics route: `teacher.analytics.export`; it reuses the existing analytics filters and emits a protected teacher-scoped CSV.
- New teacher feedback route: `teacher.gradebook.quiz-feedback`; only the owning teacher can update submitted quiz feedback.
- Quiz feedback release notifications reuse `GenericDatabaseNotification` and are scoped to the attempt's student plus verified `parent_student_links`.
- Queue worker configuration, retry policy, and failed-job monitoring are deployment requirements for notification delivery.
- New additive migrations: `2026_10_13_000200_create_group_announcements_table`, `2026_10_13_000300_create_question_bank_tables`, `2026_10_13_000400_create_learning_resource_library_tables`, `2026_10_14_000100_create_learning_outcomes_table`, and `2026_10_14_000200_add_teacher_feedback_to_quiz_attempts_table`. The earlier parent-link migration `2026_10_13_000100_add_parent_student_link_lifecycle` also remains Pending until controlled release.

## Verification

- Focused parent-link, student dashboard, assignment, assessment, Gradebook pagination/CSV export, announcement management, and Question Bank tests pass.
- The focused parent report/comparison/outcome tests pass with 3 tests and 85 assertions; the full Parent Portal file passes with 13 tests and 208 assertions.
- The focused student dashboard/achievement tests pass with 3 tests and 68 assertions; the achievement flag is disabled by default.
- The full Curriculum Builder slice passes with 14 tests and 121 assertions, including Learning Outcomes ownership, student visibility, and outcome status.
- The focused teacher performance analytics/outcome test passes with 1 test and 65 assertions; its regression slice with assessment, gradebook, announcement, and Question Bank tests remains covered.
- The focused Academic Alert test passes with 4 tests and 22 assertions; the live-reminder, subscription-renewal, and admin-notification regression slice passes with 16 tests and 73 assertions.
- The focused Resource Library feature/unit slice passes with 35 tests and 114 assertions; its combined regression slice with Academic Alerts, live reminders, renewal reminders, admin notifications, analytics, assessment, gradebook, announcements, and Question Bank passes with 62 tests and 434 assertions.
- Vite production build passes with 908 modules transformed.
- PHP syntax and diff checks pass.
- The release-hardening regression slice passes with 99 tests and 961 assertions, including deterministic admin-report/subscription, directory, curriculum, recorded-report, parent checkout/chat, free-intro, and sensitive-admin checks.
- Full suite completed with **360 passed, 0 failed, and 2,588 assertions**; no new failure appeared in Resource Library, Blob uploads, Academic Alerts, live reminders, renewal reminders, queued notifications, teacher analytics/outcomes/export, quiz feedback/notifications, the weekly report/comparison, achievements, Learning Outcomes/status, parent outcome visibility, Question Bank, quiz, assessment, Gradebook pagination/export, or announcement management slices. See `TEST_RESULTS.md` and `REGRESSION_REPORT.md`.
- Local MySQL reports the six additive migrations Pending; no migration or deployment was run.

## Production decision

Do not deploy yet. Apply the six pending migrations only after review, verify isolated MySQL and browser journeys, complete the private Blob/OIDC smoke test, and obtain explicit staging/production authorization.

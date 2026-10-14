# Execution Plan

## P0 - Security and foundation (completed locally; migration pending)

1. Record baseline and preserve the current branch state.
2. Convert direct parent phone matching into a pending approval workflow.
3. Keep legacy `verified_at` links usable and keep explicit admin/student-registration linking compatible.
4. Add expiry, request throttling, audit events, student approval/rejection, and parent revocation.
5. Add focused authorization and IDOR tests.

## P1 - Shared dashboard foundation (partial checkpoint)

1. Reuse existing layouts/cards and add only real action/navigation changes.
2. Feed dashboard cards from existing authorized aggregates; no mock data was added.
3. Full shared component extraction and browser responsive verification remain open.

## P2 - Teaching and learning (partial checkpoint)

1. Add real continue-learning selection from incomplete authorized progress.
2. Centralize assignment statuses from existing worksheet/submission records.
3. Improve teacher worksheet navigation without duplicating group models.
4. Keep the existing Learn submission and Teacher Worksheets grading paths as the working workflow.

## P3 - Assessments and gradebook (read-only checkpoint)

1. Preserve submitted-attempt history.
2. Add only additive quiz configuration/snapshot fields where a focused gap is confirmed.
3. Build a teacher-scoped read-only aggregate gradebook over submitted homework and the latest submitted quiz attempt, with explicit missing-submission semantics.
4. Keep manual quiz feedback, export, and large-dataset pagination as follow-up work.

## P4 - Parent monitoring (partial checkpoint)

1. Keep child selection strictly scoped to verified `parent_student_links`.
2. Reuse the student's active-subscription assignment status calculation for parent read-only homework monitoring.
3. Preserve parent booking/payment actions separately from academic monitoring; never add a parent-as-student submission path.
4. Add a read-only rolling seven-day report from actual lesson progress, worksheet submissions, quiz attempts, and live attendance records.

## P4.1 - Weekly parent progress report (completed locally)

1. Scope the report to the selected verified child and that child's active-subscription assignments.
2. Aggregate study minutes, completed lessons, assignment submissions/grades, quiz scores, attendance, daily activity, subject summaries, and recent activity from real records.
3. Expose the report in the existing parent dashboard without adding parent-as-student mutation controls.
4. Cover the report with a real-records feature test and preserve the existing parent authorization boundary.

## P5 - Scoped group announcements (completed locally)

1. Add durable announcements owned by a teacher and attached to an active teaching group.
2. Notify only students with an active subscription to that group.
3. Notify only parents with a verified link to an eligible student; keep notification links behind the existing authorization boundaries.
4. Add teacher publish/history UI and student dashboard history using real records.
5. Cover ownership, recipient scoping, persistent records, and the existing dashboard regression path with focused tests.

## P5.1 - Teacher performance analytics (completed locally)

1. Build a read-only teacher-scoped analytics view from active roster membership and real lesson progress, homework, quiz, and live-session records.
2. Provide attendance rate, completed lessons, study minutes, submitted and missing assignments, quiz history, average performance, and time-bucketed trends.
3. Use explainable attention signals only: missing assignments, attendance below 75% after at least two sessions, or a measurable decline between earlier and recent quiz results.
4. Add assignment, group, student, and period filters with fail-closed ownership checks and focused authorization coverage.

## P5.2 - Academic alerts (completed locally)

1. Add configurable in-app alerts for missing homework, upcoming deadlines and classes, recent absences, declining assessment trends, and subscriptions nearing expiration.
2. Scope learning alerts to active subscribed students and route parent copies only through verified parent-student links.
3. Preserve the existing live-session reminder flow while sharing a stable upcoming-class key so the two cron paths cannot double-notify the same recipient.
4. Store an alert key in every academic notification and enforce a recipient-level cooldown before sending a repeat.
5. Expose both an Artisan command and a secret-protected Vercel cron endpoint; external push/SMS delivery remains separate from the database notification channel.

## P5.3 - Resource library (completed locally)

1. Add teacher-owned educational resources with normalized metadata, tags, categories, private storage, and active/inactive lifecycle state.
2. Support both local private uploads and direct private Vercel Blob uploads, with scoped authorization, pathname validation, and protected application download routes.
3. Let teachers search and filter their own resources, attach them to owned teaching assignments, detach them, reuse approved shared resources, and request administrative sharing approval.
4. Let administrators approve or reject shared-resource requests, while students and verified parents receive access only through active subscriptions to the attached assignment.
5. Cover local upload, direct Blob authorization/completion, approval/reuse, protected student/parent access, Blob-kind validation, frontend build, and release documentation.

## P6.1 - Student assessment center (completed locally)

1. Expose only active quizzes from published curriculum units unlocked by the student's active subscriptions.
2. Present real availability windows, question counts, attempt limits, in-progress state, and submitted results.
3. Keep question content and answer keys out of the center; the existing server-side quiz flow remains the only submission/grading path.
4. Cover subscription scoping, unpublished/foreign-assignment exclusion, result history, and the frontend build.

## P6.2 - Reusable teacher question bank (completed locally)

1. Add teacher-owned reusable questions with subject, unit, lesson, topic, difficulty, tags, explanation, points, visibility, and ordered options.
2. Keep private questions owner-only and expose shared questions only through an explicit shared-library policy; foreign teachers can copy but cannot edit or delete them.
3. Add a teacher Question Bank page with creation, editing, deletion, filtering, pagination, and RTL dashboard navigation.
4. Integrate bank questions into Quiz Builder through a transactional snapshot copy so later bank edits or deletion cannot change an existing quiz question or reveal answer keys to students.
5. Cover persistence, ownership, shared reuse, Quiz Builder integration, snapshot behavior, and frontend build with focused tests.

## P6.3 - Release hardening and deferred advanced features (local checkpoint)

1. Make baseline feature tests deterministic against the demo records created by the historical seed migration instead of assuming an empty database.
2. Preserve and verify production-sensitive admin password confirmation, including no mutation before confirmation and JSON/redirect behavior.
3. Run the full regression suite and frontend production build after the hardening changes; document the exact remaining staging, migration, browser, and Blob/OIDC gates.
4. Keep external push/SMS and AI deferred until their business rules and infrastructure are approved; gamification may remain disabled by default.

## P6.4 - Gradebook CSV export hardening (completed locally)

1. Add a teacher-only CSV export that reuses the existing scoped Gradebook service and assignment filter.
2. Preserve the same latest-quiz-attempt and missing-work semantics used by the on-screen Gradebook.
3. Emit a UTF-8 BOM and stable columns for spreadsheet compatibility, while neutralizing formula-like cell values.
4. Cover export ownership, response headers, scoped rows, and spreadsheet-formula injection with focused tests and the full regression suite.

## P6.5 - Server-side Gradebook pagination (completed locally)

1. Normalize submitted worksheets and each student's latest submitted quiz attempt into one database-level union.
2. Apply teacher and assignment ownership filters before pagination, preserving the existing missing-work and latest-attempt semantics.
3. Keep summary metrics calculated across the full scoped result set while returning only the requested page to Inertia.
4. Add responsive pagination controls and cover page boundaries, totals, ordering, and regression behavior with focused tests and the full suite.

## P6.6 - Announcement management hardening (completed locally)

1. Let the owning teacher edit announcement title/body without changing group scope or sending duplicate notifications.
2. Let only the owner delete an announcement; cross-teacher mutations fail closed.
3. Add a teacher-scoped CSV export with UTF-8 spreadsheet compatibility and formula-like value neutralization.
4. Cover ownership, notification behavior, deletion, export scope, and the frontend build with focused tests and the full suite.

## P6.7 - Explainable parent progress comparison (completed locally)

1. Compare the selected verified child's current seven-day report with the immediately preceding seven-day window using the same active-subscription authorization scope.
2. Expose current value, previous value, delta, direction, and percentage change for study time, completed lessons, submitted assignments, quizzes, average quiz score, and attendance rate.
3. Add only explainable attention signals for measurable study decline, significant quiz-score decline, low current attendance, and lessons without submitted assignments.
4. Preserve the existing read-only parent dashboard and add focused real-record coverage for both current and previous periods.

## P7.1 - Optional student achievements (completed locally)

1. Add a feature-flagged achievement summary using only the student's active-subscription records.
2. Award points once per completed lesson, distinct passed quiz, distinct submitted worksheet, and attended ended session; repeated quiz attempts cannot inflate the score.
3. Expose private milestones, levels, badges, and a category breakdown on the student dashboard without a public leaderboard or external data transfer.
4. Keep the feature disabled by default and cover both the disabled state and scoped non-repeat calculations with focused tests.

## P7.2 - Lightweight learning outcomes (completed locally)

1. Add optional teacher-owned outcomes under the existing curriculum unit model.
2. Allow each outcome to target the unit, one lesson, or the unit's electronic assessment while enforcing ownership and same-unit relationships server-side.
3. Show only published outcomes to students who already have authorized access to the teaching group; do not expose outcomes from unrelated assignments.
4. Keep the feature small, additive, and covered by teacher ownership plus student visibility tests.

## P7.3 - Explainable outcome status (completed locally)

1. Derive outcome status from existing lesson progress and submitted quiz attempts without storing predictions or manual grades.
2. Show `not_started`, `in_progress`, `completed`, or `mastered` with a bounded percentage where the source record supports it.
3. Keep status inside the already authorized Study Room payload and cover completed lesson and passed assessment cases with focused regression tests.

## P7.4 - Parent outcome progress (completed locally)

1. Reuse one application service for outcome status calculation in the student Study Room and the parent report.
2. Show verified parents a read-only outcome summary for the selected child's published units attached to active subscriptions only.
3. Include explainable lesson/quiz status, completion count, and average progress without adding mutation controls or exposing draft curriculum.
4. Cover completed lessons, mastered quizzes, active-subscription scoping, and unpublished-unit exclusion with focused parent regression coverage.

## P7.5 - Teacher outcome analytics (completed locally)

1. Add a read-only aggregate of published learning outcomes to teacher performance analytics.
2. Scope the aggregate to the authenticated teacher's active assignments and current active roster.
3. Show progress percentage plus completed, mastered, in-progress, and not-started counts without exposing student mutation controls.
4. Cover teacher ownership, roster scoping, lesson/quiz status aggregation, and the frontend build.

## P7.6 - Teacher analytics CSV export (completed locally)

1. Export the same teacher-scoped analytics rows using assignment, group, student, and period filters.
2. Preserve null semantics for unavailable metrics and include explainable attention reasons in stable CSV columns.
3. Add UTF-8 BOM and neutralize formula-like values before spreadsheet export.
4. Cover response headers, ownership filtering, formula neutralization, route wiring, and the frontend build.

## P7.7 - Quiz teacher feedback (completed locally)

1. Add optional teacher feedback to submitted quiz attempts without changing server-side scoring or attempt history.
2. Allow only the owning teacher to create, replace, or clear feedback for an attempt in their curriculum.
3. Surface released feedback in the student Assessment Center, Study Room, parent read-only quiz history, Gradebook, and Gradebook CSV.
4. Cover migration shape, owner authorization, submitted-attempt validation, visibility, and the frontend build.

## P7.8 - Quiz feedback notifications (completed locally)

1. Notify the student when an owning teacher publishes a non-empty quiz feedback note.
2. Fan out the same release event only to verified linked parents, with the existing database notification channel.
3. Avoid duplicate notifications when the same feedback is saved again, and do not notify on feedback removal.
4. Cover recipient scoping, unverified-parent exclusion, deduplication, and the frontend-compatible notification payload.

## P7.9 - Queued notification delivery (completed locally)

1. Make every application Notification implement Laravel's `ShouldQueue` contract while preserving the existing database, mail, and recipient payloads.
2. Keep notification dispatch behavior compatible with the existing in-app bell, academic alert cooldowns, payment flows, live reminders, chat, and quiz feedback.
3. Add an architectural regression test covering all application notification classes so new notifications cannot silently become synchronous.
4. Keep queue-worker availability and failed-job monitoring as staging/production release gates; no worker or migration was run locally.

Every phase ends with focused tests, regression tests, a frontend build, documentation, and a small reviewable checkpoint. Production remains explicitly out of scope.

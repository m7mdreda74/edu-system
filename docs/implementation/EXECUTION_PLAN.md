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
4. Keep external push/SMS, gamification, AI, announcement editing/export, and deeper parent trend analytics deferred until their business rules and infrastructure are approved.

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

Every phase ends with focused tests, regression tests, a frontend build, documentation, and a small reviewable checkpoint. Production remains explicitly out of scope.

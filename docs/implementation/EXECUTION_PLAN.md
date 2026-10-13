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
4. Keep question-bank reuse, manual quiz feedback, export, and large-dataset pagination as follow-up work.

## P4 - Parent monitoring (partial checkpoint)

1. Keep child selection strictly scoped to verified `parent_student_links`.
2. Reuse the student's active-subscription assignment status calculation for parent read-only homework monitoring.
3. Preserve parent booking/payment actions separately from academic monitoring; never add a parent-as-student submission path.
4. Defer weekly reports, trend analytics, and configurable alerts until their data formulas and notification policy are specified.

## P5-P6 - Advanced features and release

These phases remain gated on the P0-P4 tests and isolated MySQL verification. Announcements, external push/SMS, gamification, AI, and new infrastructure are deferred unless the repository proves a safe existing integration point.

Every phase ends with focused tests, regression tests, a frontend build, documentation, and a small reviewable checkpoint. Production remains explicitly out of scope.

# LMS Architecture Checkpoint

Date: 2026-10-13
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

## Authorization surfaces

- Student parent-link actions scope the link by `student_user_id` before mutation.
- Parent revocation scopes by `parent_user_id` and verified state.
- Parent dashboard data continues to use verified links only.
- Assignment downloads point to existing protected routes; the browser never receives storage paths.
- Teacher pending-grading counts use `whereHas('worksheet.unit.assignment', teacher_id)`.

## Known architectural limits

- The migration is additive but not applied in this workspace.
- A unified quiz/homework gradebook and question bank are not yet implemented; existing worksheet grading and quiz engine remain separate.
- Browser/mobile E2E and production-like MySQL transaction/concurrency verification are still release gates.

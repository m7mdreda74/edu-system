# Al-Tafawwuq LMS Baseline Audit

Date: 2026-10-13
Branch: `feature/lms-dashboard-v2`
Baseline commit: `08bc29c`

## Scope and source of truth

The repository is an existing Laravel 12 / Vue 3 / Inertia application. The current implementation already contains the private-tutoring business model and a significant LMS slice. The audit was performed against the local repository; the deployed Vercel application and production database were not modified.

## Existing architecture found

- Domain models are split under `app/Domain/*/Models` for academic, learning, quiz, scheduling, subscription, payment, and user concerns.
- Application services already exist for quiz grading, subscription renewal, scheduling/booking, parent links, and live-session reminders.
- Inertia routes and role boundaries are in `routes/web.php` for student, teacher, parent, and admin workflows.
- Existing protected-file/video controllers re-check authorization server-side.
- Existing parent dashboards already aggregate attendance, worksheets, quiz attempts, subscriptions, payments, and booking actions.
- Existing teacher pages include curriculum, materials, worksheets, quizzes, live sessions, schedules, groups, and students.

## Critical finding

`ParentStudentLinkService::linkExistingStudent()` previously marked a parent/student link as verified immediately after matching an active student's phone number. This granted access to child data and parent booking/payment actions without student approval. The implementation phase addresses this first. Existing links with a non-null `verified_at` are treated as compatible, already-authorized relationships.

## Reusable functionality

- `SubscriptionService` and `SessionBookingService` own existing purchase/booking rules and must remain the only path for those mutations.
- `WorksheetSubmission::updateOrCreate()` already prevents duplicate homework submissions.
- `QuizService` performs server-side scoring and time-limit checks.
- `ProtectedFileController`, `MaterialPolicy`, and signed video routes provide the existing protected-resource boundary.
- `GenericDatabaseNotification` and `AuditLogger` provide existing notification/audit infrastructure.

## Main gaps for later phases

- Dashboard payloads are role-specific but do not yet expose a shared, consistent LMS component vocabulary.
- Student dashboard lacks a first-class continue-learning/assignment/assessment summary.
- Teacher dashboard has operational summaries but no unified gradebook endpoint.
- Parent linking needs approval/rejection/revocation state and an auditable request lifecycle.
- Some requested advanced capabilities (question bank reuse, announcements, feature-flagged gamification, external push/SMS) are not present and require separate scoped work.

## Safety constraints

- No production deployment, production migration, or production database access was performed.
- Existing payment, subscription, booking, live-session, video, and Blob upload code is preserved.
- Schema work is additive and keeps `verified_at` for backward compatibility.

# Feature Matrix

Status values: existing, partial, missing, security gap, deferred.

| Feature | Current evidence | Status | Implementation decision |
| --- | --- | --- | --- |
| Public grade/subject/teacher discovery | `Public/*Controller`, `BrowseFlowTest`, `TeacherDirectoryController` | existing | Preserve and regression-test. |
| Group subscriptions and pricing | `SubscriptionService`, `TeachingGroup`, checkout routes | existing | Reuse; no duplicate course model. |
| Private booking and slot conflicts | `SessionBookingService`, private slot routes/tests | existing | Reuse; later concurrency verification on MySQL. |
| Curriculum units and lesson materials | `CurriculumController`, `Curriculum.vue`, Blob upload service | partial | Existing protected curriculum is reused; Continue Learning now consumes published lesson/progress records. |
| Worksheets and grading | `WorksheetController`, `LearnController`, `WorksheetSubmission`, `StudentAssignmentService` | partial | Existing upsert/grading is preserved; student status center is added, unified gradebook remains deferred. |
| Quizzes and server-side grading | `QuizService`, `QuizController`, quiz builder | partial | Preserve attempts; add scheduling/configuration gaps after baseline. |
| Protected learning files/videos | `ProtectedFileController`, `MaterialPolicy`, signed routes | existing with security boundary | Keep server-side checks; add regression coverage where changed. |
| Parent child monitoring | `ParentDashboardController`, `StudentAssignmentService`, parent portal tests | partial, improved checkpoint | Verified-child switching, attendance, quiz/submission results, payments, and active homework statuses are read-only; weekly reports/analytics remain open. |
| Parent/student linking | `ParentStudentLinkService`, `parent_student_links` | improved, migration pending | Pending request, student approval/rejection, expiry, audit, revocation, generic response, and rate limiting are implemented; migration still needs controlled release. |
| Teacher operational dashboard | `Teacher/DashboardController`, `Teacher/Dashboard.vue` | partial, improved | Existing aggregates plus real pending-grading count and worksheet navigation. |
| Student dashboard | `Student/DashboardController`, `Student/Dashboard.vue` | partial, improved | Pending link decisions, real Continue Learning, and Assignment Center navigation are implemented. |
| Student assignment center | `StudentAssignmentService`, `Student/Assignments.vue`, `AssignmentsTest` | implemented checkpoint | Active subscriptions, published homework, progress/submission statuses, protected files, filters, and empty states use real records. |
| Unified gradebook | `TeacherGradebookService`, `Teacher/Gradebook.vue`, `GradebookTest` | implemented checkpoint | Read-only aggregate of submitted homework and latest submitted quiz attempt, teacher-scoped, with explicit missing-submission semantics; pagination/export remain follow-up hardening. |
| Question bank | Quiz questions are scoped to quizzes | missing/partial | Defer until question ownership/snapshot rules are specified and tested. |
| Announcements/academic alerts | GroupAnnouncementService, Teacher/Announcements.vue, GenericDatabaseNotification, AnnouncementsTest | implemented checkpoint | Teacher-owned active groups only; active students receive dashboard history and notifications, verified linked parents receive relevant notifications; push/SMS and editing/export remain deferred. |
| Gamification/AI integrations | No safe configured infrastructure found | deferred | Do not add appearance-only integrations. |
| Vercel/serverless storage | `CurriculumBlobUpload`, `api/blob-*`, `vercel.json` | existing but environment-dependent | Preserve; production Blob/OIDC smoke test remains external. |

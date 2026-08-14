<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AcademicTermController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\GradeLevelController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\RecordedClassController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SessionApologyController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeachingGroupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Communication\ChatController;
use App\Http\Controllers\Communication\NotificationController;
use App\Http\Controllers\Cron\LiveSessionReminderController;
use App\Http\Controllers\Cron\SubscriptionRenewalReminderController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Learning\ProtectedFileController;
use App\Http\Controllers\Learning\YoutubeProxyController;
use App\Http\Controllers\Live\LiveSessionRoomController;
use App\Http\Controllers\Parent\ParentDashboardController;
use App\Http\Controllers\Parent\ParentPrivateLessonRequestController;
use App\Http\Controllers\Parent\ParentPurchaseRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\GradeLevelBrowseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SubjectTeachersController;
use App\Http\Controllers\Public\TeacherDirectoryController;
use App\Http\Controllers\Public\TeacherProfileController;
use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\FreeIntroSessionController as StudentFreeIntroSessionController;
use App\Http\Controllers\Student\LearnController;
use App\Http\Controllers\Student\LessonQuestionController;
use App\Http\Controllers\Student\MyGradeController;
use App\Http\Controllers\Student\PrivateLessonRequestController;
use App\Http\Controllers\Student\QuizController;
use App\Http\Controllers\Student\ReviewController;
use App\Http\Controllers\Student\ScheduleController;
use App\Http\Controllers\Student\StudentPurchaseRequestController;
use App\Http\Controllers\Student\SubscriptionController;
use App\Http\Controllers\Student\VideoUrlController;
use App\Http\Controllers\SubscriptionRenewalController;
use App\Http\Controllers\Teacher\CurriculumController;
use App\Http\Controllers\Teacher\FreeIntroSessionController as TeacherFreeIntroSessionController;
use App\Http\Controllers\Teacher\LiveSessionController;
use App\Http\Controllers\Teacher\MaterialManagerController;
use App\Http\Controllers\Teacher\QuizBuilderController;
use App\Http\Controllers\Teacher\TeachingScheduleController;
use App\Http\Controllers\Teacher\WorksheetController;
use Illuminate\Support\Facades\Route;

// ─── Health Check ─────────────────────────────────────────────────────────────
Route::get('/health', HealthController::class)
    ->name('health')
    ->middleware('throttle:60,1');
Route::get('/api/cron/subscription-renewal-reminders', SubscriptionRenewalReminderController::class)
    ->name('cron.subscription-renewal-reminders')
    ->middleware(['throttle:10,1', 'cron.secret']);
Route::get('/api/cron/live-session-reminders', LiveSessionReminderController::class)
    ->name('cron.live-session-reminders')
    ->middleware(['throttle:10,1', 'cron.secret']);

// ─── Public Browse Flow: grade → subject → teachers → profile ─────────────────
Route::get('/', [HomeController::class,             'index'])->name('home');
Route::get('/grades/{key}', [GradeLevelBrowseController::class, 'show'])->name('grades.show');
Route::get('/grades/{gradeKey}/subjects/{subject}', [SubjectTeachersController::class,  'show'])->name('subjects.teachers');
Route::get('/teachers', [TeacherDirectoryController::class, 'index'])->name('teachers.index');
Route::get('/teachers/{id}', [TeacherProfileController::class,   'show'])->name('teachers.show');

Route::get('/api/search-autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');

Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::get('/our-apps', [HomeController::class, 'ourApps'])->name('our_apps');
Route::get('/students-results', [HomeController::class, 'studentsResults'])->name('students_results');

// ─── Authenticated Routes ──────────────────────────────────────────────────────
Route::middleware(['auth', 'active', 'verified'])->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Chat ─────────────────────────────────────────────────────────────────
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/start', [ChatController::class, 'startConversation'])->name('chat.start');
    Route::post('/chat/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/fetch', [ChatController::class, 'fetchMessages'])->name('chat.fetch');

    // ─── Live Session Room ────────────────────────────────────────────────────
    Route::get('/live-sessions/{id}/room', [LiveSessionRoomController::class, 'show'])->name('live-sessions.room');

    // ─── Material Q&A Forum ───────────────────────────────────────────────────
    Route::get('/materials/{materialId}/questions', [LessonQuestionController::class, 'index'])->name('materials.questions.index');
    Route::post('/materials/{materialId}/questions', [LessonQuestionController::class, 'store'])->name('materials.questions.store');

    // ─── Notifications ────────────────────────────────────────────────────────
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/subscriptions/{subscription}/renewal', [SubscriptionRenewalController::class, 'show'])
        ->name('subscriptions.renewal.show');
    Route::post('/subscriptions/{subscription}/renewal', [SubscriptionRenewalController::class, 'store'])
        ->name('subscriptions.renewal.store')
        ->middleware('throttle:10,1');
});

// Protected learning/chat files. The database path is never sent to the
// browser; every download re-checks the current user's relationship.
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/learning/materials/{material}/file', [ProtectedFileController::class, 'material'])
        ->name('learning.material.download');
    Route::get('/learning/worksheets/{worksheet}/file', [ProtectedFileController::class, 'worksheet'])
        ->name('learning.worksheet.download');
    Route::get('/learning/submissions/{submission}/file', [ProtectedFileController::class, 'submission'])
        ->name('learning.submission.download');
    Route::get('/chat/attachments/{message}', [ProtectedFileController::class, 'chatAttachment'])
        ->name('chat.attachment.download');
});

// ─── Student Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'active', 'verified', 'role:student'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // The student's own grade: every subject on their curriculum and its teachers
    Route::get('/my-grade', [MyGradeController::class, 'index'])->name('student.my-grade');

    Route::get('/my-schedule', [ScheduleController::class, 'index'])->name('student.schedule');

    // Group subscriptions, plus teacher-led agreement requests for private tuition.
    Route::get('/my-classes', [SubscriptionController::class, 'index'])->name('student.my-classes');
    Route::post('/subscribe/group/{groupId}', [SubscriptionController::class, 'subscribeToGroup'])->name('student.subscribe.group')->middleware('throttle:15,1');
    Route::post('/private-lesson-requests/{assignmentId}', [PrivateLessonRequestController::class, 'store'])
        ->name('student.private-lesson-requests.store')
        ->middleware('throttle:10,1');
    Route::post('/private-session-slots/{slotId}/book', [SubscriptionController::class, 'bookPrivateSlot'])
        ->name('student.private-slots.book')
        ->middleware('throttle:10,1');
    Route::post('/free-intro-sessions/{slotId}', [StudentFreeIntroSessionController::class, 'store'])
        ->name('student.free-intro-sessions.store')
        ->middleware('throttle:10,1');
    Route::post('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew'])->name('student.subscriptions.renew');
    Route::delete('/subscriptions/{id}', [SubscriptionController::class, 'cancel'])->name('student.subscriptions.cancel');

    // Study room — materials, progress and homework for one group
    Route::get('/my-classes/{groupId}/learn', [LearnController::class, 'show'])->name('student.learn');
    Route::post('/my-classes/{groupId}/materials/{materialId}/progress', [LearnController::class, 'updateProgress'])->name('student.material.progress');
    Route::post('/my-classes/{groupId}/worksheets/{worksheetId}/submit', [LearnController::class, 'submitHomework'])->name('student.worksheets.submit');

    // Quiz
    Route::get('/quiz/{quizId}', [QuizController::class, 'show'])->name('student.quiz');
    Route::post('/quiz/{quizId}/start', [QuizController::class, 'start'])->name('student.quiz.start');
    Route::post('/quiz/{quizId}/submit', [QuizController::class, 'submit'])->name('student.quiz.submit');
    Route::post('/quiz/{quizId}/violation', [QuizController::class, 'recordViolation'])->name('student.quiz.violation');

    // Certificate — earned per group
    Route::get('/certificate/{groupId}', [CertificateController::class, 'show'])->name('student.certificate');

    // Teacher reviews
    Route::post('/teachers/{teacherId}/reviews', [ReviewController::class, 'store'])->name('student.review.store');
    Route::delete('/teachers/{teacherId}/reviews', [ReviewController::class, 'destroy'])->name('student.review.destroy');

    // Signed video URL (never expose raw video_url to the client)
    Route::get('/materials/{materialId}/video', [VideoUrlController::class, 'getSignedUrl'])->name('student.video.url');

    // Ask a parent to pay
    Route::post('/purchase-requests', [StudentPurchaseRequestController::class, 'store'])->name('student.purchase-requests.store');
});

// Signed video stream endpoint (validated by Laravel signature)
Route::get('/stream/{materialId}', [VideoUrlController::class, 'stream'])
    ->name('video.stream')
    ->middleware(['auth', 'active', 'signed']);

// ── YouTube Proxy ─────────────────────────────────────────────────────────────
// Resolves the direct proxy URL for a YouTube video lesson (returns JSON)
Route::get('/materials/{materialId}/youtube-url', [YoutubeProxyController::class, 'resolveStreamUrl'])
    ->name('youtube.proxy.resolve')
    ->middleware(['auth', 'active']);

// Signed proxy stream endpoint — pipes YouTube stream through our server
Route::get('/youtube-stream/{materialId}', [YoutubeProxyController::class, 'stream'])
    ->name('youtube.proxy.stream')
    ->middleware(['auth', 'active', 'signed']);

// ─── Checkout Routes ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::post('/checkout/coupon/check', [CheckoutController::class, 'checkCoupon'])
        ->name('checkout.coupon.check')
        ->middleware('throttle:15,1');

    Route::get('/checkout/{subscription}', [CheckoutController::class, 'show'])->name('checkout.show')->whereNumber('subscription');
    Route::post('/checkout/{subscription}', [CheckoutController::class, 'process'])
        ->name('checkout.process')
        ->whereNumber('subscription')
        ->middleware('throttle:10,1');
});

// ─── Teacher Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'active', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Teacher\DashboardController::class, 'index'])->name('dashboard');

    // Curriculum builder — the syllabus hangs off the assignment, not the group,
    // so the teacher writes it once for every group and private student on it.
    Route::get('/assignments/{assignment}/curriculum', [CurriculumController::class, 'index'])->name('curriculum');
    Route::post('/assignments/{assignment}/curriculum/skeleton', [CurriculumController::class, 'skeleton'])->name('curriculum.skeleton');
    Route::post('/assignments/{assignment}/units', [CurriculumController::class, 'storeUnit'])->name('units.store');
    Route::put('/units/{unit}', [CurriculumController::class, 'updateUnit'])->name('units.update');
    Route::delete('/units/{unit}', [CurriculumController::class, 'destroyUnit'])->name('units.destroy');
    Route::post('/units/{unit}/reorder', [CurriculumController::class, 'reorderUnit'])->name('units.reorder');
    Route::post('/units/{unit}/lessons', [CurriculumController::class, 'storeLesson'])->name('lessons.store');
    Route::put('/lessons/{lesson}', [CurriculumController::class, 'updateLesson'])->name('lessons.update');
    Route::delete('/lessons/{lesson}', [CurriculumController::class, 'destroyLesson'])->name('lessons.destroy');
    Route::post('/curriculum-uploads/authorize', [CurriculumController::class, 'authorizeBlobUpload'])
        ->name('curriculum-uploads.authorize')
        ->middleware('throttle:30,1');
    // The {lesson} and {unit} placeholders are read by UploadHomeworkRequest and
    // UploadPaperExamRequest to decide whether the file is required — do not rename.
    Route::post('/lessons/{lesson}/booklet', [CurriculumController::class, 'storeBooklet'])->name('lessons.booklet');
    Route::post('/lessons/{lesson}/homework', [CurriculumController::class, 'storeHomework'])->name('lessons.homework');
    Route::post('/units/{unit}/paper-exam', [CurriculumController::class, 'storePaperExam'])->name('units.paper-exam');

    // Quiz builder — the electronic form of the unit exam
    Route::post('/units/{unit}/quizzes', [QuizBuilderController::class, 'store'])->name('quizzes.store');
    Route::get('/quizzes/{quiz}/edit', [QuizBuilderController::class, 'edit'])->name('quizzes.edit');
    Route::put('/quizzes/{quiz}', [QuizBuilderController::class, 'update'])->name('quizzes.update');
    Route::delete('/quizzes/{quiz}', [QuizBuilderController::class, 'destroy'])->name('quizzes.destroy');
    Route::post('/quizzes/{quiz}/questions', [QuizBuilderController::class, 'storeQuestion'])->name('quizzes.questions.store');
    Route::put('/questions/{question}', [QuizBuilderController::class, 'updateQuestion'])->name('questions.update');
    Route::delete('/questions/{question}', [QuizBuilderController::class, 'destroyQuestion'])->name('questions.destroy');

    // Group material (recorded videos and files) — superseded by the curriculum
    // builder above; kept because uploads made here still land in the first unit.
    Route::get('/groups/{groupId}/materials', [MaterialManagerController::class, 'index'])->name('materials');
    Route::post('/groups/{groupId}/materials', [MaterialManagerController::class, 'store'])->name('materials.store');
    Route::put('/materials/{id}', [MaterialManagerController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{id}', [MaterialManagerController::class, 'destroy'])->name('materials.destroy');

    // Live Sessions
    Route::get('/live-sessions', [LiveSessionController::class, 'index'])->name('live-sessions');
    Route::post('/live-sessions', [LiveSessionController::class, 'store'])->name('live-sessions.store');
    Route::patch('/live-sessions/{id}/status', [LiveSessionController::class, 'updateStatus'])->name('live-sessions.status');
    Route::post('/live-sessions/{id}/attendance', [LiveSessionController::class, 'updateAttendance'])->name('live-sessions.attendance');
    Route::post('/live-sessions/{id}/apology', [LiveSessionController::class, 'apologize'])->name('live-sessions.apologize');
    Route::post('/session-apologies/{id}/makeup', [LiveSessionController::class, 'scheduleMakeup'])->name('session-apologies.makeup');

    // Academic lesson planning for groups configured by the administration.
    Route::get('/teaching-schedule', [TeachingScheduleController::class, 'index'])->name('teaching-schedule');
    Route::post('/teaching-schedule/groups/{id}/schedules', [TeachingScheduleController::class, 'storeGroupSchedule'])->name('teaching-schedule.groups.schedules.store');
    Route::patch('/teaching-schedule/groups/{id}/capacity', [TeachingScheduleController::class, 'updateGroupCapacity'])->name('teaching-schedule.groups.capacity');
    Route::delete('/teaching-schedule/group-schedules/{id}', [TeachingScheduleController::class, 'destroyGroupSchedule'])->name('teaching-schedule.group-schedules.destroy');
    Route::post('/free-intro-sessions', [TeacherFreeIntroSessionController::class, 'store'])->name('free-intro-sessions.store');
    Route::delete('/free-intro-sessions/{id}', [TeacherFreeIntroSessionController::class, 'destroy'])->name('free-intro-sessions.destroy');
    Route::post('/private-session-slots', [TeacherFreeIntroSessionController::class, 'storePrivate'])->name('private-slots.store');
    Route::delete('/private-session-slots/{id}', [TeacherFreeIntroSessionController::class, 'destroyPrivate'])->name('private-slots.destroy');
    Route::post('/teaching-schedule/groups/{id}/lessons', [TeachingScheduleController::class, 'storeGroupLesson'])->name('teaching-schedule.groups.lessons.store');
    Route::post('/teaching-schedule/group-lessons/{id}/schedule', [TeachingScheduleController::class, 'scheduleGroupLesson'])->name('teaching-schedule.group-lessons.schedule');
    Route::get('/teaching-schedule/group-lessons/{id}/schedule', [TeachingScheduleController::class, 'redirectGroupLessonSchedule'])->name('teaching-schedule.group-lessons.schedule-link');

    // Worksheets & Grading
    Route::get('/groups/{groupId}/worksheets', [WorksheetController::class, 'index'])->name('worksheets.index');
    Route::post('/groups/{groupId}/worksheets', [WorksheetController::class, 'store'])->name('worksheets.store');
    Route::post('/worksheets/grade/{submissionId}', [WorksheetController::class, 'gradeSubmission'])->name('worksheets.grade');
    Route::delete('/worksheets/{id}', [WorksheetController::class, 'destroy'])->name('worksheets.destroy');
});

// ─── Admin Routes ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'active', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    // Polled by the dashboard so its figures stay live without a page reload.
    Route::get('/dashboard/stats', [App\Http\Controllers\Admin\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/users', [UserController::class, 'index'])->name('users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{id}/password', [UserController::class, 'resetPassword'])->name('users.password');
    Route::patch('/users/{id}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');
    Route::patch('/users/{id}/role', [UserController::class, 'updateRole'])->name('users.role');
    Route::patch('/users/{id}/commission', [UserController::class, 'updateCommission'])->name('users.commission');
    // Teacher photos are public-facing, so the platform owns them.
    Route::post('/users/{id}/avatar', [UserController::class, 'updateAvatar'])->name('users.avatar');
    Route::delete('/users/{id}/avatar', [UserController::class, 'deleteAvatar'])->name('users.avatar.delete');

    // Subscriptions replace the old course catalogue
    Route::get('/subscriptions', [App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('subscriptions');
    Route::delete('/subscriptions/{id}', [App\Http\Controllers\Admin\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // Teaching assignments, group shells, capacity and all pricing belong to admin.
    Route::get('/teaching-groups', [TeachingGroupController::class, 'index'])->name('teaching-groups');
    Route::post('/teaching-assignments', [TeachingGroupController::class, 'storeAssignment'])->name('teaching-assignments.store');
    Route::patch('/teaching-assignments/{id}', [TeachingGroupController::class, 'updateAssignment'])->name('teaching-assignments.update');
    Route::post('/teaching-groups', [TeachingGroupController::class, 'store'])->name('teaching-groups.store');
    Route::get('/teaching-groups/{id}', [TeachingGroupController::class, 'show'])->name('teaching-groups.show');
    Route::put('/teaching-groups/{id}', [TeachingGroupController::class, 'update'])->name('teaching-groups.update');
    Route::patch('/teaching-groups/{id}/toggle', [TeachingGroupController::class, 'toggle'])->name('teaching-groups.toggle');
    Route::delete('/teaching-groups/{id}', [TeachingGroupController::class, 'destroy'])->name('teaching-groups.destroy');
    Route::delete('/recorded-classes/{id}', [RecordedClassController::class, 'destroy'])->name('recorded-classes.destroy');

    // Teachers submit academic apologies; only administration can apply money deductions.
    Route::get('/session-apologies', [SessionApologyController::class, 'index'])->name('session-apologies');
    Route::post('/session-apologies/{id}/deduct', [SessionApologyController::class, 'deduct'])->name('session-apologies.deduct');

    // Review moderation — nothing reaches a teacher's profile without it
    Route::get('/reviews', [App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews');
    Route::post('/reviews/approve-all', [App\Http\Controllers\Admin\ReviewController::class, 'approveAll'])->name('reviews.approve-all');
    Route::patch('/reviews/{id}/approve', [App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{id}/reject', [App\Http\Controllers\Admin\ReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{id}', [App\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Academic calendar
    Route::get('/academic-terms', [AcademicTermController::class, 'index'])->name('academic-terms');
    Route::post('/academic-terms', [AcademicTermController::class, 'store'])->name('academic-terms.store');
    Route::put('/academic-terms/{id}', [AcademicTermController::class, 'update'])->name('academic-terms.update');
    Route::delete('/academic-terms/{id}', [AcademicTermController::class, 'destroy'])->name('academic-terms.destroy');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
    Route::post('/payments/{payment}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{id}', [SettingsController::class, 'destroy'])->name('settings.destroy');

    Route::get('/site-pages', [SettingsController::class, 'sitePages'])->name('site-pages');
    Route::post('/site-pages', [SettingsController::class, 'updateSitePages'])->name('site-pages.update');

    // Subjects
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{id}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    // Payouts
    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
    Route::post('/payouts/{id}/pay', [PayoutController::class, 'markAsPaid'])->name('payouts.pay');
    Route::get('/payouts/{id}/receipt', [PayoutController::class, 'receipt'])->name('payouts.receipt');
    Route::delete('/payouts/{id}', [PayoutController::class, 'destroy'])->name('payouts.destroy');

    // Coupons
    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::patch('/coupons/{id}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{id}', [CouponController::class, 'destroy'])->name('coupons.destroy');

    // Grade Levels
    Route::get('/grade-levels', [GradeLevelController::class, 'index'])->name('grade-levels');
    Route::post('/grade-levels', [GradeLevelController::class, 'store'])->name('grade-levels.store');
    Route::get('/grade-levels/{id}', [GradeLevelController::class, 'show'])->name('grade-levels.show');
    Route::put('/grade-levels/{id}', [GradeLevelController::class, 'update'])->name('grade-levels.update');
    Route::delete('/grade-levels/{id}', [GradeLevelController::class, 'destroy'])->name('grade-levels.destroy');
});

// ─── Parent Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'active', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/dashboard', [ParentDashboardController::class, 'index'])->name('dashboard');
    Route::delete('/unlink-student/{id}', [ParentDashboardController::class, 'unlinkStudent'])->name('unlink-student');
    Route::post('/purchase-requests/{id}/pay', [ParentDashboardController::class, 'payForRequest'])->name('purchase-requests.pay');
    Route::post('/purchase-requests/{id}/reject', [ParentPurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
    Route::post('/groups/{groupId}/subscribe', [ParentDashboardController::class, 'subscribeToGroup'])->name('groups.subscribe');
    Route::post('/free-intro-sessions/{slotId}', [ParentDashboardController::class, 'bookFreeIntro'])->name('free-intro-sessions.book');
    Route::post('/private-lesson-requests/{assignmentId}', [ParentPrivateLessonRequestController::class, 'store'])->name('private-lesson-requests.store');
});

require __DIR__.'/auth.php';

Route::get('/run-prod-migrations-seed', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
    return 'Migrated and seeded successfully!';
});

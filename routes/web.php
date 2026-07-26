<?php

declare(strict_types=1);

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\GradeLevelBrowseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\SubjectTeachersController;
use App\Http\Controllers\Public\TeacherProfileController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// ─── Health Check ─────────────────────────────────────────────────────────────
Route::get('/health', function () {
    $checks = [];

    try { DB::select('SELECT 1');                  $checks['database'] = 'ok'; }
    catch (\Throwable $e) { $checks['database'] = 'error: ' . $e->getMessage(); }

    try {
        Cache::put('health_check', true, 5);
        $checks['cache'] = Cache::get('health_check') ? 'ok' : 'error';
    } catch (\Throwable $e) { $checks['cache'] = 'error: ' . $e->getMessage(); }

    return response()->json([
        'status'  => 'ok',
        'checks'  => $checks,
        'version' => Application::VERSION,
        'time'    => now()->toIso8601String(),
    ]);
})->name('health');

// ─── Public Browse Flow: grade → subject → teachers → profile ─────────────────
Route::get('/',                                     [HomeController::class,             'index'])->name('home');
Route::get('/grades/{key}',                         [GradeLevelBrowseController::class, 'show'])->name('grades.show');
Route::get('/grades/{gradeKey}/subjects/{subject}', [SubjectTeachersController::class,  'show'])->name('subjects.teachers');
Route::get('/teachers/{id}',                        [TeacherProfileController::class,   'show'])->name('teachers.show');

Route::get('/api/search-autocomplete', [App\Http\Controllers\Public\SearchController::class, 'autocomplete'])->name('search.autocomplete');

Route::get('/about',            [HomeController::class, 'about'])->name('about');
Route::get('/contact',          [HomeController::class, 'contact'])->name('contact');
Route::get('/our-apps',         [HomeController::class, 'ourApps'])->name('our_apps');
Route::get('/students-results', [HomeController::class, 'studentsResults'])->name('students_results');

// ─── Authenticated Routes ──────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Profile
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Chat ─────────────────────────────────────────────────────────────────
    Route::get('/chat',        [App\Http\Controllers\Communication\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/start', [App\Http\Controllers\Communication\ChatController::class, 'startConversation'])->name('chat.start');
    Route::post('/chat/send',  [App\Http\Controllers\Communication\ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/fetch',  [App\Http\Controllers\Communication\ChatController::class, 'fetchMessages'])->name('chat.fetch');

    // ─── Live Session Room ────────────────────────────────────────────────────
    Route::get('/live-sessions/{id}/room', [App\Http\Controllers\Live\LiveSessionRoomController::class, 'show'])->name('live-sessions.room');

    // ─── WebRTC Signaling (HTTP Polling) ──────────────────────────────────────
    Route::prefix('live-sessions/{id}/webrtc')->name('webrtc.')->group(function () {
        Route::post('heartbeat', [App\Http\Controllers\Live\WebRtcSignalingController::class, 'heartbeat'])->name('heartbeat');
        Route::post('signal',    [App\Http\Controllers\Live\WebRtcSignalingController::class, 'signal'])->name('signal');
        Route::get('poll',       [App\Http\Controllers\Live\WebRtcSignalingController::class, 'poll'])->name('poll');
        Route::post('leave',     [App\Http\Controllers\Live\WebRtcSignalingController::class, 'leave'])->name('leave');
    });

    // ─── Material Q&A Forum ───────────────────────────────────────────────────
    Route::get('/materials/{materialId}/questions',  [App\Http\Controllers\Student\LessonQuestionController::class, 'index'])->name('materials.questions.index');
    Route::post('/materials/{materialId}/questions', [App\Http\Controllers\Student\LessonQuestionController::class, 'store'])->name('materials.questions.store');

    // ─── Notifications ────────────────────────────────────────────────────────
    Route::get('/notifications',            [App\Http\Controllers\Communication\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Communication\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all',  [App\Http\Controllers\Communication\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}',    [App\Http\Controllers\Communication\NotificationController::class, 'destroy'])->name('notifications.destroy');
});

// ─── Student Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');

    // Subscriptions — monthly access to groups and private tuition
    Route::get('/my-classes',                        [App\Http\Controllers\Student\SubscriptionController::class, 'index'])->name('student.my-classes');
    Route::post('/subscribe/group/{groupId}',        [App\Http\Controllers\Student\SubscriptionController::class, 'subscribeToGroup'])->name('student.subscribe.group')->middleware('throttle:15,1');
    Route::post('/subscribe/private/{assignmentId}', [App\Http\Controllers\Student\SubscriptionController::class, 'subscribeToPrivate'])->name('student.subscribe.private')->middleware('throttle:15,1');
    Route::post('/subscriptions/{id}/renew',         [App\Http\Controllers\Student\SubscriptionController::class, 'renew'])->name('student.subscriptions.renew');
    Route::delete('/subscriptions/{id}',             [App\Http\Controllers\Student\SubscriptionController::class, 'cancel'])->name('student.subscriptions.cancel');

    // Booking teachers' groups and private slots
    Route::get('/session-booking',               [App\Http\Controllers\Student\SessionBookingController::class, 'index'])->name('student.session-booking');
    Route::post('/session-booking/groups/{id}',  [App\Http\Controllers\Student\SessionBookingController::class, 'bookGroup'])->name('student.session-booking.group');
    Route::post('/session-booking/private/{id}', [App\Http\Controllers\Student\SessionBookingController::class, 'bookPrivate'])->name('student.session-booking.private');
    Route::delete('/session-booking/{id}',       [App\Http\Controllers\Student\SessionBookingController::class, 'cancel'])->name('student.session-booking.cancel');

    // Study room — materials, progress and homework for one group
    Route::get('/my-classes/{groupId}/learn',                            [App\Http\Controllers\Student\LearnController::class, 'show'])->name('student.learn');
    Route::post('/my-classes/{groupId}/materials/{materialId}/progress', [App\Http\Controllers\Student\LearnController::class, 'updateProgress'])->name('student.material.progress');
    Route::post('/my-classes/{groupId}/worksheets/{worksheetId}/submit', [App\Http\Controllers\Student\LearnController::class, 'submitHomework'])->name('student.worksheets.submit');

    // Quiz
    Route::get('/quiz/{quizId}',            [App\Http\Controllers\Student\QuizController::class, 'show'])->name('student.quiz');
    Route::post('/quiz/{quizId}/start',     [App\Http\Controllers\Student\QuizController::class, 'start'])->name('student.quiz.start');
    Route::post('/quiz/{quizId}/submit',    [App\Http\Controllers\Student\QuizController::class, 'submit'])->name('student.quiz.submit');
    Route::post('/quiz/{quizId}/violation', [App\Http\Controllers\Student\QuizController::class, 'recordViolation'])->name('student.quiz.violation');

    // Certificate — earned per group
    Route::get('/certificate/{groupId}', [App\Http\Controllers\Student\CertificateController::class, 'show'])->name('student.certificate');

    // Teacher reviews
    Route::post('/teachers/{teacherId}/reviews',   [App\Http\Controllers\Student\ReviewController::class, 'store'])->name('student.review.store');
    Route::delete('/teachers/{teacherId}/reviews', [App\Http\Controllers\Student\ReviewController::class, 'destroy'])->name('student.review.destroy');

    // Signed video URL (never expose raw video_url to the client)
    Route::get('/materials/{materialId}/video', [App\Http\Controllers\Student\VideoUrlController::class, 'getSignedUrl'])->name('student.video.url');

    // Ask a parent to pay
    Route::post('/purchase-requests', [App\Http\Controllers\Student\StudentPurchaseRequestController::class, 'store'])->name('student.purchase-requests.store');
});

// Signed video stream endpoint (validated by Laravel signature)
Route::get('/stream/{materialId}', [App\Http\Controllers\Student\VideoUrlController::class, 'stream'])
    ->name('video.stream')
    ->middleware('signed');

// ─── Checkout Routes ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout/success',       [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel',        [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::post('/checkout/coupon/check', [CheckoutController::class, 'checkCoupon'])
        ->name('checkout.coupon.check')
        ->middleware('throttle:15,1');

    Route::get('/checkout/mock-gateway/{ref}',           [CheckoutController::class, 'mockGateway'])->name('checkout.mock_gateway');
    Route::post('/checkout/mock-gateway/{ref}/complete', [CheckoutController::class, 'mockComplete'])->name('checkout.mock_gateway.complete');
    Route::post('/checkout/mock-gateway/{ref}/cancel',   [CheckoutController::class, 'mockCancel'])->name('checkout.mock_gateway.cancel');

    // Declared after the literal segments so "mock-gateway" is never read as an id.
    Route::get('/checkout/{subscription}',  [CheckoutController::class, 'show'])->name('checkout.show')->whereNumber('subscription');
    Route::post('/checkout/{subscription}', [CheckoutController::class, 'process'])
        ->name('checkout.process')
        ->whereNumber('subscription')
        ->middleware('throttle:10,1');
});

// ─── Webhook Routes (NO auth, NO CSRF) ────────────────────────────────────────
Route::withoutMiddleware(['web'])->group(function () {
    Route::post('/webhooks/stripe', [WebhookController::class, 'stripe'])->name('webhooks.stripe');
    Route::post('/webhooks/fatora', [WebhookController::class, 'fatora'])->name('webhooks.fatora');
});

// ─── Teacher Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard',                 [App\Http\Controllers\Teacher\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/payouts',                   [App\Http\Controllers\Teacher\PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts/{id}/acknowledge', [App\Http\Controllers\Teacher\PayoutController::class, 'acknowledge'])->name('payouts.acknowledge');
    Route::get('/payouts/{id}/receipt',      [App\Http\Controllers\Teacher\PayoutController::class, 'receipt'])->name('payouts.receipt');

    // Group material (recorded videos and files)
    Route::get('/groups/{groupId}/materials',  [App\Http\Controllers\Teacher\MaterialManagerController::class, 'index'])->name('materials');
    Route::post('/groups/{groupId}/materials', [App\Http\Controllers\Teacher\MaterialManagerController::class, 'store'])->name('materials.store');
    Route::put('/materials/{id}',              [App\Http\Controllers\Teacher\MaterialManagerController::class, 'update'])->name('materials.update');
    Route::delete('/materials/{id}',           [App\Http\Controllers\Teacher\MaterialManagerController::class, 'destroy'])->name('materials.destroy');

    // Live Sessions
    Route::get('/live-sessions',               [App\Http\Controllers\Teacher\LiveSessionController::class, 'index'])->name('live-sessions');
    Route::post('/live-sessions',              [App\Http\Controllers\Teacher\LiveSessionController::class, 'store'])->name('live-sessions.store');
    Route::patch('/live-sessions/{id}/status', [App\Http\Controllers\Teacher\LiveSessionController::class, 'updateStatus'])->name('live-sessions.status');
    Route::delete('/live-sessions/{id}',       [App\Http\Controllers\Teacher\LiveSessionController::class, 'destroy'])->name('live-sessions.destroy');

    // Teaching assignments, groups, and private availability
    Route::get('/teaching-schedule',                              [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'index'])->name('teaching-schedule');
    Route::post('/teaching-schedule/assignments',                 [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'storeAssignment'])->name('teaching-schedule.assignments.store');
    Route::post('/teaching-schedule/groups',                      [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'storeGroup'])->name('teaching-schedule.groups.store');
    Route::delete('/teaching-schedule/groups/{id}',               [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'destroyGroup'])->name('teaching-schedule.groups.destroy');
    Route::post('/teaching-schedule/groups/{id}/lessons',         [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'storeGroupLesson'])->name('teaching-schedule.groups.lessons.store');
    Route::post('/teaching-schedule/group-lessons/{id}/schedule', [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'scheduleGroupLesson'])->name('teaching-schedule.group-lessons.schedule');
    Route::get('/teaching-schedule/group-lessons/{id}/schedule',  [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'redirectGroupLessonSchedule'])->name('teaching-schedule.group-lessons.schedule-link');
    Route::post('/teaching-schedule/private-slots',               [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'storePrivateSlot'])->name('teaching-schedule.private-slots.store');
    Route::delete('/teaching-schedule/private-slots/{id}',        [App\Http\Controllers\Teacher\TeachingScheduleController::class, 'destroyPrivateSlot'])->name('teaching-schedule.private-slots.destroy');

    // Worksheets & Grading
    Route::get('/groups/{groupId}/worksheets',      [App\Http\Controllers\Teacher\WorksheetController::class, 'index'])->name('worksheets.index');
    Route::post('/groups/{groupId}/worksheets',     [App\Http\Controllers\Teacher\WorksheetController::class, 'store'])->name('worksheets.store');
    Route::post('/worksheets/grade/{submissionId}', [App\Http\Controllers\Teacher\WorksheetController::class, 'gradeSubmission'])->name('worksheets.grade');
    Route::delete('/worksheets/{id}',               [App\Http\Controllers\Teacher\WorksheetController::class, 'destroy'])->name('worksheets.destroy');
});

// ─── Admin Routes ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',               [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    // Polled by the dashboard so its figures stay live without a page reload.
    Route::get('/dashboard/stats',         [App\Http\Controllers\Admin\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('/users',                   [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users');
    Route::post('/users',                  [App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{id}/toggle',     [App\Http\Controllers\Admin\UserController::class, 'toggleActive'])->name('users.toggle');
    Route::patch('/users/{id}/role',       [App\Http\Controllers\Admin\UserController::class, 'updateRole'])->name('users.role');
    Route::patch('/users/{id}/commission', [App\Http\Controllers\Admin\UserController::class, 'updateCommission'])->name('users.commission');

    // Subscriptions replace the old course catalogue
    Route::get('/subscriptions',         [App\Http\Controllers\Admin\SubscriptionController::class, 'index'])->name('subscriptions');
    Route::delete('/subscriptions/{id}', [App\Http\Controllers\Admin\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // Teaching groups — oversight across every teacher
    Route::get('/teaching-groups',              [App\Http\Controllers\Admin\TeachingGroupController::class, 'index'])->name('teaching-groups');
    Route::get('/teaching-groups/{id}',         [App\Http\Controllers\Admin\TeachingGroupController::class, 'show'])->name('teaching-groups.show');
    Route::patch('/teaching-groups/{id}/toggle', [App\Http\Controllers\Admin\TeachingGroupController::class, 'toggle'])->name('teaching-groups.toggle');

    // Review moderation — nothing reaches a teacher's profile without it
    Route::get('/reviews',              [App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews');
    Route::post('/reviews/approve-all', [App\Http\Controllers\Admin\ReviewController::class, 'approveAll'])->name('reviews.approve-all');
    Route::patch('/reviews/{id}/approve', [App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('reviews.approve');
    Route::patch('/reviews/{id}/reject',  [App\Http\Controllers\Admin\ReviewController::class, 'reject'])->name('reviews.reject');
    Route::delete('/reviews/{id}',        [App\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Academic calendar
    Route::get('/academic-terms',         [App\Http\Controllers\Admin\AcademicTermController::class, 'index'])->name('academic-terms');
    Route::post('/academic-terms',        [App\Http\Controllers\Admin\AcademicTermController::class, 'store'])->name('academic-terms.store');
    Route::put('/academic-terms/{id}',    [App\Http\Controllers\Admin\AcademicTermController::class, 'update'])->name('academic-terms.update');
    Route::delete('/academic-terms/{id}', [App\Http\Controllers\Admin\AcademicTermController::class, 'destroy'])->name('academic-terms.destroy');

    Route::get('/payments',                    [App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments');
    Route::post('/payments/{payment}/approve', [App\Http\Controllers\Admin\PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject',  [App\Http\Controllers\Admin\PaymentController::class, 'reject'])->name('payments.reject');
    Route::get('/payments/{payment}/receipt',  [App\Http\Controllers\Admin\PaymentController::class, 'receipt'])->name('payments.receipt');

    Route::get('/settings',         [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings',        [App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{id}', [App\Http\Controllers\Admin\SettingsController::class, 'destroy'])->name('settings.destroy');

    Route::get('/site-pages',  [App\Http\Controllers\Admin\SettingsController::class, 'sitePages'])->name('site-pages');
    Route::post('/site-pages', [App\Http\Controllers\Admin\SettingsController::class, 'updateSitePages'])->name('site-pages.update');

    // Subjects
    Route::get('/subjects',         [App\Http\Controllers\Admin\SubjectController::class, 'index'])->name('subjects');
    Route::post('/subjects',        [App\Http\Controllers\Admin\SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{id}',    [App\Http\Controllers\Admin\SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{id}', [App\Http\Controllers\Admin\SubjectController::class, 'destroy'])->name('subjects.destroy');

    // Payouts
    Route::get('/payouts',              [App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts',             [App\Http\Controllers\Admin\PayoutController::class, 'store'])->name('payouts.store');
    Route::post('/payouts/{id}/pay',    [App\Http\Controllers\Admin\PayoutController::class, 'markAsPaid'])->name('payouts.pay');
    Route::get('/payouts/{id}/receipt', [App\Http\Controllers\Admin\PayoutController::class, 'receipt'])->name('payouts.receipt');
    Route::delete('/payouts/{id}',      [App\Http\Controllers\Admin\PayoutController::class, 'destroy'])->name('payouts.destroy');

    // Coupons
    Route::get('/coupons',               [App\Http\Controllers\Admin\CouponController::class, 'index'])->name('coupons');
    Route::post('/coupons',              [App\Http\Controllers\Admin\CouponController::class, 'store'])->name('coupons.store');
    Route::patch('/coupons/{id}/toggle', [App\Http\Controllers\Admin\CouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{id}',       [App\Http\Controllers\Admin\CouponController::class, 'destroy'])->name('coupons.destroy');

    // Grade Levels
    Route::get('/grade-levels',         [App\Http\Controllers\Admin\GradeLevelController::class, 'index'])->name('grade-levels');
    Route::post('/grade-levels',        [App\Http\Controllers\Admin\GradeLevelController::class, 'store'])->name('grade-levels.store');
    Route::get('/grade-levels/{id}',    [App\Http\Controllers\Admin\GradeLevelController::class, 'show'])->name('grade-levels.show');
    Route::put('/grade-levels/{id}',    [App\Http\Controllers\Admin\GradeLevelController::class, 'update'])->name('grade-levels.update');
    Route::delete('/grade-levels/{id}', [App\Http\Controllers\Admin\GradeLevelController::class, 'destroy'])->name('grade-levels.destroy');
});

// ─── Parent Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/dashboard',                      [App\Http\Controllers\Parent\ParentDashboardController::class, 'index'])->name('dashboard');
    Route::post('/link-student',                  [App\Http\Controllers\Parent\ParentDashboardController::class, 'linkStudent'])->name('link-student');
    Route::delete('/unlink-student/{id}',         [App\Http\Controllers\Parent\ParentDashboardController::class, 'unlinkStudent'])->name('unlink-student');
    Route::post('/purchase-requests/{id}/pay',    [App\Http\Controllers\Parent\ParentDashboardController::class, 'payForRequest'])->name('purchase-requests.pay');
    Route::post('/purchase-requests/{id}/reject', [App\Http\Controllers\Parent\ParentPurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');
});

require __DIR__ . '/auth.php';

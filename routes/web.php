<?php

declare(strict_types=1);

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\TeacherProfileController;
use App\Http\Controllers\CheckoutController;
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

// ─── Public Routes ────────────────────────────────────────────────────────────
Route::get('/',                          [HomeController::class,           'index'])->name('home');
Route::get('/courses',                   [CourseController::class,         'index'])->name('courses.index');
Route::get('/courses/{slug}',            [CourseController::class,         'show'])->name('courses.show');
Route::get('/teachers/{id}',             [TeacherProfileController::class, 'show'])->name('teachers.show');
Route::get('/about',                     [HomeController::class, 'about'])->name('about');
Route::get('/contact',                   [HomeController::class, 'contact'])->name('contact');
Route::get('/our-apps',                  [HomeController::class, 'ourApps'])->name('our_apps');
Route::get('/students-results',          [HomeController::class, 'studentsResults'])->name('students_results');
Route::get('/api/search-autocomplete',   [CourseController::class,         'autocomplete'])->name('courses.autocomplete');

// ─── Authenticated Routes ──────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {

    // Profile
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Chat ─────────────────────────────────────────────────────────────────
    Route::get('/chat',                 [App\Http\Controllers\Communication\ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/start',          [App\Http\Controllers\Communication\ChatController::class, 'startConversation'])->name('chat.start');
    Route::post('/chat/send',           [App\Http\Controllers\Communication\ChatController::class, 'sendMessage'])->name('chat.send');
    Route::get('/chat/fetch',           [App\Http\Controllers\Communication\ChatController::class, 'fetchMessages'])->name('chat.fetch');

    // ─── Live Session Room ────────────────────────────────────────────────────
    Route::get('/live-sessions/{id}/room', [App\Http\Controllers\Course\LiveSessionRoomController::class, 'show'])->name('live-sessions.room');

    // ─── Notifications ────────────────────────────────────────────────────────
    Route::get('/notifications',          [App\Http\Controllers\Communication\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\Communication\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\Communication\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}',   [App\Http\Controllers\Communication\NotificationController::class, 'destroy'])->name('notifications.destroy');
});

// ─── Student Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // Enrollment
    Route::post('/courses/{slug}/enroll', [App\Http\Controllers\Student\EnrollController::class, 'store'])->name('student.enroll');
});

Route::middleware(['auth', 'verified', 'role:student'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/my-courses/{slug}/learn', [App\Http\Controllers\Student\LearnController::class, 'show'])->name('student.learn');
    Route::post('/my-courses/{slug}/lessons/{lessonId}/progress', [App\Http\Controllers\Student\LearnController::class, 'updateProgress'])->name('student.lesson.progress');
    Route::post('/my-courses/{slug}/worksheets/{worksheetId}/submit', [App\Http\Controllers\Student\LearnController::class, 'submitHomework'])->name('student.worksheets.submit');

    // Quiz
    Route::get('/quiz/{quizId}',           [App\Http\Controllers\Student\QuizController::class, 'show'])->name('student.quiz');
    Route::post('/quiz/{quizId}/start',    [App\Http\Controllers\Student\QuizController::class, 'start'])->name('student.quiz.start');
    Route::post('/quiz/{quizId}/submit',   [App\Http\Controllers\Student\QuizController::class, 'submit'])->name('student.quiz.submit');
    Route::post('/quiz/{quizId}/violation', [App\Http\Controllers\Student\QuizController::class, 'recordViolation'])->name('student.quiz.violation');

    // Certificate
    Route::get('/certificate/{enrollmentId}', [App\Http\Controllers\Student\CertificateController::class, 'show'])->name('student.certificate');

    // Reviews
    Route::post('/courses/{slug}/reviews',   [App\Http\Controllers\Student\ReviewController::class, 'store'])->name('student.review.store');
    Route::delete('/courses/{slug}/reviews', [App\Http\Controllers\Student\ReviewController::class, 'destroy'])->name('student.review.destroy');

    // Signed Video URL (never expose raw video_url to client)
    Route::get('/lessons/{lessonId}/video', [App\Http\Controllers\Student\VideoUrlController::class, 'getSignedUrl'])->name('student.video.url');
});

// Signed video stream endpoint (validated by Laravel signature)
Route::get('/stream/{lessonId}', [App\Http\Controllers\Student\VideoUrlController::class, 'stream'])
    ->name('video.stream')
    ->middleware('signed');

// ─── Checkout Routes ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout/success',        [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel',         [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::get('/checkout/{slug}',         [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{slug}',        [CheckoutController::class, 'process'])->name('checkout.process');
    Route::post('/checkout/coupon/check',  [CheckoutController::class, 'checkCoupon'])->name('checkout.coupon.check');
    Route::get('/checkout/mock-gateway/{ref}',             [CheckoutController::class, 'mockGateway'])->name('checkout.mock_gateway');
    Route::post('/checkout/mock-gateway/{ref}/complete',   [CheckoutController::class, 'mockComplete'])->name('checkout.mock_gateway.complete');
    Route::post('/checkout/mock-gateway/{ref}/cancel',     [CheckoutController::class, 'mockCancel'])->name('checkout.mock_gateway.cancel');
});

// ─── Webhook Routes (NO auth, NO CSRF) ────────────────────────────────────────
Route::withoutMiddleware(['web'])->group(function () {
    Route::post('/webhooks/stripe', [WebhookController::class, 'stripe'])->name('webhooks.stripe');
    Route::post('/webhooks/fatora', [WebhookController::class, 'fatora'])->name('webhooks.fatora');
});

// ─── Teacher Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/dashboard',             [App\Http\Controllers\Teacher\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/courses',               [App\Http\Controllers\Teacher\CourseManagerController::class, 'index'])->name('courses');
    Route::get('/courses/create',        [App\Http\Controllers\Teacher\CourseManagerController::class, 'create'])->name('courses.create');
    Route::post('/courses',              [App\Http\Controllers\Teacher\CourseManagerController::class, 'store'])->name('courses.store');
    Route::get('/courses/{id}/edit',     [App\Http\Controllers\Teacher\CourseManagerController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{id}',          [App\Http\Controllers\Teacher\CourseManagerController::class, 'update'])->name('courses.update');
    Route::get('/courses/{id}/lessons',  [App\Http\Controllers\Teacher\LessonManagerController::class, 'index'])->name('lessons');
    Route::post('/courses/{id}/lessons', [App\Http\Controllers\Teacher\LessonManagerController::class, 'store'])->name('lessons.store');
    Route::delete('/lessons/{id}',       [App\Http\Controllers\Teacher\LessonManagerController::class, 'destroy'])->name('lessons.destroy');
    
    // Live Sessions
    Route::get('/live-sessions',                     [App\Http\Controllers\Teacher\LiveSessionController::class, 'index'])->name('live-sessions');
    Route::post('/live-sessions',                    [App\Http\Controllers\Teacher\LiveSessionController::class, 'store'])->name('live-sessions.store');
    Route::patch('/live-sessions/{id}/status',       [App\Http\Controllers\Teacher\LiveSessionController::class, 'updateStatus'])->name('live-sessions.status');
    Route::delete('/live-sessions/{id}',             [App\Http\Controllers\Teacher\LiveSessionController::class, 'destroy'])->name('live-sessions.destroy');

    // Worksheets & Grading
    Route::get('/courses/{id}/worksheets',           [App\Http\Controllers\Teacher\WorksheetController::class, 'index'])->name('worksheets.index');
    Route::post('/courses/{id}/worksheets',          [App\Http\Controllers\Teacher\WorksheetController::class, 'store'])->name('worksheets.store');
    Route::post('/worksheets/grade/{submissionId}',  [App\Http\Controllers\Teacher\WorksheetController::class, 'gradeSubmission'])->name('worksheets.grade');
    Route::delete('/worksheets/{id}',                [App\Http\Controllers\Teacher\WorksheetController::class, 'destroy'])->name('worksheets.destroy');
});

// ─── Admin Routes ─────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',              [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/users',                  [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users');
    Route::patch('/users/{id}/toggle',    [App\Http\Controllers\Admin\UserController::class, 'toggleActive'])->name('users.toggle');
    Route::patch('/users/{id}/role',      [App\Http\Controllers\Admin\UserController::class, 'updateRole'])->name('users.role');
    Route::get('/courses',                [App\Http\Controllers\Admin\CourseController::class, 'index'])->name('courses');
    Route::patch('/courses/{id}/publish', [App\Http\Controllers\Admin\CourseController::class, 'togglePublish'])->name('courses.toggle');
    Route::get('/payments',               [App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments');
    Route::get('/settings',               [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Route::post('/settings',              [App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/{id}',       [App\Http\Controllers\Admin\SettingsController::class, 'destroy'])->name('settings.destroy');

    Route::get('/site-pages',             [App\Http\Controllers\Admin\SettingsController::class, 'sitePages'])->name('site-pages');
    Route::post('/site-pages',            [App\Http\Controllers\Admin\SettingsController::class, 'updateSitePages'])->name('site-pages.update');

    // Subjects
    Route::get('/subjects',               [App\Http\Controllers\Admin\SubjectController::class, 'index'])->name('subjects');
    Route::post('/subjects',              [App\Http\Controllers\Admin\SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{id}',          [App\Http\Controllers\Admin\SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{id}',       [App\Http\Controllers\Admin\SubjectController::class, 'destroy'])->name('subjects.destroy');

    // Payouts
    Route::get('/payouts',                [App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts',               [App\Http\Controllers\Admin\PayoutController::class, 'store'])->name('payouts.store');
    Route::post('/payouts/{id}/pay',      [App\Http\Controllers\Admin\PayoutController::class, 'markAsPaid'])->name('payouts.pay');
    Route::delete('/payouts/{id}',        [App\Http\Controllers\Admin\PayoutController::class, 'destroy'])->name('payouts.destroy');

    // Coupons
    Route::get('/coupons',                [App\Http\Controllers\Admin\CouponController::class, 'index'])->name('coupons');
    Route::post('/coupons',               [App\Http\Controllers\Admin\CouponController::class, 'store'])->name('coupons.store');
    Route::patch('/coupons/{id}/toggle',  [App\Http\Controllers\Admin\CouponController::class, 'toggle'])->name('coupons.toggle');
    Route::delete('/coupons/{id}',        [App\Http\Controllers\Admin\CouponController::class, 'destroy'])->name('coupons.destroy');

    // Grade Levels
    Route::get('/grade-levels',               [App\Http\Controllers\Admin\GradeLevelController::class, 'index'])->name('grade-levels');
    Route::post('/grade-levels',              [App\Http\Controllers\Admin\GradeLevelController::class, 'store'])->name('grade-levels.store');
    Route::get('/grade-levels/{id}',          [App\Http\Controllers\Admin\GradeLevelController::class, 'show'])->name('grade-levels.show');
    Route::put('/grade-levels/{id}',          [App\Http\Controllers\Admin\GradeLevelController::class, 'update'])->name('grade-levels.update');
    Route::delete('/grade-levels/{id}',       [App\Http\Controllers\Admin\GradeLevelController::class, 'destroy'])->name('grade-levels.destroy');
});

// ─── Parent Routes ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/dashboard',              [App\Http\Controllers\Parent\ParentDashboardController::class, 'index'])->name('dashboard');
    Route::post('/link-student',          [App\Http\Controllers\Parent\ParentDashboardController::class, 'linkStudent'])->name('link-student');
    Route::delete('/unlink-student/{id}', [App\Http\Controllers\Parent\ParentDashboardController::class, 'unlinkStudent'])->name('unlink-student');
});

require __DIR__ . '/auth.php';

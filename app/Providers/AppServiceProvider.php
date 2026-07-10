<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Infrastructure\Observers\CourseLessonObserver;
use App\Infrastructure\Observers\CourseObserver;
use App\Infrastructure\Observers\EnrollmentObserver;
use App\Infrastructure\Observers\PaymentObserver;
use App\Infrastructure\Observers\UserObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Use this for binding interfaces to implementations (DIP principle).
     */
    public function register(): void
    {
        // ─── Repository Bindings (Dependency Inversion Principle) ─────────────
        $this->app->bind(
            \App\Domain\Course\Contracts\CourseRepositoryInterface::class,
            \App\Infrastructure\Persistence\Eloquent\EloquentCourseRepository::class,
        );

        // ─── Service Bindings ─────────────────────────────────────────────────
        $this->app->bind(
            \App\Domain\Enrollment\Contracts\EnrollmentServiceInterface::class,
            \App\Application\Enrollment\Services\EnrollmentService::class,
        );

        // Singletons — stateless services that can be safely shared
        $this->app->singleton(\App\Application\Quiz\Services\QuizService::class);
        $this->app->singleton(\App\Application\Certificate\Services\CertificateService::class);

        // ─── Payment Gateway (Strategy Pattern) ──────────────────────────────
        // Switch gateway by changing this binding only — controllers unaffected
        $this->app->bind(
            \App\Infrastructure\Payment\PaymentGatewayInterface::class,
            \App\Infrastructure\Payment\Gateways\StripeGateway::class,
        );

        $this->app->singleton(\App\Application\Payment\Services\PaymentService::class);
    }

    /**
     * Bootstrap any application services.
     * Register Model Observers here (Observer Pattern).
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // ─── Model Observers ─────────────────────────────────────────
        // These separate side-effects from business logic (SRP + Observer Pattern)
        User::observe(UserObserver::class);
        Course::observe(CourseObserver::class);
        CourseLesson::observe(CourseLessonObserver::class);
        Enrollment::observe(EnrollmentObserver::class);
        Payment::observe(PaymentObserver::class);
    }
}

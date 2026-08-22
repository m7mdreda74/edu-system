<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Certificate\Services\CertificateService;
use App\Application\Payment\Services\PaymentService;
use App\Application\Quiz\Services\QuizService;
use App\Application\Scheduling\Services\SessionBookingService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Learning\Models\GroupMaterial;
use App\Services\AuditLogger;
use App\Policies\MaterialPolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QuizService::class);
        $this->app->singleton(CertificateService::class);
        $this->app->singleton(SessionBookingService::class);
        $this->app->singleton(SubscriptionService::class);

        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(GroupMaterial::class, MaterialPolicy::class);

        Event::listen(Login::class, function (Login $event): void {
            AuditLogger::record('auth.login', $event->user, [], $event->user);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            AuditLogger::record('auth.logout', $event->user, [], $event->user);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            AuditLogger::record('auth.login_failed', null, [
                'email_hash' => AuditLogger::hashValue($event->credentials['email'] ?? null),
            ]);
        });
    }
}

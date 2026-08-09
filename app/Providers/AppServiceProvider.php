<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Certificate\Services\CertificateService;
use App\Application\Payment\Services\PaymentService;
use App\Application\Quiz\Services\QuizService;
use App\Application\Scheduling\Services\SessionBookingService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Learning\Models\GroupMaterial;
use App\Policies\MaterialPolicy;
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
    }
}

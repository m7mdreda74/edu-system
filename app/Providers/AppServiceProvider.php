<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Certificate\Services\CertificateService;
use App\Application\Payment\Services\PaymentService;
use App\Application\Quiz\Services\QuizService;
use App\Application\Scheduling\Services\SessionBookingService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Settings\Models\PlatformSetting;
use App\Infrastructure\Payment\Gateways\FatoraGateway;
use App\Infrastructure\Payment\Gateways\StripeGateway;
use App\Infrastructure\Payment\Gateways\TapGateway;
use App\Infrastructure\Payment\PaymentGatewayInterface;
use App\Policies\MaterialPolicy;
use Illuminate\Contracts\Foundation\Application;
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

        $this->app->bind(
            PaymentGatewayInterface::class,
            function (Application $app): PaymentGatewayInterface {
                $gatewayName = PlatformSetting::where('key', 'active_gateway')->value('value')
                    ?: config('services.payment.gateway', 'fatora');

                return match (strtolower($gatewayName)) {
                    'tap' => $app->make(TapGateway::class),
                    'stripe' => $app->make(StripeGateway::class),
                    default => $app->make(FatoraGateway::class),
                };
            },
        );

        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(GroupMaterial::class, MaterialPolicy::class);
    }
}

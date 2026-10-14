<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Certificate\Services\CertificateService;
use App\Application\Payment\Services\PaymentService;
use App\Application\Quiz\Services\QuizService;
use App\Application\Scheduling\Services\SessionBookingService;
use App\Application\Subscription\Services\SubscriptionService;
use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LearningResource;
use App\Domain\User\Models\User;
use App\Infrastructure\Payment\Gateways\FatoraGateway;
use App\Infrastructure\Payment\Gateways\SkipCashGateway;
use App\Infrastructure\Payment\Gateways\StripeGateway;
use App\Infrastructure\Payment\Gateways\TapGateway;
use App\Infrastructure\Payment\PaymentGatewayInterface;
use App\Infrastructure\Video\CloudflareStreamVideoProvider;
use App\Infrastructure\Video\VideoProviderInterface;
use App\Policies\LearningResourcePolicy;
use App\Policies\MaterialPolicy;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Notifications\Events\NotificationSending;
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

        $this->app->singleton(PaymentGatewayInterface::class, function () {
            $gateway = config('services.payment.gateway', 'skipcash');

            return match ($gateway) {
                'skipcash' => new SkipCashGateway,
                'fatora' => new FatoraGateway,
                'stripe' => new StripeGateway,
                'tap' => new TapGateway,
                default => new SkipCashGateway,
            };
        });

        $this->app->singleton(VideoProviderInterface::class, CloudflareStreamVideoProvider::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::policy(GroupMaterial::class, MaterialPolicy::class);
        Gate::policy(LearningResource::class, LearningResourcePolicy::class);

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

        Event::listen(NotificationSending::class, function (NotificationSending $event): ?bool {
            if (! $event->notifiable instanceof User) {
                return null;
            }

            $type = NotificationPreference::typeFor($event->notification, $event->notifiable);

            return NotificationPreference::enabledFor($event->notifiable, $type, $event->channel)
                ? null
                : false;
        });
    }
}

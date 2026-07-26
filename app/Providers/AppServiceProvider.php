<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Infrastructure\Observers\PaymentObserver;
use App\Infrastructure\Observers\UserObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
        // ─── Service Bindings ─────────────────────────────────────────────────
        // Singletons — stateless services that can be safely shared
        $this->app->singleton(\App\Application\Quiz\Services\QuizService::class);
        $this->app->singleton(\App\Application\Certificate\Services\CertificateService::class);
        $this->app->singleton(\App\Application\Scheduling\Services\SessionBookingService::class);
        $this->app->singleton(\App\Application\Subscription\Services\SubscriptionService::class);

        // ─── Payment Gateway (Strategy Pattern) ──────────────────────────────
        // Switch gateway by changing this binding only — controllers unaffected
        $this->app->bind(
            \App\Infrastructure\Payment\PaymentGatewayInterface::class,
            function ($app) {
                // Read active gateway from DB settings
                $gatewayName = \App\Domain\Settings\Models\PlatformSetting::where('key', 'active_gateway')->value('value')
                    ?: config('services.payment.gateway', 'fatora');

                return match (strtolower($gatewayName)) {
                    'tap'    => $app->make(\App\Infrastructure\Payment\Gateways\TapGateway::class),
                    'stripe' => $app->make(\App\Infrastructure\Payment\Gateways\StripeGateway::class),
                    default  => $app->make(\App\Infrastructure\Payment\Gateways\FatoraGateway::class),
                };
            }
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
        Payment::observe(PaymentObserver::class);

        // Register Gates & Policies explicitly
        Gate::policy(GroupMaterial::class, \App\Policies\MaterialPolicy::class);

        // ─── Auto-migrate WebRTC tables if missing (Vercel serverless safe) ──
        // Runs only once per cold start; negligible overhead after first check.
        $this->bootWebRtcTables();
    }

    /**
     * Ensure WebRTC signaling tables exist (safe for serverless / Vercel).
     * Uses Schema::hasTable() guard so it only creates if missing.
     */
    private function bootWebRtcTables(): void
    {
        try {
            if (! Schema::hasTable('webrtc_signals')) {
                Schema::create('webrtc_signals', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('live_session_id');
                    $table->unsignedBigInteger('from_user_id');
                    $table->unsignedBigInteger('to_user_id')->nullable();
                    $table->string('type', 30);
                    $table->json('payload');
                    $table->timestamp('created_at')->useCurrent()->index();
                });
            }

            if (! Schema::hasTable('webrtc_participants')) {
                Schema::create('webrtc_participants', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('live_session_id');
                    $table->unsignedBigInteger('user_id');
                    $table->string('name');
                    $table->boolean('is_teacher')->default(false);
                    $table->timestamp('last_seen_at')->index();
                    $table->timestamps();
                    $table->unique(['live_session_id', 'user_id']);
                });
            }
        } catch (\Throwable $e) {
            // Fail silently — DB might not be available during build phase
        }
    }
}

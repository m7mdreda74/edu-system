<?php

use App\Application\Learning\Services\LiveSessionReminderService;
use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'subscriptions:send-renewal-reminders',
    function (SubscriptionRenewalReminderService $reminders): void {
        $count = $reminders->sendDueReminders();

        $this->info("Sent {$count} subscription renewal reminder(s).");
    },
)->purpose('Notify students and parents before the final class in a subscription period');

Artisan::command(
    'sessions:send-reminders',
    function (LiveSessionReminderService $reminders): void {
        $count = $reminders->sendDueReminders();

        $this->info("Sent {$count} live session reminder(s).");
    },
)->purpose('Notify students up to 24 hours before every scheduled class');

Artisan::command('audit:production-readiness', function (): int {
    $checks = [];
    $check = function (string $name, bool $passed, string $detail) use (&$checks): void {
        $checks[] = [$passed ? 'PASS' : 'FAIL', $name, $detail];
    };

    $check('APP_ENV', config('app.env') === 'production', 'must be production');
    $check('APP_DEBUG', config('app.debug') === false, 'must be false');
    $check('APP_KEY', filled(config('app.key')), 'configured without displaying its value');
    $check('CRON_SECRET', filled(config('services.cron.secret')), 'configured without displaying its value');

    $jitsiDomain = strtolower(trim((string) config('services.jitsi.domain')));
    $jitsiPrivate = (bool) config('services.jitsi.require_auth');
    $check('Jitsi domain', $jitsiDomain !== '' && $jitsiDomain !== 'meet.jit.si', 'use the approved production Jitsi host');
    $check('Jitsi authentication', $jitsiPrivate && filled(config('services.jitsi.app_id')) && filled(config('services.jitsi.app_secret')), 'JWT authentication and credentials are required');
    $check('Jitsi file recordings', (bool) config('services.jitsi.recording.enabled'), 'server-side recording must be enabled');
    $check('Jitsi automatic recording', (bool) config('services.jitsi.recording.auto_start'), 'automatic recording must be enabled');
    $check('Recording allowed hosts', count(config('services.jitsi.recording.allowed_hosts', [])) > 0, 'configure HTTPS recording hosts');

    if ($jitsiDomain !== '' && $jitsiDomain !== 'meet.jit.si') {
        $check('Jitsi whiteboard backend', filled(config('services.jitsi.whiteboard.collab_server_base_url')), 'configure the collaboration backend');
    }

    if (config('services.turnstile.enabled')) {
        $check('Turnstile', filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key')), 'site and secret keys are required when enabled');
    }

    foreach (['.env', 'altafawwuq.zip', 'database/database.sqlite', 'storage/logs/laravel.log'] as $artifact) {
        $check("Artifact {$artifact}", ! is_file(base_path($artifact)), 'must not be present in the deployment workspace');
    }

    try {
        $check('audit_events table', Schema::hasTable('audit_events'), 'migration must be applied');
        $check('payments receipt hash', Schema::hasColumn('payments', 'receipt_sha256'), 'payment integrity migration must be applied');
        $check('payments teacher snapshot', Schema::hasColumn('payments', 'teacher_id'), 'payment integrity migration must be applied');
    } catch (\Throwable) {
        $check('database connection', false, 'could not inspect the configured database');
    }

    $this->table(['Status', 'Check', 'Requirement'], $checks);
    $failures = collect($checks)->where('0', 'FAIL')->count();

    if ($failures > 0) {
        $this->error("Production readiness failed: {$failures} check(s) need attention.");

        return 1;
    }

    $this->info('Production readiness checks passed.');

    return 0;
})->purpose('Check production security, Jitsi, artifact, and migration readiness without printing secrets');

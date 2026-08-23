<?php

use App\Application\Learning\Services\LiveSessionReminderService;
use App\Application\Subscription\Services\SubscriptionRenewalReminderService;
use App\Domain\Payment\Models\Payment;
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

    $artifactPaths = [
        base_path('.env'),
    ];

    foreach (glob(base_path('.env.*')) ?: [] as $artifactPath) {
        if (basename($artifactPath) !== '.env.example') {
            $artifactPaths[] = $artifactPath;
        }
    }

    foreach (glob(base_path('*.zip')) ?: [] as $artifactPath) {
        $artifactPaths[] = $artifactPath;
    }

    foreach (glob(base_path('database/*.sqlite*')) ?: [] as $artifactPath) {
        $artifactPaths[] = $artifactPath;
    }

    foreach (glob(base_path('storage/logs/*.log*')) ?: [] as $artifactPath) {
        $artifactPaths[] = $artifactPath;
    }

    foreach (array_unique($artifactPaths) as $artifactPath) {
        $artifact = ltrim(str_replace('\\', '/', str_replace(base_path(), '', $artifactPath)), '/');
        $check("Artifact {$artifact}", ! is_file($artifactPath), 'must not be present in the deployment workspace');
    }

    try {
        $check('audit_events table', Schema::hasTable('audit_events'), 'migration must be applied');
        $check('payments receipt hash', Schema::hasColumn('payments', 'receipt_sha256'), 'payment integrity migration must be applied');
        $check('payments teacher snapshot', Schema::hasColumn('payments', 'teacher_id'), 'payment integrity migration must be applied');
        $check('payments idempotency key', Schema::hasColumn('payments', 'idempotency_key'), 'payment idempotency migration must be applied');
        $paymentIndexes = collect(Schema::getIndexes('payments'))->pluck('name');
        $requiredPaymentIndexes = collect([
            'idx_payments_status_created',
            'idx_payments_user_status_created',
            'idx_payments_teacher_payout_date',
        ]);
        $check(
            'payments query indexes',
            $requiredPaymentIndexes->diff($paymentIndexes)->isEmpty(),
            'payment query index migration must be applied',
        );
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

Artisan::command('audit:payment-reconciliation', function (): int {
    $issues = [];
    $paidCount = 0;
    $grossAmount = 0;
    $teacherEarnings = 0;
    $platformCommission = 0;

    $recordIssue = static function (string $check, int $paymentId) use (&$issues): void {
        $issues[$check]['count'] = ($issues[$check]['count'] ?? 0) + 1;
        $issues[$check]['sample_ids'] ??= [];

        if (count($issues[$check]['sample_ids']) < 10) {
            $issues[$check]['sample_ids'][] = $paymentId;
        }
    };

    try {
        Payment::query()
            ->with('invoice:id,payment_id')
            ->where('status', Payment::STATUS_PAID)
            ->orderBy('id')
            ->chunkById(500, function ($payments) use (
                &$paidCount,
                &$grossAmount,
                &$teacherEarnings,
                &$platformCommission,
                $recordIssue,
            ): void {
                foreach ($payments as $payment) {
                    $paidCount++;
                    $amount = (int) $payment->amount;
                    $grossAmount += $amount;

                    if ($payment->teacher_id === null) {
                        $recordIssue('missing teacher snapshot', (int) $payment->id);
                    }

                    if ($payment->commission_percent === null) {
                        $recordIssue('missing commission snapshot', (int) $payment->id);
                    }

                    if ($payment->platform_commission_amount === null || $payment->teacher_earnings === null) {
                        $recordIssue('missing payment split', (int) $payment->id);
                        continue;
                    }

                    $platformAmount = (int) $payment->platform_commission_amount;
                    $earnings = (int) $payment->teacher_earnings;
                    $platformCommission += $platformAmount;
                    $teacherEarnings += $earnings;

                    if ($platformAmount < 0 || $earnings < 0 || $platformAmount + $earnings !== $amount) {
                        $recordIssue('unbalanced payment split', (int) $payment->id);
                    }

                    if (! $payment->invoice) {
                        $recordIssue('missing invoice', (int) $payment->id);
                    }
                }
            });
    } catch (\Throwable $exception) {
        $this->error('Payment reconciliation could not inspect the configured database.');

        return 1;
    }

    $this->table(
        ['Metric', 'Value'],
        [
            ['Paid payments', $paidCount],
            ['Gross amount', $grossAmount],
            ['Teacher earnings', $teacherEarnings],
            ['Platform commission', $platformCommission],
            ['Total issues', array_sum(array_column($issues, 'count'))],
        ],
    );

    if ($issues !== []) {
        $rows = [];

        foreach ($issues as $check => $issue) {
            $rows[] = [$check, $issue['count'], implode(', ', $issue['sample_ids'])];
        }

        $this->table(['Check', 'Count', 'Sample payment IDs'], $rows);
        $this->error('Payment reconciliation failed; review the report before releasing payouts.');

        return 1;
    }

    $this->info('Payment reconciliation passed for all paid payments.');

    return 0;
})->purpose('Read-only reconciliation of paid payment snapshots, splits, and invoices');

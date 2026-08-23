<?php

declare(strict_types=1);

/**
 * Read-only EXPLAIN report for the highest-volume application queries.
 *
 * Usage: php scripts/explain-queries.php
 */
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$queries = [
    'admin_pending_payments' => <<<'SQL'
        EXPLAIN
        SELECT id
        FROM payments
        WHERE status = 'pending_verification'
        ORDER BY created_at DESC
        LIMIT 10
    SQL,
    'student_payment_history' => <<<'SQL'
        EXPLAIN
        SELECT id
        FROM payments
        WHERE user_id = 1
          AND status = 'pending_verification'
        ORDER BY created_at DESC
    SQL,
    'payout_balance' => <<<'SQL'
        EXPLAIN
        SELECT teacher_id, SUM(amount)
        FROM payments
        WHERE teacher_id = 1
          AND status = 'paid'
          AND teacher_payout_id IS NULL
          AND paid_at BETWEEN '2026-01-01 00:00:00' AND '2026-12-31 23:59:59'
        GROUP BY teacher_id
    SQL,
    'teacher_search' => <<<'SQL'
        EXPLAIN
        SELECT id, name, headline
        FROM users
        WHERE is_active = 1
          AND (name LIKE '%math%' OR headline LIKE '%math%')
        LIMIT 6
    SQL,
    'live_session_schedule' => <<<'SQL'
        EXPLAIN
        SELECT id
        FROM live_sessions
        WHERE status = 'scheduled'
        ORDER BY scheduled_at ASC
        LIMIT 10
    SQL,
];

$failed = false;

foreach ($queries as $name => $sql) {
    echo PHP_EOL.$name.PHP_EOL;

    try {
        foreach (Illuminate\Support\Facades\DB::select($sql) as $row) {
            echo json_encode((array) $row, JSON_UNESCAPED_SLASHES).PHP_EOL;
        }
    } catch (Throwable $exception) {
        $failed = true;
        fwrite(STDERR, "Unable to explain {$name}: ".get_class($exception).PHP_EOL);
    }
}

exit($failed ? 1 : 0);

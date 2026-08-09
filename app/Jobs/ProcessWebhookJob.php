<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ProcessWebhookJob — handles Stripe webhook payload asynchronously.
 *
 * MUST be idempotent: running this job twice must produce the same result.
 * ShouldBeUnique: ensures only one job runs per gateway_ref at a time.
 * This prevents double-enrollment if a webhook is delivered twice.
 */
class ProcessWebhookJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries        = 3;       // Retry up to 3 times
    public int $backoff      = 60;      // Wait 60s between retries
    public int $uniqueFor    = 300;     // Lock for 5 minutes

    public function __construct(
        private readonly string $payload,
        private readonly string $signature,
    ) {}

    /**
     * Unique key prevents duplicate processing for same webhook signature.
     */
    public function uniqueId(): string
    {
        return md5($this->signature);
    }

    public function handle(): void
    {
        // Legacy online-payment jobs are intentionally ignored. Manual
        // transfers are completed only by an admin review action.
        return;
    }
}

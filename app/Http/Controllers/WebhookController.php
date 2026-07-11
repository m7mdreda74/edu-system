<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Payment\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WebhookController — Receives payment gateway callbacks.
 *
 * Critical security rules:
 *  1. Signature verified BEFORE any processing
 *  2. All processing is idempotent (safe to receive twice)
 *  3. Returns 200 quickly — no heavy logic inline (use Jobs in production)
 *  4. CSRF exempted (gateway can't get our token)
 */
class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function stripe(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        // Step 1: Verify signature — reject unsigned webhooks
        if (! $this->paymentService->getGateway()->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Stripe webhook: invalid signature', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        // Step 2: Process idempotently — log errors but return 200
        // (Gateway will retry on non-200; we don't want infinite retries for bugs)
        try {
            $this->paymentService->processWebhookEvent($payload);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing error', [
                'error'   => $e->getMessage(),
                'payload' => substr($payload, 0, 500),
            ]);
        }

        return response('OK', 200);
    }

    public function fatora(Request $request): Response
    {
        $payload = $request->getContent();

        if (! $this->paymentService->getGateway()->verifyWebhookSignature($payload, '')) {
            Log::warning('Fatora webhook: invalid authorization', ['ip' => $request->ip()]);
            return response('Unauthorized', 401);
        }

        try {
            $this->paymentService->processWebhookEvent($payload);
        } catch (\Throwable $e) {
            Log::error('Fatora webhook processing error', [
                'error'   => $e->getMessage(),
                'payload' => substr($payload, 0, 500),
            ]);
        }

        return response('OK', 200);
    }
}

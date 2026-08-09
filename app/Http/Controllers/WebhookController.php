<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    public function stripe(Request $request): Response
    {
        return response('Online payment webhooks are disabled.', 410);
    }

    public function fatora(Request $request): Response
    {
        return response('Online payment webhooks are disabled.', 410);
    }
}

<?php

declare(strict_types=1);

// Automatic production database mutations are forbidden by default.
// Production migrations require an explicit runbook execution with owner approval.
if (getenv('VERCEL_ENV') !== 'production') {
    exit(0);
}

if (getenv('ALLOW_PRODUCTION_MIGRATIONS') !== 'true') {
    fwrite(STDOUT, "Production migrations skipped: automated build migration is disabled by default. Set ALLOW_PRODUCTION_MIGRATIONS=true to execute with explicit approval.\n");
    exit(0);
}

passthru('php artisan migrate --force --no-interaction', $status);

exit($status);

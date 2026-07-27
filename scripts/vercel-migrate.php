<?php

declare(strict_types=1);

if (getenv('VERCEL_ENV') !== 'production') {
    exit(0);
}

passthru('php artisan migrate --force --no-interaction', $status);

exit($status);

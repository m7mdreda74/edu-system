<?php

declare(strict_types=1);

/**
 * Small dependency-free CI guard. It scans tracked files only and never prints
 * matching values, so a failed check does not become another secret leak.
 */
$files = [];
$exitCode = 0;
exec('git ls-files', $files, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Unable to enumerate tracked files.\n");
    exit(2);
}

$patterns = [
    '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
    '/\b(?:sk_live|rk_live|whsec|xox[baprs])_[A-Za-z0-9_-]{12,}\b/',
    '/^(?:APP_KEY|DB_PASSWORD|BLOB_READ_WRITE_TOKEN|CRON_SECRET|TURNSTILE_SECRET_KEY|STRIPE_SECRET|TAP_SECRET_KEY|FATORA_API_KEY|JITSI_APP_SECRET|MAIL_PASSWORD)=[ \t]*(?!\r?$|null(?:\r?$)|your[-_]|change[-_]|base64:\$)/mi',
];

$findings = [];

foreach ($files as $file) {
    if (! is_file($file) || filesize($file) > 5_000_000) {
        continue;
    }

    $contents = file_get_contents($file);
    if (! is_string($contents) || str_contains($contents, "\0")) {
        continue;
    }

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $findings[] = $file;
            break;
        }
    }
}

if ($findings !== []) {
    fwrite(STDERR, "Potential secrets found in tracked files:\n");
    foreach (array_unique($findings) as $file) {
        fwrite(STDERR, "- {$file}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Tracked-file secret scan passed.\n");

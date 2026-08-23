<?php

declare(strict_types=1);

/**
 * Inspect an archive's entry names without printing file contents.
 *
 * Usage: php scripts/scan-archive.php path/to/release.zip
 */
$archivePath = $argv[1] ?? '';

if ($archivePath === '' || ! is_file($archivePath)) {
    fwrite(STDERR, "Usage: php scripts/scan-archive.php path/to/archive.zip\n");
    exit(2);
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required to inspect archives.\n");
    exit(2);
}

$zip = new ZipArchive();

if ($zip->open($archivePath) !== true) {
    fwrite(STDERR, "Unable to open the archive for inspection.\n");
    exit(2);
}

$isSensitiveEntry = static function (string $entry): ?string {
    $entry = str_replace('\\', '/', $entry);
    $entry = preg_replace('#^(?:\./)+#', '', $entry) ?? $entry;
    $baseName = basename($entry);

    if (preg_match('/(?:^|\/)\.env(?:\.[^\/]+)?$/i', $entry) === 1 && $baseName !== '.env.example') {
        return 'environment file';
    }

    if (preg_match('/(?:^|\/)database\/[^\/]+\.sqlite(?:[^\/]*)?$/i', $entry) === 1) {
        return 'SQLite database';
    }

    if (preg_match('/(?:^|\/)storage\/logs\/[^\/]+\.log(?:[^\/]*)?$/i', $entry) === 1) {
        return 'application log';
    }

    if (preg_match('/(?:^|\/)storage\/app\/private(?:\/|$)/i', $entry) === 1) {
        return 'private uploaded file';
    }

    if (preg_match('/(?:^|\/)\.git(?:\/|$)/i', $entry) === 1) {
        return 'Git metadata';
    }

    return null;
};

$findings = [];

for ($index = 0; $index < $zip->numFiles; $index++) {
    $entry = $zip->getNameIndex($index);

    if (! is_string($entry)) {
        continue;
    }

    $category = $isSensitiveEntry($entry);

    if ($category !== null) {
        $findings[$category] = ($findings[$category] ?? 0) + 1;
    }
}

$zip->close();

if ($findings !== []) {
    fwrite(STDERR, "Sensitive archive entries detected:\n");

    foreach ($findings as $category => $count) {
        fwrite(STDERR, "- {$category}: {$count} entr" . ($count === 1 ? 'y' : 'ies') . "\n");
    }

    exit(1);
}

fwrite(STDOUT, "Archive artifact scan passed.\n");

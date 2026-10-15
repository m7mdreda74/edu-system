<?php

declare(strict_types=1);

/**
 * Creates a clean, audited release archive free of sensitive files.
 *
 * Excludes:
 * - Environment files (.env, .env.* except .env.example)
 * - SQLite databases (*.sqlite*)
 * - Private uploads (storage/app/private/*)
 * - Application logs (storage/logs/*)
 * - Git metadata (.git/*)
 * - Node modules & temporary files
 *
 * Usage: php scripts/package-release.php [output-filename.zip]
 */
$outputFile = $argv[1] ?? 'altafawwuq-release-clean.zip';

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(2);
}

$root = realpath(__DIR__.'/..');
if (! is_string($root)) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

echo "Enumerating clean project files via git ls-files...\n";
$files = [];
$exitCode = 0;
exec('git ls-files', $files, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "git ls-files failed.\n");
    exit(2);
}

// Add built assets if public/build exists
if (is_dir($root.'/public/build')) {
    $dirIter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/public/build', RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($dirIter as $f) {
        if ($f->isFile()) {
            $relPath = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
            $files[] = $relPath;
        }
    }
}

$zip = new ZipArchive;
if ($zip->open($root.'/'.$outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create zip file: {$outputFile}\n");
    exit(2);
}

$added = 0;
foreach (array_unique($files) as $file) {
    $fullPath = $root.'/'.$file;
    if (! is_file($fullPath)) {
        continue;
    }

    // Safety filter check
    $baseName = basename($file);
    if (
        (preg_match('/(?:^|\/)\.env(?:\.[^\/]+)?$/i', $file) === 1 && $baseName !== '.env.example')
        || preg_match('/(?:^|\/)database\/[^\/]+\.sqlite(?:[^\/]*)?$/i', $file) === 1
        || preg_match('/(?:^|\/)storage\/logs\/[^\/]+\.log(?:[^\/]*)?$/i', $file) === 1
        || preg_match('/(?:^|\/)storage\/app\/private(?:\/|$)/i', $file) === 1
        || preg_match('/(?:^|\/)\.git(?:\/|$)/i', $file) === 1
        || str_ends_with($file, '.zip')
    ) {
        continue;
    }

    $zip->addFile($fullPath, $file);
    $added++;
}

$zip->close();
echo "Successfully created clean archive: {$outputFile} with {$added} files.\n";

echo "Running verification scan with scripts/scan-archive.php...\n";
passthru('php scripts/scan-archive.php '.escapeshellarg($outputFile), $scanStatus);

if ($scanStatus !== 0) {
    fwrite(STDERR, "Verification scan failed!\n");
    exit(1);
}

echo "Clean release archive verified successfully.\n";

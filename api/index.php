<?php

declare(strict_types=1);

// Normalize SCRIPT_NAME on Vercel serverless so Symfony does not strip '/api' prefix from requests.
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__.'/../public/index.php';

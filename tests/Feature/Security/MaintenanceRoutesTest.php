<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

it('does not expose migration commands over HTTP', function (string $path): void {
    Artisan::shouldReceive('call')->never();

    $this->get($path.'?key=arbitrary-value')->assertNotFound();
})->with([
    '/maintenance/run-migrations',
    '/maintenance/migration-status',
]);

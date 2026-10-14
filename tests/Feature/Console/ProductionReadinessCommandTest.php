<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('reports the serverless queue cache and blob release gates without exposing secrets', function (): void {
    config()->set([
        'app.env' => 'production',
        'queue.default' => 'database',
        'queue.worker_confirmed' => false,
        'cache.default' => 'file',
        'services.vercel_blob.serverless' => true,
        'services.vercel_blob.store_id' => '',
        'services.vercel_blob.oidc_token' => '',
        'services.vercel_blob.token' => '',
    ]);

    $exitCode = Artisan::call('audit:production-readiness');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Queue connection')
        ->and($output)->toContain('Queue worker')
        ->and($output)->toContain('Shared cache')
        ->and($output)->toContain('GD image processing')
        ->and($output)->toContain('Vercel Blob store')
        ->and($output)->toContain('Vercel Blob credentials')
        ->and($output)->not->toContain('vercel_blob_rw_');
});

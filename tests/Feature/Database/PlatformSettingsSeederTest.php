<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('fills every missing demo Vodafone Cash number without replacing an admin setting', function (): void {
    $missing = GradeLevel::query()->orderBy('id')->firstOrFail();
    $configured = GradeLevel::query()->orderBy('id')->skip(1)->firstOrFail();

    $missing->update(['vodafone_cash_number' => null]);
    $configured->update(['vodafone_cash_number' => '01005556677']);

    $this->seed(PlatformSettingsSeeder::class);

    expect($missing->fresh()->vodafone_cash_number)
        ->toMatch('/^(?:\+20|0020|0)1\d{9}$/')
        ->and($configured->fresh()->vodafone_cash_number)->toBe('01005556677')
        ->and(GradeLevel::query()->whereNull('vodafone_cash_number')->count())->toBe(0);
});

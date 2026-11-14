<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('replaces only known demo contact details and preserves configured values', function (): void {
    $migration = require database_path('migrations/2026_11_14_000100_update_placeholder_contact_details.php');

    DB::table('platform_settings')->where('key', 'contact_phone')->update(['value' => '+974 4444 8888']);
    DB::table('platform_settings')->where('key', 'whatsapp_url')->update(['value' => 'https://wa.me/97455556666']);
    $migration->up();

    expect(DB::table('platform_settings')->where('key', 'contact_phone')->value('value'))->toBe('+974 7015 9941')
        ->and(DB::table('platform_settings')->where('key', 'whatsapp_url')->value('value'))->toBe('https://wa.me/97470159941');

    DB::table('platform_settings')->where('key', 'contact_phone')->update(['value' => '+974 1234 5678']);
    DB::table('platform_settings')->where('key', 'whatsapp_url')->update(['value' => 'https://wa.me/97412345678']);
    $migration->up();

    expect(DB::table('platform_settings')->where('key', 'contact_phone')->value('value'))->toBe('+974 1234 5678')
        ->and(DB::table('platform_settings')->where('key', 'whatsapp_url')->value('value'))->toBe('https://wa.me/97412345678');
});

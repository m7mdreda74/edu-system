<?php

declare(strict_types=1);

use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\User\Models\User;
use Illuminate\Support\Facades\DB;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

function themeAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('allows an admin to choose one of the supported site themes', function () {
    $this->actingAs(themeAdmin())
        ->post(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'site_theme',
                'value' => 'ocean',
                'type' => 'string',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PlatformSetting::where('key', 'site_theme')->value('value'))->toBe('ocean');

    $this->actingAs(themeAdmin())
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('data-site-theme="ocean"', false);
});

it('rejects unsupported site themes', function () {
    $this->actingAs(themeAdmin())
        ->post(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'site_theme',
                'value' => 'unsafe-theme',
                'type' => 'string',
            ]],
        ])
        ->assertSessionHasErrors('settings.0.value');

    expect(PlatformSetting::where('key', 'site_theme')->value('value'))->toBe('royal');
});

it('saves a full settings screen in a constant number of database queries', function () {
    $timestamp = now();
    DB::table('platform_settings')->insert(
        collect(range(1, 50))->map(fn (int $index): array => [
            'key' => "bulk_setting_{$index}",
            'value' => "old_{$index}",
            'type' => 'string',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all(),
    );

    $payload = PlatformSetting::query()
        ->where('key', 'like', 'bulk_setting_%')
        ->get(['id', 'key', 'value', 'type'])
        ->map(fn (PlatformSetting $setting): array => [
            'id' => $setting->id,
            'key' => $setting->key,
            'value' => "new_{$setting->id}",
            'type' => $setting->type,
        ])
        ->all();

    $settingsQueries = [];
    DB::listen(function ($query) use (&$settingsQueries): void {
        if (str_contains($query->sql, 'platform_settings')) {
            $settingsQueries[] = $query->sql;
        }
    });

    $this->actingAs(themeAdmin())
        ->post(route('admin.settings.update'), ['settings' => $payload])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($settingsQueries)->toHaveCount(2)
        ->and(PlatformSetting::where('key', 'bulk_setting_1')->value('value'))
        ->toStartWith('new_');
});

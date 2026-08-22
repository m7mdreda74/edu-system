<?php

declare(strict_types=1);

use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\User\Models\User;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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

it('saves settings as json without reloading the inertia page', function () {
    $this->actingAs(themeAdmin())
        ->postJson(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'platform_name',
                'value' => 'التفوق السريع',
                'type' => 'string',
            ]],
        ])
        ->assertOk()
        ->assertJson(['message' => 'تم حفظ الإعدادات بنجاح.']);

    expect(PlatformSetting::where('key', 'platform_name')->value('value'))
        ->toBe('التفوق السريع');
});

it('does not allow homepage live counters to be edited as platform settings', function () {
    $setting = PlatformSetting::create([
        'key' => 'home_stats_students',
        'value' => '123',
        'type' => 'string',
    ]);

    $this->actingAs(themeAdmin())
        ->post(route('admin.settings.update'), [
            'settings' => [[
                'id' => $setting->id,
                'key' => 'home_stats_students',
                'value' => '999999',
                'type' => 'string',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PlatformSetting::find($setting->id)->value)->toBe('123');
});

it('allows the admin to edit the homepage hero badge through site pages', function () {
    $this->actingAs(themeAdmin())
        ->post(route('admin.site-pages.update'), [
            'settings' => [
                'home_hero_badge' => 'شارتنا الجديدة',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PlatformSetting::where('key', 'home_hero_badge')->value('value'))
        ->toBe('شارتنا الجديدة');
});

it('allows the admin to control the free youtube section visibility through site pages', function () {
    $this->actingAs(themeAdmin())
        ->post(route('admin.site-pages.update'), [
            'settings' => [
                'home_youtube_visible' => 'false',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PlatformSetting::where('key', 'home_youtube_visible')->value('value'))
        ->toBe('false');
});

it('rejects new settings outside the typed registry', function () {
    $this->actingAs(themeAdmin())
        ->postJson(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'arbitrary_runtime_key',
                'value' => 'should not be stored',
                'type' => 'string',
            ]],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('settings.0.key');

    expect(PlatformSetting::where('key', 'arbitrary_runtime_key')->exists())->toBeFalse();
});

it('does not share unknown legacy settings with client pages', function () {
    PlatformSetting::create([
        'key' => 'legacy_secret_key',
        'value' => 'must-not-reach-browser',
        'type' => 'string',
    ]);
    Cache::forget('platform_settings');

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('settings.legacy_secret_key'));
});

it('rejects unsafe URLs in structured site content and records only value hashes', function () {
    $admin = themeAdmin();

    $this->actingAs($admin)
        ->postJson(route('admin.site-pages.update'), [
            'settings' => [
                'navbar_links' => [[
                    'label' => 'رابط',
                    'href' => 'javascript:alert(1)',
                ]],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('settings.navbar_links');

    $this->actingAs($admin)
        ->postJson(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'platform_name',
                'value' => 'اسم آمن',
                'type' => 'string',
            ]],
        ])
        ->assertOk();

    $event = AuditEvent::query()
        ->where('action', 'settings.updated')
        ->latest('id')
        ->firstOrFail();
    $change = $event->metadata['changes'][0];

    expect($change['new_value_hash'])->toBe(hash('sha256', 'اسم آمن'))
        ->and($event->metadata)->not->toHaveKey('value');
});

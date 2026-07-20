<?php

declare(strict_types=1);

use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\User\Models\User;

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

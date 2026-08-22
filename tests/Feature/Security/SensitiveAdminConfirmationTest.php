<?php

declare(strict_types=1);

use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('requires recent password confirmation for production admin mutations', function (): void {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    config(['app.env' => 'production']);

    $this->actingAs($admin)
        ->post(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'platform_name',
                'value' => 'blocked until confirmation',
                'type' => 'string',
            ]],
        ])
        ->assertRedirect(route('password.confirm'));

    expect(PlatformSetting::where('key', 'platform_name')->exists())->toBeFalse();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->post(route('admin.settings.update'), [
            'settings' => [[
                'key' => 'platform_name',
                'value' => 'confirmed admin change',
                'type' => 'string',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PlatformSetting::where('key', 'platform_name')->value('value'))
        ->toBe('confirmed admin change');
});

<?php

declare(strict_types=1);

use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\User\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('shows notification preferences with enabled defaults', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->where('notificationPreferences.0.key', 'generic_database_notification')
            ->where('notificationPreferences.0.channels.0.enabled', true));
});

it('saves supported notification preferences and suppresses disabled in-app notifications', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.notification-preferences.update'), [
            'preferences' => [
                [
                    'type' => 'generic_database_notification',
                    'channel' => NotificationPreference::CHANNEL_DATABASE,
                    'enabled' => false,
                ],
                [
                    'type' => 'payment_success',
                    'channel' => NotificationPreference::CHANNEL_MAIL,
                    'enabled' => false,
                ],
            ],
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors();

    expect(NotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_type', 'generic_database_notification')
        ->where('channel', NotificationPreference::CHANNEL_DATABASE)
        ->value('is_enabled'))->toBeFalse();

    $user->notify(new GenericDatabaseNotification([
        'title' => 'Disabled notification',
        'message' => 'This notification should not be persisted.',
    ]));

    expect($user->notifications()->count())->toBe(0);

    $user->notificationPreferences()->updateOrCreate(
        [
            'notification_type' => 'generic_database_notification',
            'channel' => NotificationPreference::CHANNEL_DATABASE,
        ],
        ['is_enabled' => true],
    );
    $user->notify(new GenericDatabaseNotification([
        'title' => 'Enabled notification',
        'message' => 'This notification should be persisted.',
    ]));

    expect($user->notifications()->count())->toBe(1);
});

it('does not let a user submit unsupported notification types or channels', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.notification-preferences.update'), [
            'preferences' => [[
                'type' => 'not-a-real-type',
                'channel' => 'push',
                'enabled' => true,
            ]],
        ])
        ->assertSessionHasErrors(['preferences.0.type', 'preferences.0.channel'])
        ->assertRedirect(route('profile.edit'));
});

<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('serves public teacher autocomplete results from the named endpoint', function (): void {
    Role::findOrCreate('teacher', 'web');
    $teacher = User::factory()->create(['name' => 'مدرس الرياضيات', 'is_active' => true]);
    $teacher->assignRole('teacher');

    $this->getJson(route('search.autocomplete', ['q' => 'رياض']))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'teacher',
            'id' => $teacher->id,
            'title' => 'مدرس الرياضيات',
        ]);
});

it('returns an empty autocomplete response for a too-short query', function (): void {
    $this->getJson(route('search.autocomplete', ['q' => 'م']))
        ->assertOk()
        ->assertExactJson([]);
});

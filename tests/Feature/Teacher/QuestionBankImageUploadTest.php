<?php

use App\Domain\User\Models\User;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('teacher can upload quiz images and receives clean url', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('PHP GD extension is required for image upload tests.');
    }

    Storage::fake('public');

    $teacher = User::factory()->create();
    $teacher->assignRole('teacher');

    $image = UploadedFile::fake()->image('diagram.png', 400, 300);

    $response = $this->actingAs($teacher)
        ->postJson(route('teacher.question-bank.upload-image'), [
            'image' => $image,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['url']);

    expect($response->json('url'))->toStartWith('/storage/quiz_images/');
});

test('prune audit command removes only events older than retention cutoff', function (): void {
    $recentEvent = AuditEvent::create([
        'action' => 'test.recent',
        'created_at' => now()->subDays(5),
    ]);

    $oldEvent = AuditEvent::create([
        'action' => 'test.old',
        'created_at' => now()->subDays(400),
    ]);

    $this->artisan('audit:prune-old-logs', ['--days' => 365])
        ->assertExitCode(0);

    expect(AuditEvent::find($recentEvent->id))->not->toBeNull()
        ->and(AuditEvent::find($oldEvent->id))->toBeNull();
});

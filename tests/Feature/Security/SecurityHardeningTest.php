<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LessonQuestion;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_has_baseline_security_headers_and_no_framework_version(): void
    {
        $jitsiOrigin = 'https://' . trim((string) config('services.jitsi.domain', 'meet.jit.si'), '/');

        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader(
                'Permissions-Policy',
                sprintf('camera=(self "%s"), microphone=(self "%s"), geolocation=()', $jitsiOrigin, $jitsiOrigin),
            );

        $this->assertArrayNotHasKey('version', $response->json());
    }

    public function test_public_production_migration_seed_endpoint_does_not_exist(): void
    {
        $this->get('/run-prod-migrations-seed')->assertNotFound();
    }

    public function test_inactive_users_cannot_log_in_or_keep_an_existing_session(): void
    {
        $inactive = User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);
        $inactive->assignRole('student');

        $this->post('/login', [
            'email' => $inactive->email,
            'password' => 'password',
        ]);

        $this->assertGuest();

        $this->actingAs($inactive)
            ->get('/profile')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_private_material_file_requires_the_assignment_subscription(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('booklets/security-test.pdf', "%PDF-1.4\nprivate content");

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $assignment = TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
            'subject_id' => Subject::query()->firstOrFail()->id,
        ]);
        $unit = CurriculumUnit::factory()->create(['teaching_assignment_id' => $assignment->id]);
        $material = GroupMaterial::factory()->create([
            'curriculum_unit_id' => $unit->id,
            'attachment_path' => 'private://booklets/security-test.pdf',
        ]);

        $unsubscribed = User::factory()->create();
        $unsubscribed->assignRole('student');

        $this->actingAs($unsubscribed)
            ->get(route('learning.material.download', $material))
            ->assertForbidden();

        $student = User::factory()->create();
        $student->assignRole('student');
        Subscription::factory()->active()->private()->create([
            'student_id' => $student->id,
            'teaching_assignment_id' => $assignment->id,
        ]);

        $this->actingAs($student)
            ->get(route('learning.material.download', $material))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_paid_material_questions_require_access_and_cannot_reply_across_lessons(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $assignment = TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
            'subject_id' => Subject::query()->firstOrFail()->id,
        ]);
        $unit = CurriculumUnit::factory()->create(['teaching_assignment_id' => $assignment->id]);
        $material = GroupMaterial::factory()->create(['curriculum_unit_id' => $unit->id]);
        $otherMaterial = GroupMaterial::factory()->create([
            'curriculum_unit_id' => $unit->id,
            'order' => 2,
        ]);

        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student)
            ->getJson(route('materials.questions.index', $material->id))
            ->assertForbidden();

        Subscription::factory()->active()->private()->create([
            'student_id' => $student->id,
            'teaching_assignment_id' => $assignment->id,
        ]);

        $question = LessonQuestion::create([
            'user_id' => $student->id,
            'lesson_id' => $otherMaterial->id,
            'content' => 'Question from another lesson',
        ]);

        $this->actingAs($student)
            ->postJson(route('materials.questions.store', $material->id), [
                'content' => 'Cross-lesson reply attempt',
                'parent_id' => $question->id,
            ])
            ->assertNotFound();
    }
}

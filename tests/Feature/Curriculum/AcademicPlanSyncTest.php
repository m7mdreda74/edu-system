<?php

declare(strict_types=1);

namespace Tests\Feature\Curriculum;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcademicPlanSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_teacher_curriculum_landing_redirects_to_active_assignment(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $grade = GradeLevel::query()->first() ?? GradeLevel::factory()->create();
        $subject = Subject::factory()->create();

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'grade_level_id' => $grade->id,
            'subject_id' => $subject->id,
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.curriculum.index'))
            ->assertRedirect(route('teacher.curriculum', ['assignment' => $assignment->id]));
    }

    public function test_group_lesson_in_teaching_schedule_syncs_to_curriculum(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $grade = GradeLevel::query()->first() ?? GradeLevel::factory()->create();
        $subject = Subject::factory()->create();
        $term = AcademicTerm::first() ?? AcademicTerm::factory()->create();

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'grade_level_id' => $grade->id,
            'subject_id' => $subject->id,
            'is_active' => true,
        ]);

        $group = TeachingGroup::create([
            'teaching_assignment_id' => $assignment->id,
            'name' => 'المجموعة الأولى',
            'capacity' => 20,
            'day_of_week' => 0,
            'start_time' => '16:00',
            'end_time' => '17:00',
            'duration_minutes' => 60,
            'academic_term_id' => $term->id,
            'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.teaching-schedule.groups.lessons.store', $group->id), [
                'title' => 'الدرس الأول: مقدمة في المادة',
                'description' => 'شرح الدرس الأول',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('teaching_group_lessons', [
            'teaching_group_id' => $group->id,
            'title' => 'الدرس الأول: مقدمة في المادة',
        ]);

        $this->assertDatabaseHas('curriculum_units', [
            'teaching_assignment_id' => $assignment->id,
            'title' => 'الوحدة الأولى',
        ]);

        $this->assertDatabaseHas('group_materials', [
            'title' => 'الدرس الأول: مقدمة في المادة',
        ]);
    }

    public function test_student_learn_page_reflects_semester_academic_plan(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $student = User::factory()->create();
        $student->assignRole('student');

        $grade = GradeLevel::query()->first() ?? GradeLevel::factory()->create();
        $subject = Subject::factory()->create();

        $terms = AcademicTerm::orderBy('starts_on')->get();
        $term1 = $terms->first() ?? AcademicTerm::factory()->create([
            'name' => 'الفصل الدراسي الأول',
            'starts_on' => now()->subMonths(3),
            'ends_on' => now()->subMonth(),
        ]);
        $term2 = $terms->skip(1)->first() ?? AcademicTerm::factory()->create([
            'name' => 'الفصل الدراسي الثاني',
            'starts_on' => now()->subDay(),
            'ends_on' => now()->addMonths(3),
        ]);

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'grade_level_id' => $grade->id,
            'subject_id' => $subject->id,
            'is_active' => true,
        ]);

        $group = TeachingGroup::create([
            'teaching_assignment_id' => $assignment->id,
            'name' => 'المجموعة الأولى',
            'capacity' => 20,
            'day_of_week' => 0,
            'start_time' => '16:00',
            'end_time' => '17:00',
            'duration_minutes' => 60,
            'academic_term_id' => $term2->id,
            'is_active' => true,
        ]);

        Subscription::create([
            'student_id' => $student->id,
            'teaching_assignment_id' => $assignment->id,
            'teaching_group_id' => $group->id,
            'type' => Subscription::TYPE_GROUP,
            'status' => Subscription::STATUS_ACTIVE,
            'monthly_price' => 200,
            'period_start' => now()->subDays(5),
            'period_end' => now()->addDays(25),
        ]);

        // Teacher created Unit 1 in Term 1
        $unit = CurriculumUnit::create([
            'teaching_assignment_id' => $assignment->id,
            'academic_term_id' => $term1->id,
            'order' => 1,
            'title' => 'الوحدة الأولى: أساسيات المنهج',
            'is_published' => true,
        ]);

        GroupMaterial::create([
            'curriculum_unit_id' => $unit->id,
            'academic_term_id' => $term1->id,
            'title' => 'الدرس الأول: المفاهيم الأساسية',
            'order' => 1,
            'duration_seconds' => 300,
        ]);

        // Even though group has term2, since term2 has no units yet,
        // LearnController should auto-select term1 where units exist!
        $this->actingAs($student)
            ->get(route('student.learn', $group->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Student/Learn')
                ->where('activeTermId', $term1->id)
                ->has('units', 1)
                ->where('units.0.title', 'الوحدة الأولى: أساسيات المنهج')
                ->where('units.0.lessons.0.title', 'الدرس الأول: المفاهيم الأساسية')
            );
    }
}

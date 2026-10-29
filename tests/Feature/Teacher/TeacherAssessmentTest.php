<?php

declare(strict_types=1);

namespace Tests\Feature\Teacher;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_assessments_dashboard(): void
    {
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $grade = GradeLevel::first() ?? GradeLevel::create([
            'key' => 'grade_test',
            'name' => 'الصف التجريبي',
            'order' => 99,
        ]);

        $subject = Subject::create([
            'grade_level_id' => $grade->id,
            'name' => 'الرياضيات',
            'order' => 1,
        ]);

        $assignment = TeachingAssignment::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'grade_level_id' => $grade->id,
            'private_monthly_price' => 100,
            'currency' => 'QAR',
            'accepts_private' => true,
            'is_active' => true,
        ]);

        $group = TeachingGroup::create([
            'teaching_assignment_id' => $assignment->id,
            'name' => 'مجموعة أ',
            'capacity' => 20,
            'monthly_price' => 100,
            'currency' => 'QAR',
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Qatar',
            'is_active' => true,
        ]);

        $term = \App\Domain\Academic\Models\AcademicTerm::first() ?? \App\Domain\Academic\Models\AcademicTerm::create([
            'name' => 'الفصل الأول',
            'year_label' => '2026/2027',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(3),
            'is_active' => true,
        ]);

        $unit = CurriculumUnit::create([
            'teaching_assignment_id' => $assignment->id,
            'academic_term_id' => $term->id,
            'order' => 1,
            'title' => 'الوحدة الأولى',
            'is_published' => true,
        ]);

        $lesson = GroupMaterial::create([
            'curriculum_unit_id' => $unit->id,
            'title' => 'الدرس الأول',
            'order' => 1,
            'is_published' => true,
        ]);

        $worksheet = Worksheet::create([
            'curriculum_unit_id' => $unit->id,
            'lesson_id' => $lesson->id,
            'teaching_group_id' => $group->id,
            'title' => 'تقييم تجريبي',
            'file_path' => 'private://placeholder',
            'type' => Worksheet::TYPE_HOMEWORK,
            'requires_submission' => true,
            'available_from' => now()->subDay(),
            'due_date' => now()->addDays(2),
            'allow_late_submission' => true,
            'max_attempts' => 1,
            'max_score' => 100,
            'is_active' => true,
        ]);

        \App\Domain\Learning\Models\LearningResource::create([
            'owner_teacher_id' => $teacher->id,
            'title' => 'ملف تجريبي',
            'file_path' => 'public://dummy.pdf',
            'category' => 'worksheet',
            'visibility' => 'shared',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $response = $this->actingAs($teacher)
            ->get(route('teacher.assessments.index'));

        $response->assertStatus(200);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;

/**
 * Turns the faculty plan into assignments, groups and open private slots.
 *
 * The plan is keyed by subject, so a teacher can only ever end up with the one
 * they are declared under — the rule holds by construction rather than by
 * checking.
 */
class TeachingSeeder extends Seeder
{
    private const TIMEZONE = 'Asia/Qatar';

    private ?int $termId = null;

    public function run(): void
    {
        $this->termId = AcademicTerm::currentOrNext()?->id;

        foreach (TeachingStaff::plan() as $subjectName => $staff) {
            $subject = Subject::where('name', $subjectName)->first();

            if (! $subject) {
                continue;
            }

            foreach ($staff as $definition) {
                $teacher = User::where('email', $definition['email'])->first();

                if (! $teacher) {
                    continue;
                }

                // The subject is a fact about the teacher, not their timetable.
                $teacher->update(['subject_id' => $subject->id]);

                $this->seedGrades($teacher, $subject->id, $definition);
            }
        }
    }

    private function seedGrades(User $teacher, int $subjectId, array $definition): void
    {
        foreach ($definition['grades'] as $gradeKey) {
            $grade = GradeLevel::where('key', $gradeKey)->first();

            if (! $grade) {
                continue;
            }

            $assignment = TeachingAssignment::firstOrCreate(
                [
                    'teacher_id'     => $teacher->id,
                    'subject_id'     => $subjectId,
                    'grade_level_id' => $grade->id,
                ],
                [
                    'private_monthly_price' => $definition['private'],
                    'currency'              => 'QAR',
                    'accepts_private'       => true,
                    'is_active'             => true,
                ],
            );

            $this->seedPrivateSlots($assignment->id);

            foreach ($definition['groups'] as $spec) {
                $this->seedGroup($assignment->id, $spec);
            }
        }
    }

    private function seedGroup(int $assignmentId, array $spec): void
    {
        // Group names are unique per assignment, and the same definition runs
        // across several grades — each gets its own assignment, so no clash.
        if (TeachingGroup::where('teaching_assignment_id', $assignmentId)->where('name', $spec['name'])->exists()) {
            return;
        }

        [$day, $start, $end] = $spec['days'][0];

        $group = TeachingGroup::create([
            'teaching_assignment_id' => $assignmentId,
            'academic_term_id'       => $this->termId,
            'name'                   => $spec['name'],
            'capacity'               => $spec['capacity'],
            'monthly_price'          => $spec['price'],
            'currency'               => 'QAR',
            'day_of_week'            => $day,
            'start_time'             => $start,
            'end_time'               => $end,
            'duration_minutes'       => $this->minutesBetween($start, $end),
            'timezone'               => self::TIMEZONE,
            'is_active'              => true,
        ]);

        foreach ($spec['days'] as [$scheduleDay, $scheduleStart, $scheduleEnd]) {
            TeachingGroupSchedule::create([
                'teaching_group_id' => $group->id,
                'day_of_week'       => $scheduleDay,
                'start_time'        => $scheduleStart,
                'end_time'          => $scheduleEnd,
                'duration_minutes'  => $this->minutesBetween($scheduleStart, $scheduleEnd),
            ]);
        }
    }

    /** A few open evening slots so the booking page is never empty. */
    private function seedPrivateSlots(int $assignmentId): void
    {
        if (PrivateSessionSlot::where('teaching_assignment_id', $assignmentId)->exists()) {
            return;
        }

        foreach ([2, 4, 6, 9] as $daysAhead) {
            PrivateSessionSlot::create([
                'teaching_assignment_id' => $assignmentId,
                'starts_at'              => now()->addDays($daysAhead)->setTime(20, 0),
                'ends_at'                => now()->addDays($daysAhead)->setTime(21, 0),
                'timezone'               => self::TIMEZONE,
                'status'                 => 'available',
            ]);
        }
    }

    private function minutesBetween(string $start, string $end): int
    {
        return (int) round((strtotime($end) - strtotime($start)) / 60);
    }
}

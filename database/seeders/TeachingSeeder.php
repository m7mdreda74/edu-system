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
 * What each teacher covers, the weekly groups they run, and the private slots
 * they leave open.
 *
 * Prices are in the smallest currency unit — 45000 is 450 riyals a month.
 * Secondary costs more than primary, and private more than group, which is what
 * the market actually looks like.
 */
class TeachingSeeder extends Seeder
{
    private const TIMEZONE = 'Asia/Qatar';

    /** Sunday is 0 here, matching Carbon's dayOfWeek. */
    private const SUN = 0;
    private const MON = 1;
    private const TUE = 2;
    private const WED = 3;
    private const THU = 4;
    private const SAT = 6;

    private ?int $termId = null;

    public function run(): void
    {
        $this->termId = AcademicTerm::currentOrNext()?->id;

        foreach ($this->plan() as $teacherEmail => $assignments) {
            $teacher = User::where('email', $teacherEmail)->first();

            if (! $teacher) {
                continue;
            }

            foreach ($assignments as $definition) {
                $this->seedAssignment($teacher, $definition);
            }
        }
    }

    /**
     * teacher email => list of [subject, grades, private price, groups]
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function plan(): array
    {
        return [
            'ahmed@altafawwuq.com' => [
                [
                    'subject' => 'الرياضيات',
                    'grades'  => ['grade_12_science', 'grade_11_science'],
                    'private' => 120_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 60_000, 'capacity' => 18, 'days' => [[self::SUN, '16:00', '17:30'], [self::TUE, '16:00', '17:30']]],
                        ['name' => 'مجموعة السبت المكثفة',   'price' => 75_000, 'capacity' => 12, 'days' => [[self::SAT, '09:00', '11:30']]],
                    ],
                ],
                [
                    'subject' => 'الرياضيات',
                    'grades'  => ['grade_10'],
                    'private' => 95_000,
                    'groups'  => [
                        ['name' => 'مجموعة الاثنين والأربعاء', 'price' => 50_000, 'capacity' => 22, 'days' => [[self::MON, '18:00', '19:30'], [self::WED, '18:00', '19:30']]],
                    ],
                ],
            ],

            'sara@altafawwuq.com' => [
                [
                    'subject' => 'الفيزياء',
                    'grades'  => ['grade_12_science', 'grade_11_science'],
                    'private' => 125_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأربعاء',        'price' => 65_000, 'capacity' => 16, 'days' => [[self::WED, '17:00', '18:30']]],
                        ['name' => 'مجموعة الخميس المسائية', 'price' => 65_000, 'capacity' => 16, 'days' => [[self::THU, '19:00', '20:30']]],
                    ],
                ],
                [
                    'subject' => 'الكيمياء',
                    'grades'  => ['grade_11_science', 'grade_10'],
                    'private' => 110_000,
                    'groups'  => [
                        ['name' => 'مجموعة الاثنين', 'price' => 55_000, 'capacity' => 20, 'days' => [[self::MON, '16:30', '18:00']]],
                    ],
                ],
            ],

            'khaled@altafawwuq.com' => [
                [
                    'subject' => 'الأحياء',
                    'grades'  => ['grade_12_science', 'grade_11_science'],
                    'private' => 100_000,
                    'groups'  => [
                        ['name' => 'مجموعة الثلاثاء', 'price' => 52_000, 'capacity' => 20, 'days' => [[self::TUE, '19:00', '20:30']]],
                    ],
                ],
                [
                    'subject' => 'العلوم',
                    'grades'  => ['grade_9', 'grade_8'],
                    'private' => 70_000,
                    'groups'  => [
                        ['name' => 'مجموعة السبت', 'price' => 38_000, 'capacity' => 25, 'days' => [[self::SAT, '11:00', '12:30']]],
                    ],
                ],
            ],

            'noura@altafawwuq.com' => [
                [
                    'subject' => 'اللغة العربية',
                    'grades'  => ['grade_12_science', 'grade_12_arts', 'grade_11_arts'],
                    'private' => 90_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأحد',   'price' => 45_000, 'capacity' => 24, 'days' => [[self::SUN, '18:00', '19:30']]],
                        ['name' => 'مجموعة الأربعاء', 'price' => 45_000, 'capacity' => 24, 'days' => [[self::WED, '15:00', '16:30']]],
                    ],
                ],
                [
                    'subject' => 'اللغة العربية',
                    'grades'  => ['grade_7', 'grade_6'],
                    'private' => 65_000,
                    'groups'  => [
                        ['name' => 'مجموعة الإثنين والخميس', 'price' => 35_000, 'capacity' => 28, 'days' => [[self::MON, '15:00', '16:00'], [self::THU, '15:00', '16:00']]],
                    ],
                ],
            ],

            'yousef@altafawwuq.com' => [
                [
                    'subject' => 'اللغة الإنجليزية',
                    'grades'  => ['grade_12_science', 'grade_12_arts', 'grade_10'],
                    'private' => 100_000,
                    'groups'  => [
                        ['name' => 'مجموعة الثلاثاء والخميس', 'price' => 55_000, 'capacity' => 20, 'days' => [[self::TUE, '17:30', '19:00'], [self::THU, '17:30', '19:00']]],
                    ],
                ],
                [
                    'subject' => 'اللغة الإنجليزية',
                    'grades'  => ['grade_9', 'grade_8'],
                    'private' => 70_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأحد', 'price' => 40_000, 'capacity' => 26, 'days' => [[self::SUN, '14:00', '15:30']]],
                    ],
                ],
            ],

            'jassim@altafawwuq.com' => [
                [
                    'subject' => 'علوم الحاسب',
                    'grades'  => ['grade_12_technology', 'grade_11_technology'],
                    'private' => 110_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 58_000, 'capacity' => 18, 'days' => [[self::SUN, '17:00', '18:30'], [self::TUE, '17:00', '18:30']]],
                    ],
                ],
                [
                    'subject' => 'تكنولوجيا المعلومات',
                    'grades'  => ['grade_11_technology', 'grade_10'],
                    'private' => 90_000,
                    'groups'  => [
                        ['name' => 'مجموعة الخميس', 'price' => 48_000, 'capacity' => 20, 'days' => [[self::THU, '18:00', '19:30']]],
                    ],
                ],
            ],

            'maryam@altafawwuq.com' => [
                [
                    'subject' => 'التاريخ',
                    'grades'  => ['grade_12_arts', 'grade_11_arts'],
                    'private' => 85_000,
                    'groups'  => [
                        ['name' => 'مجموعة السبت', 'price' => 45_000, 'capacity' => 20, 'days' => [[self::SAT, '16:00', '17:30']]],
                    ],
                ],
                [
                    'subject' => 'الجغرافيا',
                    'grades'  => ['grade_12_arts', 'grade_11_arts'],
                    'private' => 85_000,
                    'groups'  => [
                        ['name' => 'مجموعة الإثنين', 'price' => 45_000, 'capacity' => 20, 'days' => [[self::MON, '19:30', '21:00']]],
                    ],
                ],
            ],

            'abdullah@altafawwuq.com' => [
                [
                    'subject' => 'التربية الإسلامية',
                    'grades'  => ['grade_9', 'grade_8', 'grade_7'],
                    'private' => 60_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأربعاء', 'price' => 30_000, 'capacity' => 30, 'days' => [[self::WED, '16:00', '17:00']]],
                    ],
                ],
                [
                    'subject' => 'التربية الإسلامية',
                    'grades'  => ['grade_5', 'grade_4'],
                    'private' => 50_000,
                    'groups'  => [
                        ['name' => 'مجموعة السبت الصباحية', 'price' => 25_000, 'capacity' => 30, 'days' => [[self::SAT, '10:00', '11:00']]],
                    ],
                ],
            ],

            'fatima@altafawwuq.com' => [
                [
                    'subject' => 'الرياضيات',
                    'grades'  => ['grade_6', 'grade_5', 'grade_4'],
                    'private' => 55_000,
                    'groups'  => [
                        ['name' => 'مجموعة الأحد والثلاثاء', 'price' => 28_000, 'capacity' => 25, 'days' => [[self::SUN, '15:00', '16:00'], [self::TUE, '15:00', '16:00']]],
                    ],
                ],
                [
                    'subject' => 'العلوم',
                    'grades'  => ['grade_6', 'grade_5'],
                    'private' => 55_000,
                    'groups'  => [
                        ['name' => 'مجموعة الخميس', 'price' => 26_000, 'capacity' => 25, 'days' => [[self::THU, '16:00', '17:00']]],
                    ],
                ],
                [
                    'subject' => 'الرياضيات',
                    'grades'  => ['grade_3', 'grade_2', 'grade_1'],
                    'private' => 45_000,
                    // A brand-new offering: assigned, priced, but no group yet.
                    'groups'  => [],
                ],
            ],
        ];
    }

    private function seedAssignment(User $teacher, array $definition): void
    {
        $subject = Subject::where('name', $definition['subject'])->first();

        if (! $subject) {
            return;
        }

        foreach ($definition['grades'] as $gradeKey) {
            $grade = GradeLevel::where('key', $gradeKey)->first();

            if (! $grade) {
                continue;
            }

            $assignment = TeachingAssignment::firstOrCreate(
                [
                    'teacher_id'     => $teacher->id,
                    'subject_id'     => $subject->id,
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

            foreach ($definition['groups'] as $index => $spec) {
                // Group names are unique per assignment, and one definition can
                // cover several grades — suffix the grade to keep them apart.
                $this->seedGroup($assignment->id, $spec, $grade->name);
            }
        }
    }

    private function seedGroup(int $assignmentId, array $spec, string $gradeName): void
    {
        $firstDay = $spec['days'][0];
        $name     = $spec['name'];

        if (TeachingGroup::where('teaching_assignment_id', $assignmentId)->where('name', $name)->exists()) {
            return;
        }

        $group = TeachingGroup::create([
            'teaching_assignment_id' => $assignmentId,
            'academic_term_id'       => $this->termId,
            'name'                   => $name,
            'capacity'               => $spec['capacity'],
            'monthly_price'          => $spec['price'],
            'currency'               => 'QAR',
            'day_of_week'            => $firstDay[0],
            'start_time'             => $firstDay[1],
            'end_time'               => $firstDay[2],
            'duration_minutes'       => $this->minutesBetween($firstDay[1], $firstDay[2]),
            'timezone'               => self::TIMEZONE,
            'is_active'              => true,
        ]);

        foreach ($spec['days'] as [$day, $start, $end]) {
            TeachingGroupSchedule::create([
                'teaching_group_id' => $group->id,
                'day_of_week'       => $day,
                'start_time'        => $start,
                'end_time'          => $end,
                'duration_minutes'  => $this->minutesBetween($start, $end),
            ]);
        }
    }

    /** A handful of open evening slots so the booking page is never empty. */
    private function seedPrivateSlots(int $assignmentId): void
    {
        if (PrivateSessionSlot::where('teaching_assignment_id', $assignmentId)->exists()) {
            return;
        }

        foreach ([2, 4, 6, 9, 11] as $daysAhead) {
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

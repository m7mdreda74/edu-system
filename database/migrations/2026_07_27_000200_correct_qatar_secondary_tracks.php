<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correct the secondary tracks and the subject list against the ministry's
 * actual plan.
 *
 * Qatari general secondary runs three tracks from grade 11, not two: العلمي,
 * الآداب والإنسانيات, and التكنولوجي. Grade 10 stays common — students take
 * every subject before choosing.
 *
 * The subject list is corrected too. Grade 10's weekly allocation is the
 * ministry's published one (25 periods), and two subjects that belong in the
 * plan were missing entirely: المهارات الحياتية and علوم الحاسب. Subjects that
 * are not part of the Qatari plan are deactivated rather than deleted, since
 * teachers may already be assigned to them.
 *
 * @see https://edu.gov.qa/ar/News/Details/9271800
 */
return new class extends Migration
{
    private const TRACK_SCIENCE    = 'science';
    private const TRACK_ARTS       = 'arts';
    private const TRACK_TECHNOLOGY = 'technology';

    public function up(): void
    {
        $this->addWeeklyPeriods();
        $this->renameLiteraryToArts();
        $this->addTechnologyTrack();

        $subjects = $this->correctSubjects();
        $this->rebuildCurriculum($subjects);
        $this->deactivateSubjectsOutsidePlan($subjects);

        Cache::forget('home.grades');
        Cache::forget('platform_settings');
    }

    public function down(): void
    {
        throw new RuntimeException('لا يمكن التراجع عن تصحيح مسارات ومواد المنهج القطري.');
    }

    // ─── Structure ────────────────────────────────────────────────

    /** The ministry publishes a weekly period count per subject per grade. */
    private function addWeeklyPeriods(): void
    {
        if (Schema::hasColumn('grade_level_subject', 'weekly_periods')) {
            return;
        }

        Schema::table('grade_level_subject', function (Blueprint $table): void {
            $table->unsignedTinyInteger('weekly_periods')->nullable()->after('subject_id');
        });
    }

    /**
     * "أدبي" was the working name; the ministry calls it مسار الآداب
     * والإنسانيات. Keys change with it, so the URLs match what it is called.
     */
    private function renameLiteraryToArts(): void
    {
        foreach ([11 => 'الحادي عشر', 12 => 'الثاني عشر'] as $number => $ordinal) {
            $old = DB::table('grade_levels')->where('key', "grade_{$number}_literary")->first();

            if (! $old) {
                continue;
            }

            DB::table('grade_levels')->where('id', $old->id)->update([
                'key'     => "grade_{$number}_arts",
                'name'    => "الصف {$ordinal} — مسار الآداب والإنسانيات",
                'name_en' => "Grade {$number} — Arts & Humanities",
                'track'   => self::TRACK_ARTS,
            ]);

            DB::table('users')->where('grade_level', "grade_{$number}_literary")
                ->update(['grade_level' => "grade_{$number}_arts"]);
        }

        // The science rows only need their formal name tidying.
        foreach ([11 => 'الحادي عشر', 12 => 'الثاني عشر'] as $number => $ordinal) {
            DB::table('grade_levels')->where('key', "grade_{$number}_science")->update([
                'name'    => "الصف {$ordinal} — المسار العلمي",
                'name_en' => "Grade {$number} — Science",
                'track'   => self::TRACK_SCIENCE,
            ]);
        }
    }

    private function addTechnologyTrack(): void
    {
        foreach ([11 => 'الحادي عشر', 12 => 'الثاني عشر'] as $number => $ordinal) {
            $key = "grade_{$number}_technology";

            if (DB::table('grade_levels')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('grade_levels')->insert([
                'key'        => $key,
                'name'       => "الصف {$ordinal} — المسار التكنولوجي",
                'name_en'    => "Grade {$number} — Technology",
                'stage'      => 'secondary',
                'track'      => self::TRACK_TECHNOLOGY,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ─── Subjects ─────────────────────────────────────────────────

    /** @return array<string, int> */
    private function correctSubjects(): array
    {
        $plan = [
            'التربية الإسلامية'       => ['Islamic Education', 'book'],
            'اللغة العربية'           => ['Arabic Language', 'language'],
            'اللغة الإنجليزية'        => ['English Language', 'globe'],
            'الرياضيات'               => ['Mathematics', 'calculator'],
            'العلوم'                  => ['Science', 'atom'],
            'الفيزياء'                => ['Physics', 'atom'],
            'الكيمياء'                => ['Chemistry', 'flask'],
            'الأحياء'                 => ['Biology', 'dna'],
            'الدراسات الاجتماعية'     => ['Social Studies', 'landmark'],
            'التاريخ'                 => ['History', 'landmark'],
            'الجغرافيا'               => ['Geography', 'globe'],
            'تكنولوجيا المعلومات'     => ['Information Technology', 'settings'],
            'علوم الحاسب'             => ['Computer Science', 'settings'],
            'المهارات الحياتية'       => ['Life Skills', 'student'],
            'التربية البدنية والصحية'  => ['Physical & Health Education', 'student'],
            'التربية الفنية'          => ['Art Education', 'video'],
        ];

        $ids = [];

        foreach ($plan as $name => [$nameEn, $icon]) {
            $existing = DB::table('subjects')->where('name', $name)->first();

            if ($existing) {
                DB::table('subjects')->where('id', $existing->id)
                    ->update(['name_en' => $nameEn, 'icon' => $icon, 'is_active' => true]);

                $ids[$name] = (int) $existing->id;
                continue;
            }

            $ids[$name] = (int) DB::table('subjects')->insertGetId([
                'name'       => $name,
                'name_en'    => $nameEn,
                'icon'       => $icon,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /**
     * Anything invented earlier that the Qatari plan does not carry —
     * psychology, sociology, economics, geology, French — is switched off
     * rather than deleted, because a teacher may already be assigned to it.
     *
     * @param array<string, int> $planned
     */
    private function deactivateSubjectsOutsidePlan(array $planned): void
    {
        DB::table('subjects')
            ->whereNotIn('id', array_values($planned))
            ->update(['is_active' => false]);
    }

    // ─── Curriculum ───────────────────────────────────────────────

    /** @param array<string, int> $subjects */
    private function rebuildCurriculum(array $subjects): void
    {
        $core = ['التربية الإسلامية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات'];

        $primaryLower = [...$core, 'العلوم', 'تكنولوجيا المعلومات', 'التربية البدنية والصحية', 'التربية الفنية'];
        $primaryUpper = [...$primaryLower, 'الدراسات الاجتماعية'];
        $preparatory  = [...$core, 'العلوم', 'الدراسات الاجتماعية', 'تكنولوجيا المعلومات', 'التربية البدنية والصحية', 'التربية الفنية'];

        // Grade 10 is the ministry's published allocation: 25 periods a week.
        $grade10 = [
            'التربية الإسلامية'      => 2,
            'اللغة العربية'          => 4,
            'اللغة الإنجليزية'       => 4,
            'الرياضيات'              => 4,
            'الكيمياء'               => 2,
            'الفيزياء'               => 2,
            'الأحياء'                => 2,
            'الدراسات الاجتماعية'    => 2,
            'تكنولوجيا المعلومات'    => 1,
            'التربية البدنية والصحية' => 1,
            'المهارات الحياتية'      => 1,
        ];

        $science = [...$core, 'الفيزياء', 'الكيمياء', 'الأحياء', 'علوم الحاسب', 'التربية البدنية والصحية'];
        $arts    = [...$core, 'التاريخ', 'الجغرافيا', 'الدراسات الاجتماعية', 'علوم الحاسب', 'التربية البدنية والصحية'];

        // The technology track is delivered in the technology schools; its core
        // is computing alongside maths and physics.
        $technology = [...$core, 'علوم الحاسب', 'تكنولوجيا المعلومات', 'الفيزياء', 'التربية البدنية والصحية'];

        $curriculum = [
            'grade_1' => $primaryLower, 'grade_2' => $primaryLower, 'grade_3' => $primaryLower,
            'grade_4' => $primaryUpper, 'grade_5' => $primaryUpper, 'grade_6' => $primaryUpper,
            'grade_7' => $preparatory,  'grade_8' => $preparatory,  'grade_9' => $preparatory,
            'grade_11_science'    => $science,    'grade_12_science'    => $science,
            'grade_11_arts'       => $arts,       'grade_12_arts'       => $arts,
            'grade_11_technology' => $technology, 'grade_12_technology' => $technology,
        ];

        // Start clean so subjects removed from the plan drop out of the browse.
        DB::table('grade_level_subject')->delete();

        foreach ($curriculum as $gradeKey => $names) {
            $this->link($gradeKey, array_fill_keys($names, null), $subjects);
        }

        $this->link('grade_10', $grade10, $subjects);

        // Never hide something a teacher is actually teaching.
        foreach (DB::table('teaching_assignments')->get(['grade_level_id', 'subject_id']) as $assignment) {
            DB::table('grade_level_subject')->updateOrInsert(
                ['grade_level_id' => $assignment->grade_level_id, 'subject_id' => $assignment->subject_id],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /**
     * @param array<string, int|null> $names  subject name => weekly periods
     * @param array<string, int>      $subjects
     */
    private function link(string $gradeKey, array $names, array $subjects): void
    {
        $gradeId = DB::table('grade_levels')->where('key', $gradeKey)->value('id');

        if (! $gradeId) {
            return;
        }

        foreach ($names as $name => $periods) {
            if (! isset($subjects[$name])) {
                continue;
            }

            DB::table('grade_level_subject')->updateOrInsert(
                ['grade_level_id' => $gradeId, 'subject_id' => $subjects[$name]],
                ['weekly_periods' => $periods, 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
};

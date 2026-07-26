<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lay in the Qatari MOEHE curriculum.
 *
 * Two things change. First, secondary splits into tracks: grade 10 is common,
 * and grades 11–12 run as a science track and a literary track, which is how
 * general secondary schools in Qatar actually work. Second, a subject belongs
 * to many grades — Arabic runs from grade 1 to grade 12 — so the single
 * `subjects.grade_level` column gives way to a proper curriculum pivot.
 *
 * Existing grade 11 and 12 rows keep their ids and become the science track,
 * because that is what the teaching already on them covers; anything teaching
 * a literary-only subject is moved across.
 */
return new class extends Migration
{
    private const TRACK_SCIENCE  = 'science';
    private const TRACK_LITERARY = 'literary';

    /** Subjects that exist only in one track, used to re-point existing teaching. */
    private const LITERARY_ONLY = [
        'التاريخ', 'الجغرافيا', 'علم النفس', 'علم الاجتماع', 'الاقتصاد', 'الفلسفة والمنطق',
    ];

    public function up(): void
    {
        $this->addTrackColumn();
        $this->ensureAllGrades();
        $this->splitSecondaryIntoTracks();
        $this->createCurriculumPivot();

        $subjects = $this->seedSubjects();
        $this->linkCurriculum($subjects);

        $this->retireSubjectGradeColumn();

        Cache::forget('platform_settings');
        Cache::forget('home.grades');
    }

    public function down(): void
    {
        throw new RuntimeException('لا يمكن التراجع عن زرع منهج دولة قطر.');
    }

    // ─── Schema ───────────────────────────────────────────────────

    private function addTrackColumn(): void
    {
        if (Schema::hasColumn('grade_levels', 'track')) {
            return;
        }

        Schema::table('grade_levels', function (Blueprint $table): void {
            // null for every grade below eleven — they have no track.
            $table->string('track', 20)->nullable()->after('stage')->index();
        });
    }

    private function createCurriculumPivot(): void
    {
        if (Schema::hasTable('grade_level_subject')) {
            return;
        }

        Schema::create('grade_level_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['grade_level_id', 'subject_id']);
        });
    }

    // ─── Grades ───────────────────────────────────────────────────

    /**
     * The original schema only shipped grades 10–12. Qatari schooling runs
     * 1–6 primary, 7–9 preparatory, 10–12 secondary, so fill in the rest.
     */
    private function ensureAllGrades(): void
    {
        $ordinals = [
            1 => 'الأول', 2 => 'الثاني', 3 => 'الثالث', 4 => 'الرابع', 5 => 'الخامس', 6 => 'السادس',
            7 => 'السابع', 8 => 'الثامن', 9 => 'التاسع', 10 => 'العاشر',
        ];

        foreach ($ordinals as $number => $ordinal) {
            $stage = match (true) {
                $number <= 6 => 'primary',
                $number <= 9 => 'preparatory',
                default      => 'secondary',
            };

            $suffix = match ($stage) {
                'primary'     => ' الابتدائي',
                'preparatory' => ' الإعدادي',
                default       => '',
            };

            $this->ensureGrade("grade_{$number}", "الصف {$ordinal}{$suffix}", "Grade {$number}", $stage, null);
        }
    }

    // ─── Secondary tracks ─────────────────────────────────────────

    private function splitSecondaryIntoTracks(): void
    {
        foreach ([11 => 'الحادي عشر', 12 => 'الثاني عشر'] as $number => $ordinal) {
            $existing = DB::table('grade_levels')->where('key', "grade_{$number}")->first();

            // The current row becomes the science track, keeping its id so all
            // the teaching already hanging off it survives untouched.
            if ($existing) {
                DB::table('grade_levels')->where('id', $existing->id)->update([
                    'key'     => "grade_{$number}_science",
                    'name'    => "الصف {$ordinal} — المسار العلمي",
                    'name_en' => "Grade {$number} — Science",
                    'track'   => self::TRACK_SCIENCE,
                    'stage'   => 'secondary',
                ]);

                DB::table('users')->where('grade_level', "grade_{$number}")
                    ->update(['grade_level' => "grade_{$number}_science"]);

                DB::table('subjects')->where('grade_level', "grade_{$number}")
                    ->update(['grade_level' => "grade_{$number}_science"]);
            }

            $this->ensureGrade("grade_{$number}_science", "الصف {$ordinal} — المسار العلمي", "Grade {$number} — Science", 'secondary', self::TRACK_SCIENCE);
            $this->ensureGrade("grade_{$number}_literary", "الصف {$ordinal} — المسار الأدبي", "Grade {$number} — Literary", 'secondary', self::TRACK_LITERARY);
        }

        $this->movePurelyLiteraryTeaching();
    }

    /**
     * A teacher covering history or geography for grade 11 belongs in the
     * literary track, not the science one the row just became.
     */
    private function movePurelyLiteraryTeaching(): void
    {
        foreach ([11, 12] as $number) {
            $scienceId  = DB::table('grade_levels')->where('key', "grade_{$number}_science")->value('id');
            $literaryId = DB::table('grade_levels')->where('key', "grade_{$number}_literary")->value('id');

            if (! $scienceId || ! $literaryId) {
                continue;
            }

            $literarySubjectIds = DB::table('subjects')->whereIn('name', self::LITERARY_ONLY)->pluck('id');

            DB::table('teaching_assignments')
                ->where('grade_level_id', $scienceId)
                ->whereIn('subject_id', $literarySubjectIds)
                ->update(['grade_level_id' => $literaryId]);
        }
    }

    private function ensureGrade(string $key, string $name, string $nameEn, string $stage, ?string $track): int
    {
        $existing = DB::table('grade_levels')->where('key', $key)->value('id');

        if ($existing) {
            DB::table('grade_levels')->where('id', $existing)->update([
                'name' => $name, 'name_en' => $nameEn, 'stage' => $stage, 'track' => $track, 'is_active' => true,
            ]);

            return (int) $existing;
        }

        return (int) DB::table('grade_levels')->insertGetId([
            'key'        => $key,
            'name'       => $name,
            'name_en'    => $nameEn,
            'stage'      => $stage,
            'track'      => $track,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── Subjects ─────────────────────────────────────────────────

    /** @return array<string, int> subject name => id */
    private function seedSubjects(): array
    {
        $catalogue = [
            'التربية الإسلامية'      => ['Islamic Education', 'book'],
            'اللغة العربية'          => ['Arabic Language', 'language'],
            'اللغة الإنجليزية'       => ['English Language', 'globe'],
            'اللغة الفرنسية'         => ['French Language', 'globe'],
            'الرياضيات'              => ['Mathematics', 'calculator'],
            'العلوم'                 => ['Science', 'atom'],
            'الفيزياء'               => ['Physics', 'atom'],
            'الكيمياء'               => ['Chemistry', 'flask'],
            'الأحياء'                => ['Biology', 'dna'],
            'الجيولوجيا وعلوم البيئة' => ['Geology & Environmental Science', 'globe'],
            'الدراسات الاجتماعية'    => ['Social Studies', 'landmark'],
            'التاريخ'                => ['History', 'landmark'],
            'الجغرافيا'              => ['Geography', 'globe'],
            'علم النفس'              => ['Psychology', 'student'],
            'علم الاجتماع'           => ['Sociology', 'users'],
            'الاقتصاد'               => ['Economics', 'chart'],
            'تكنولوجيا المعلومات'    => ['Information Technology', 'settings'],
            'التربية البدنية والصحية' => ['Physical & Health Education', 'student'],
            'التربية الفنية'         => ['Art Education', 'video'],
        ];

        $ids = [];

        foreach ($catalogue as $name => [$nameEn, $icon]) {
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

        // Older seeds used slightly different names for the same subject.
        foreach (['العلوم العامة' => 'العلوم', 'الحاسب الآلي' => 'تكنولوجيا المعلومات'] as $old => $canonical) {
            $this->mergeSubject($old, $ids[$canonical] ?? null);
        }

        return $ids;
    }

    /** Point any teaching at the canonical subject, then drop the duplicate. */
    private function mergeSubject(string $duplicateName, ?int $canonicalId): void
    {
        $duplicate = DB::table('subjects')->where('name', $duplicateName)->first();

        if (! $duplicate || ! $canonicalId || $duplicate->id === $canonicalId) {
            return;
        }

        // A teacher may already cover the canonical subject for that grade;
        // the unique key on teaching_assignments would reject the update.
        foreach (DB::table('teaching_assignments')->where('subject_id', $duplicate->id)->get() as $assignment) {
            $clash = DB::table('teaching_assignments')
                ->where('teacher_id', $assignment->teacher_id)
                ->where('subject_id', $canonicalId)
                ->where('grade_level_id', $assignment->grade_level_id)
                ->exists();

            if ($clash) {
                continue; // Leave it pointing at the duplicate; it is deleted below.
            }

            DB::table('teaching_assignments')->where('id', $assignment->id)
                ->update(['subject_id' => $canonicalId]);
        }

        DB::table('subjects')->where('id', $duplicate->id)->delete();
    }

    // ─── Curriculum ───────────────────────────────────────────────

    /** @param array<string, int> $subjects */
    private function linkCurriculum(array $subjects): void
    {
        $core = ['التربية الإسلامية', 'اللغة العربية', 'اللغة الإنجليزية', 'الرياضيات'];

        $primary = [
            ...$core,
            'العلوم',
            'تكنولوجيا المعلومات',
            'التربية البدنية والصحية',
            'التربية الفنية',
        ];

        // Social studies starts once pupils can handle it as its own subject.
        $primaryUpper = [...$primary, 'الدراسات الاجتماعية'];

        $preparatory = [
            ...$core,
            'العلوم',
            'الدراسات الاجتماعية',
            'تكنولوجيا المعلومات',
            'التربية البدنية والصحية',
            'التربية الفنية',
            'اللغة الفرنسية',
        ];

        // Grade 10 is common ground: everyone takes the three sciences before
        // choosing a track.
        $secondaryCommon = [
            ...$core,
            'الفيزياء',
            'الكيمياء',
            'الأحياء',
            'الدراسات الاجتماعية',
            'تكنولوجيا المعلومات',
            'التربية البدنية والصحية',
        ];

        $science = [
            ...$core,
            'الفيزياء',
            'الكيمياء',
            'الأحياء',
            'الجيولوجيا وعلوم البيئة',
            'تكنولوجيا المعلومات',
            'التربية البدنية والصحية',
        ];

        $literary = [
            ...$core,
            'التاريخ',
            'الجغرافيا',
            'علم النفس',
            'علم الاجتماع',
            'الاقتصاد',
            'تكنولوجيا المعلومات',
            'التربية البدنية والصحية',
        ];

        $curriculum = [
            'grade_1' => $primary,
            'grade_2' => $primary,
            'grade_3' => $primary,
            'grade_4' => $primaryUpper,
            'grade_5' => $primaryUpper,
            'grade_6' => $primaryUpper,
            'grade_7' => $preparatory,
            'grade_8' => $preparatory,
            'grade_9' => $preparatory,
            'grade_10' => $secondaryCommon,
            'grade_11_science'  => $science,
            'grade_12_science'  => $science,
            'grade_11_literary' => $literary,
            'grade_12_literary' => $literary,
        ];

        foreach ($curriculum as $gradeKey => $subjectNames) {
            $gradeId = DB::table('grade_levels')->where('key', $gradeKey)->value('id');

            if (! $gradeId) {
                continue;
            }

            foreach ($subjectNames as $name) {
                if (! isset($subjects[$name])) {
                    continue;
                }

                DB::table('grade_level_subject')->updateOrInsert(
                    ['grade_level_id' => $gradeId, 'subject_id' => $subjects[$name]],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        // Whatever a teacher is already assigned to counts as curriculum too,
        // so nothing that is being taught disappears from the browse flow.
        foreach (DB::table('teaching_assignments')->get(['grade_level_id', 'subject_id']) as $assignment) {
            DB::table('grade_level_subject')->updateOrInsert(
                ['grade_level_id' => $assignment->grade_level_id, 'subject_id' => $assignment->subject_id],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    /** The pivot replaces the single-grade column entirely. */
    private function retireSubjectGradeColumn(): void
    {
        if (! Schema::hasColumn('subjects', 'grade_level')) {
            return;
        }

        // Carry across anything the pivot does not already know about.
        foreach (DB::table('subjects')->whereNotNull('grade_level')->get(['id', 'grade_level']) as $subject) {
            if ($subject->grade_level === 'all') {
                continue;
            }

            $gradeId = DB::table('grade_levels')->where('key', $subject->grade_level)->value('id');

            if ($gradeId) {
                DB::table('grade_level_subject')->updateOrInsert(
                    ['grade_level_id' => $gradeId, 'subject_id' => $subject->id],
                    ['updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        foreach (Schema::getIndexes('subjects') as $index) {
            if (($index['primary'] ?? false) || ! in_array('grade_level', $index['columns'], true)) {
                continue;
            }

            Schema::table('subjects', fn (Blueprint $table) => $table->dropIndex($index['name']));
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropColumn('grade_level');
        });

        // The catch-all pseudo grade only existed to fill that column. Leave it
        // if a teacher somehow ended up assigned to it — a dangling foreign key
        // is worse than a stray row.
        $catchAllId = DB::table('grade_levels')->where('key', 'all')->value('id');

        if ($catchAllId && ! DB::table('teaching_assignments')->where('grade_level_id', $catchAllId)->exists()) {
            DB::table('users')->where('grade_level', 'all')->update(['grade_level' => null]);
            DB::table('grade_levels')->where('id', $catchAllId)->delete();
        }
    }
};

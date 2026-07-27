<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give the syllabus the shape a Qatari teacher actually works in:
 * a term holds units, a unit holds lessons and closes with an exam.
 *
 * Content moves from the teaching group to the teaching assignment, one level
 * up. A group is a timetable slot — "الأحد والثلاثاء" — and the syllabus is the
 * same whichever slot a student sits in; private students have an assignment
 * and no group at all. Hanging content off the assignment means the teacher
 * builds it once and every slot, group or private, gets it.
 *
 * Existing content is per-group, so several groups under one assignment carry
 * their own copy of the same lesson. The backfill collapses those copies into
 * one unit and re-points progress, questions and submissions at the survivor
 * before the losers are deleted.
 *
 * Every step checks whether it has already been applied, because MySQL commits
 * each DDL statement on its own — a failure halfway through leaves the schema
 * partly migrated, and this has to be safe to run again from the top.
 */
return new class extends Migration
{
    /** Content tables that move from a group owner to a unit owner. */
    private const OWNED_TABLES = ['group_materials', 'worksheets', 'quizzes'];

    public function up(): void
    {
        $this->createUnitsTable();

        foreach (self::OWNED_TABLES as $table) {
            $this->addUnitColumn($table);
        }

        $this->addQuizWindow();

        $this->backfillUnits();

        $this->collapseDuplicateMaterials();
        $this->collapseDuplicateWorksheets();
        $this->collapseDuplicateQuizzes();

        foreach (self::OWNED_TABLES as $table) {
            $this->dropColumnAndConstraints($table, 'teaching_group_id');
        }

        $this->addIndexIfMissing('group_materials', ['curriculum_unit_id', 'order']);
        $this->addIndexIfMissing('worksheets', ['curriculum_unit_id']);
        $this->addIndexIfMissing('quizzes', ['curriculum_unit_id']);
    }

    public function down(): void
    {
        // Groups no longer own content to point back at; this migration is one-way.
        throw new RuntimeException('لا يمكن التراجع عن تحويل المحتوى إلى وحدات دراسية.');
    }

    // ─── Schema ───────────────────────────────────────────────────

    private function createUnitsTable(): void
    {
        if (Schema::hasTable('curriculum_units')) {
            return;
        }

        Schema::create('curriculum_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['teaching_assignment_id', 'academic_term_id', 'order']);
        });
    }

    /**
     * Nullable, because a paper exam or a stray quiz may sit outside the tree
     * and because the backfill needs somewhere to write before it can enforce
     * anything.
     */
    private function addUnitColumn(string $table): void
    {
        if (Schema::hasColumn($table, 'curriculum_unit_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreignId('curriculum_unit_id')->nullable()->after('id')
                ->constrained('curriculum_units')->cascadeOnDelete();
        });
    }

    /** "متاح من / إلى" — the teacher opens an exam for a window, not forever. */
    private function addQuizWindow(): void
    {
        foreach (['available_from', 'available_until'] as $column) {
            if (Schema::hasColumn('quizzes', $column)) {
                continue;
            }

            Schema::table('quizzes', function (Blueprint $table) use ($column): void {
                $table->timestamp($column)->nullable();
            });
        }
    }

    // ─── Backfill ─────────────────────────────────────────────────

    /**
     * Give every assignment that already owns content a first unit, and move
     * that content onto it. Runs only while the group owner is still around —
     * after the drop there is nothing left to translate.
     */
    private function backfillUnits(): void
    {
        $tables = array_values(array_filter(
            self::OWNED_TABLES,
            fn (string $table): bool => Schema::hasColumn($table, 'teaching_group_id'),
        ));

        if ($tables === []) {
            return;
        }

        $groupIds = collect($tables)
            ->flatMap(fn (string $table) => DB::table($table)->whereNotNull('teaching_group_id')->distinct()->pluck('teaching_group_id'))
            ->unique();

        $groups = DB::table('teaching_groups')
            ->whereIn('id', $groupIds)
            ->get(['id', 'teaching_assignment_id', 'academic_term_id']);

        foreach ($groups->groupBy('teaching_assignment_id') as $assignmentId => $assignmentGroups) {
            $termId = $assignmentGroups->pluck('academic_term_id')->filter()->first();

            $unitId = $this->firstUnitFor((int) $assignmentId, $termId === null ? null : (int) $termId);

            if ($unitId === null) {
                continue;
            }

            foreach ($tables as $table) {
                DB::table($table)
                    ->whereIn('teaching_group_id', $assignmentGroups->pluck('id'))
                    ->whereNull('curriculum_unit_id')
                    ->update(['curriculum_unit_id' => $unitId]);
            }
        }

        // Whatever is left belongs to a group that no longer exists.
        foreach ($tables as $table) {
            DB::table($table)->whereNull('curriculum_unit_id')->delete();
        }
    }

    /** Find or create "الوحدة الأولى" for an assignment. */
    private function firstUnitFor(int $assignmentId, ?int $termId): ?int
    {
        $existing = DB::table('curriculum_units')
            ->where('teaching_assignment_id', $assignmentId)
            ->orderBy('order')
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $termId ??= AcademicTerm::currentOrNext()?->id;

        if ($termId === null) {
            return null;
        }

        return (int) DB::table('curriculum_units')->insertGetId([
            'teaching_assignment_id' => $assignmentId,
            'academic_term_id'       => $termId,
            'order'                  => 1,
            'title'                  => 'الوحدة الأولى',
            'is_published'           => true,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    // ─── De-duplication ───────────────────────────────────────────

    private function collapseDuplicateMaterials(): void
    {
        foreach ($this->duplicates('group_materials', ['curriculum_unit_id', 'title']) as [$keepId, $losingIds]) {
            DB::table('worksheets')->whereIn('lesson_id', $losingIds)->update(['lesson_id' => $keepId]);
            DB::table('quizzes')->whereIn('lesson_id', $losingIds)->update(['lesson_id' => $keepId]);
            DB::table('lesson_questions')->whereIn('lesson_id', $losingIds)->update(['lesson_id' => $keepId]);
            DB::table('live_sessions')->whereIn('lesson_id', $losingIds)->update(['lesson_id' => $keepId]);

            foreach ($losingIds as $losingId) {
                // (student_id, lesson_id) is unique, so a student who watched
                // both copies keeps the row already on the survivor. Re-read the
                // taken students each time — the previous loser just added some.
                $taken = DB::table('lesson_progress')->where('lesson_id', $keepId)->pluck('student_id');

                DB::table('lesson_progress')->where('lesson_id', $losingId)->whereIn('student_id', $taken)->delete();
                DB::table('lesson_progress')->where('lesson_id', $losingId)->update(['lesson_id' => $keepId]);
            }

            DB::table('group_materials')->whereIn('id', $losingIds)->delete();
        }
    }

    private function collapseDuplicateWorksheets(): void
    {
        foreach ($this->duplicates('worksheets', ['curriculum_unit_id', 'title', 'type']) as [$keepId, $losingIds]) {
            DB::table('worksheet_submissions')->whereIn('worksheet_id', $losingIds)->update(['worksheet_id' => $keepId]);

            DB::table('worksheets')->whereIn('id', $losingIds)->delete();
        }
    }

    private function collapseDuplicateQuizzes(): void
    {
        foreach ($this->duplicates('quizzes', ['curriculum_unit_id', 'title']) as [$keepId, $losingIds]) {
            // Questions and options cascade away with the losing quiz; the
            // attempts are a student's record and move to the survivor.
            DB::table('quiz_attempts')->whereIn('quiz_id', $losingIds)->update(['quiz_id' => $keepId]);

            DB::table('quizzes')->whereIn('id', $losingIds)->delete();
        }
    }

    /**
     * Rows sharing a key, as [survivor id, losing ids]. The lowest id wins so
     * the oldest copy — the one students have been working against — stays.
     *
     * @param  array<int, string>  $keys
     * @return iterable<int, array{0: int, 1: array<int, int>}>
     */
    private function duplicates(string $table, array $keys): iterable
    {
        $groups = DB::table($table)
            ->select($keys)
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy($keys)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $query = DB::table($table)->where('id', '!=', $group->keep_id);

            foreach ($keys as $key) {
                $group->{$key} === null ? $query->whereNull($key) : $query->where($key, $group->{$key});
            }

            $losingIds = $query->pluck('id')->all();

            if ($losingIds !== []) {
                yield [(int) $group->keep_id, $losingIds];
            }
        }
    }

    // ─── Schema helpers ───────────────────────────────────────────

    /**
     * Drop a column together with everything hanging off it.
     *
     * The two drivers want opposite things. MySQL keeps a foreign key's
     * backing index alive, so the constraint must go first and the column
     * last. SQLite has no standalone constraint — it rebuilds the table — so
     * the key and the column have to go in one alteration.
     */
    private function dropColumnAndConstraints(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (in_array($column, $foreignKey['columns'], true)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($foreignKey['name']));
                }
            }
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (($index['primary'] ?? false) || ! in_array($column, $index['columns'], true)) {
                continue;
            }

            $this->preserveOtherForeignKeys($table, $index, $column);

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index['name']));
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $isSqlite): void {
            $isSqlite
                ? $blueprint->dropConstrainedForeignId($column)
                : $blueprint->dropColumn($column);
        });
    }

    /**
     * A composite index also backs any foreign key sitting on its leftmost
     * columns — `(user_id, course_id)` is what MySQL uses for the `user_id`
     * key. Dropping it would orphan that key, so give each such column its own
     * index first.
     *
     * @param array{name: string, columns: array<int, string>} $index
     */
    private function preserveOtherForeignKeys(string $table, array $index, string $columnBeingDropped): void
    {
        $foreignKeyColumns = collect(Schema::getForeignKeys($table))
            ->flatMap(fn (array $foreignKey) => $foreignKey['columns'])
            ->unique();

        foreach ($index['columns'] as $indexedColumn) {
            if ($indexedColumn === $columnBeingDropped || ! $foreignKeyColumns->contains($indexedColumn)) {
                continue;
            }

            $this->addIndexIfMissing($table, [$indexedColumn]);
        }
    }

    /** @param array<int, string> $columns */
    private function addIndexIfMissing(string $table, array $columns, bool $unique = false, ?string $name = null): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['columns'] === $columns) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $unique, $name): void {
            $unique ? $blueprint->unique($columns, $name) : $blueprint->index($columns, $name);
        });
    }
};

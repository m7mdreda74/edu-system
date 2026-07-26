<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move every piece of teaching content off courses and onto teaching groups.
 *
 * Content keeps its `lesson_id` column names — only the owning table changes —
 * so foreign keys stay intact and the diff stays small. `course_lessons`
 * becomes `group_materials`, the one rename needed to retire the word.
 *
 * Reviews move onto the teacher rather than a group: students rate a teacher's
 * style, which is what the new browse flow asks them to judge.
 *
 * Every step checks whether it has already been applied, because MySQL commits
 * each DDL statement on its own — a failure halfway through leaves the schema
 * partly migrated, and this has to be safe to run again from the top.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->repointGroupMaterials();
        $this->repointLessonProgress();
        $this->repointSimpleGroupOwner('quizzes');
        $this->repointSimpleGroupOwner('worksheets');
        $this->repointPurchaseRequests();
        $this->repointReviewsToTeacher();
        $this->repointConversations();
        $this->detachLiveSessions();

        if (Schema::hasTable('course_lessons') && ! Schema::hasTable('group_materials')) {
            Schema::rename('course_lessons', 'group_materials');
        }
    }

    public function down(): void
    {
        // Courses no longer exist to point back at; this migration is one-way.
        throw new RuntimeException('لا يمكن التراجع عن تحويل المحتوى إلى مجموعات التدريس.');
    }

    /** Video lessons and files now belong to a weekly group. */
    private function repointGroupMaterials(): void
    {
        if (! Schema::hasTable('course_lessons') || ! Schema::hasColumn('course_lessons', 'course_id')) {
            return;
        }

        $this->addOwnerColumn('course_lessons', 'teaching_group_id', 'teaching_groups');
        $this->copyOwnerFromMap('course_lessons', 'teaching_group_id');
        $this->dropColumnAndConstraints('course_lessons', 'course_id');

        $this->addIndexIfMissing('course_lessons', ['teaching_group_id', 'order']);
    }

    /**
     * Progress was keyed to an enrollment. Enrollments are going away, and
     * progress should survive a lapsed month anyway, so key it to the student.
     */
    private function repointLessonProgress(): void
    {
        if (! Schema::hasColumn('lesson_progress', 'enrollment_id')) {
            return;
        }

        if (! Schema::hasColumn('lesson_progress', 'student_id')) {
            Schema::table('lesson_progress', function (Blueprint $table): void {
                $table->foreignId('student_id')->nullable()->after('id')
                    ->constrained('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('enrollments')) {
            foreach (DB::table('lesson_progress')->whereNull('student_id')->orderBy('id')->cursor() as $row) {
                $studentId = DB::table('enrollments')->where('id', $row->enrollment_id)->value('user_id');

                if ($studentId) {
                    DB::table('lesson_progress')->where('id', $row->id)->update(['student_id' => $studentId]);
                }
            }
        }

        // Rows we could not attribute to a student are meaningless now.
        DB::table('lesson_progress')->whereNull('student_id')->delete();

        // Collapse duplicates before the new unique key goes on.
        $this->deleteDuplicates('lesson_progress', ['student_id', 'lesson_id']);

        $this->dropColumnAndConstraints('lesson_progress', 'enrollment_id');

        $this->addIndexIfMissing('lesson_progress', ['student_id', 'lesson_id'], unique: true);
        $this->addIndexIfMissing('lesson_progress', ['student_id', 'is_completed']);
    }

    /** Quizzes and worksheets: swap the course owner for a group owner. */
    private function repointSimpleGroupOwner(string $table): void
    {
        if (! Schema::hasColumn($table, 'course_id')) {
            return;
        }

        $this->addOwnerColumn($table, 'teaching_group_id', 'teaching_groups');
        $this->copyOwnerFromMap($table, 'teaching_group_id');
        $this->dropColumnAndConstraints($table, 'course_id');

        $this->addIndexIfMissing($table, ['teaching_group_id']);
    }

    /** A parent now approves a subscription to a group, not a course purchase. */
    private function repointPurchaseRequests(): void
    {
        if (! Schema::hasColumn('purchase_requests', 'course_id')) {
            return;
        }

        $this->addOwnerColumn('purchase_requests', 'teaching_group_id', 'teaching_groups', 'parent_user_id');
        $this->copyOwnerFromMap('purchase_requests', 'teaching_group_id');
        $this->dropColumnAndConstraints('purchase_requests', 'course_id');

        $this->addIndexIfMissing('purchase_requests', ['teaching_group_id']);
    }

    /** Ratings follow the teacher, since that is what students now choose. */
    private function repointReviewsToTeacher(): void
    {
        if (! Schema::hasColumn('reviews', 'course_id')) {
            return;
        }

        if (! Schema::hasColumn('reviews', 'teacher_id')) {
            Schema::table('reviews', function (Blueprint $table): void {
                $table->foreignId('teacher_id')->nullable()->after('user_id')
                    ->constrained('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('courses')) {
            foreach (DB::table('reviews')->whereNull('teacher_id')->orderBy('id')->cursor() as $review) {
                $teacherId = DB::table('courses')->where('id', $review->course_id)->value('teacher_id');

                if ($teacherId) {
                    DB::table('reviews')->where('id', $review->id)->update(['teacher_id' => $teacherId]);
                }
            }
        }

        DB::table('reviews')->whereNull('teacher_id')->delete();

        // A student rated several courses by one teacher — keep their latest.
        $this->deleteDuplicates('reviews', ['user_id', 'teacher_id']);

        $this->dropColumnAndConstraints('reviews', 'course_id');

        $this->addIndexIfMissing('reviews', ['user_id', 'teacher_id'], unique: true);
        $this->addIndexIfMissing('reviews', ['teacher_id', 'is_approved']);
    }

    /** Chat threads are per student ↔ teacher ↔ subject, i.e. per assignment. */
    private function repointConversations(): void
    {
        if (! Schema::hasColumn('conversations', 'course_id')) {
            return;
        }

        if (! Schema::hasColumn('conversations', 'teaching_assignment_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->foreignId('teaching_assignment_id')->nullable()->after('id')
                    ->constrained('teaching_assignments')->cascadeOnDelete();
            });
        }

        foreach (DB::table('course_group_map')->cursor() as $map) {
            DB::table('conversations')
                ->where('course_id', $map->course_id)
                ->update(['teaching_assignment_id' => $map->teaching_assignment_id]);
        }

        DB::table('conversations')->whereNull('teaching_assignment_id')->delete();

        $this->deleteDuplicates('conversations', ['teaching_assignment_id', 'student_id', 'teacher_id']);

        $this->dropColumnAndConstraints('conversations', 'course_id');

        $this->addIndexIfMissing(
            'conversations',
            ['teaching_assignment_id', 'student_id', 'teacher_id'],
            unique: true,
            name: 'conversations_assignment_participants_unique',
        );
    }

    /**
     * Live sessions already carry `teaching_group_id` / `private_session_slot_id`.
     * Backfill the group for course-era sessions, then drop the course link.
     */
    private function detachLiveSessions(): void
    {
        if (! Schema::hasColumn('live_sessions', 'course_id')) {
            return;
        }

        foreach (DB::table('course_group_map')->cursor() as $map) {
            DB::table('live_sessions')
                ->where('course_id', $map->course_id)
                ->whereNull('teaching_group_id')
                ->update(['teaching_group_id' => $map->teaching_group_id]);
        }

        $this->dropColumnAndConstraints('live_sessions', 'course_id');
    }

    // ─── Schema helpers ───────────────────────────────────────────

    private function addOwnerColumn(string $table, string $column, string $references, string $after = 'id'): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $after): void {
            $blueprint->foreignId($column)->nullable()->after($after)
                ->constrained($references)->cascadeOnDelete();
        });
    }

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

    // ─── Data helpers ─────────────────────────────────────────────

    /** Fill a new owner column by translating each row's course via the map. */
    private function copyOwnerFromMap(string $table, string $column): void
    {
        foreach (DB::table('course_group_map')->cursor() as $map) {
            DB::table($table)
                ->where('course_id', $map->course_id)
                ->whereNull($column)
                ->update([$column => $map->teaching_group_id]);
        }

        DB::table($table)->whereNull($column)->delete();
    }

    /** Keep the newest row for each key combination, drop the rest. */
    private function deleteDuplicates(string $table, array $keys): void
    {
        $duplicates = DB::table($table)
            ->select($keys)
            ->selectRaw('MAX(id) as keep_id')
            ->groupBy($keys)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $query = DB::table($table)->where('id', '!=', $duplicate->keep_id);

            foreach ($keys as $key) {
                $query->where($key, $duplicate->{$key});
            }

            $query->delete();
        }
    }
};

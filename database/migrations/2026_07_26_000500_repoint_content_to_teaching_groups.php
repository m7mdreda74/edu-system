<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        Schema::rename('course_lessons', 'group_materials');
    }

    public function down(): void
    {
        // Courses no longer exist to point back at; this migration is one-way.
        throw new RuntimeException('لا يمكن التراجع عن تحويل المحتوى إلى مجموعات التدريس.');
    }

    /** Video lessons and files now belong to a weekly group. */
    private function repointGroupMaterials(): void
    {
        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->foreignId('teaching_group_id')->nullable()->after('id')
                ->constrained('teaching_groups')->cascadeOnDelete();
        });

        $this->copyOwnerFromMap('course_lessons', 'teaching_group_id');

        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->dropIndex(['course_id', 'order']);
            $table->dropConstrainedForeignId('course_id');
            $table->index(['teaching_group_id', 'order']);
        });
    }

    /**
     * Progress was keyed to an enrollment. Enrollments are going away, and
     * progress should survive a lapsed month anyway, so key it to the student.
     */
    private function repointLessonProgress(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table): void {
            $table->foreignId('student_id')->nullable()->after('id')
                ->constrained('users')->cascadeOnDelete();
        });

        if (Schema::hasTable('enrollments')) {
            foreach (DB::table('lesson_progress')->orderBy('id')->cursor() as $row) {
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

        Schema::table('lesson_progress', function (Blueprint $table): void {
            // A performance index from an earlier migration also covers this
            // column and would dangle once it is gone.
            $table->dropIndex('idx_lesson_progress_completion');
            $table->dropUnique(['enrollment_id', 'lesson_id']);
            $table->dropConstrainedForeignId('enrollment_id');
            $table->unique(['student_id', 'lesson_id']);
            $table->index(['student_id', 'is_completed'], 'idx_lesson_progress_completion');
        });
    }

    /** Quizzes and worksheets: swap the course owner for a group owner. */
    private function repointSimpleGroupOwner(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->foreignId('teaching_group_id')->nullable()->after('id')
                ->constrained('teaching_groups')->cascadeOnDelete();
        });

        $this->copyOwnerFromMap($table, 'teaching_group_id');

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('course_id');
            $blueprint->index('teaching_group_id');
        });
    }

    /** A parent now approves a subscription to a group, not a course purchase. */
    private function repointPurchaseRequests(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->foreignId('teaching_group_id')->nullable()->after('parent_user_id')
                ->constrained('teaching_groups')->cascadeOnDelete();
        });

        $this->copyOwnerFromMap('purchase_requests', 'teaching_group_id');

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
            $table->index('teaching_group_id');
        });
    }

    /** Ratings follow the teacher, since that is what students now choose. */
    private function repointReviewsToTeacher(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('teacher_id')->nullable()->after('user_id')
                ->constrained('users')->cascadeOnDelete();
        });

        if (Schema::hasTable('courses')) {
            foreach (DB::table('reviews')->orderBy('id')->cursor() as $review) {
                $teacherId = DB::table('courses')->where('id', $review->course_id)->value('teacher_id');

                if ($teacherId) {
                    DB::table('reviews')->where('id', $review->id)->update(['teacher_id' => $teacherId]);
                }
            }
        }

        DB::table('reviews')->whereNull('teacher_id')->delete();

        // A student rated several courses by one teacher — keep their latest.
        $this->deleteDuplicates('reviews', ['user_id', 'teacher_id']);

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'course_id']);
            $table->dropConstrainedForeignId('course_id');
            $table->unique(['user_id', 'teacher_id']);
            $table->index(['teacher_id', 'is_approved']);
        });
    }

    /** Chat threads are per student ↔ teacher ↔ subject, i.e. per assignment. */
    private function repointConversations(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('teaching_assignment_id')->nullable()->after('id')
                ->constrained('teaching_assignments')->cascadeOnDelete();
        });

        foreach (DB::table('course_group_map')->cursor() as $map) {
            DB::table('conversations')
                ->where('course_id', $map->course_id)
                ->update(['teaching_assignment_id' => $map->teaching_assignment_id]);
        }

        DB::table('conversations')->whereNull('teaching_assignment_id')->delete();

        $this->deleteDuplicates('conversations', ['teaching_assignment_id', 'student_id', 'teacher_id']);

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique(['course_id', 'student_id', 'teacher_id']);
            $table->dropConstrainedForeignId('course_id');
            $table->unique(['teaching_assignment_id', 'student_id', 'teacher_id'], 'conversations_assignment_participants_unique');
        });
    }

    /**
     * Live sessions already carry `teaching_group_id` / `private_session_slot_id`.
     * Backfill the group for course-era sessions, then drop the course link.
     */
    private function detachLiveSessions(): void
    {
        foreach (DB::table('course_group_map')->cursor() as $map) {
            DB::table('live_sessions')
                ->where('course_id', $map->course_id)
                ->whereNull('teaching_group_id')
                ->update(['teaching_group_id' => $map->teaching_group_id]);
        }

        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('course_id');
        });
    }

    /** Fill a new owner column by translating each row's course via the map. */
    private function copyOwnerFromMap(string $table, string $column): void
    {
        foreach (DB::table('course_group_map')->cursor() as $map) {
            DB::table($table)
                ->where('course_id', $map->course_id)
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

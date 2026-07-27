<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A teacher specialises in exactly one subject.
 *
 * A subject can be taught by many teachers — that is the whole point of the
 * browse flow — but the reverse does not hold: a teacher is the maths teacher,
 * or the physics teacher, not both. Putting the subject on the teacher makes
 * that a fact about them rather than something re-derived from their timetable.
 *
 * Existing teachers are given the subject they teach most. Anything they teach
 * outside it is left alone rather than deleted — those groups have students
 * with paid subscriptions in them, and quietly cancelling a month of somebody's
 * tuition to satisfy a new rule is not a migration's call to make. The admin
 * screen surfaces the conflicts to resolve by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'subject_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('subject_id')->nullable()->after('grade_level')
                    ->constrained('subjects')->nullOnDelete();
            });
        }

        $this->assignDominantSubject();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_id');
        });
    }

    /**
     * The subject a teacher covers most: by number of assignments first, then
     * by how many groups actually run under it — teaching three grades one
     * subject beats a single group of another.
     */
    private function assignDominantSubject(): void
    {
        $teacherIds = DB::table('teaching_assignments')->distinct()->pluck('teacher_id');

        foreach ($teacherIds as $teacherId) {
            $candidates = DB::table('teaching_assignments as ta')
                ->leftJoin('teaching_groups as tg', 'tg.teaching_assignment_id', '=', 'ta.id')
                ->where('ta.teacher_id', $teacherId)
                ->groupBy('ta.subject_id')
                ->select('ta.subject_id', DB::raw('COUNT(DISTINCT ta.id) as assignments'), DB::raw('COUNT(tg.id) as group_count'))
                ->orderByDesc('assignments')
                ->orderByDesc('group_count')
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            DB::table('users')->where('id', $teacherId)
                ->update(['subject_id' => $candidates->first()->subject_id]);
        }
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge every existing course onto the teaching-group model.
 *
 * A course already carried the three facts a teaching assignment needs —
 * teacher, subject and grade — so each one maps onto an assignment, and gets a
 * default weekly group to hang its content and students off. Enrollments become
 * the first month's subscription.
 *
 * The `course_group_map` table it leaves behind is what the following
 * migrations use to repoint content; the drop migration removes it.
 */
return new class extends Migration
{
    private const DEFAULT_CAPACITY   = 30;
    private const DEFAULT_DAY        = 0;       // Sunday
    private const DEFAULT_START      = '16:00:00';
    private const DEFAULT_END        = '17:30:00';
    private const DEFAULT_DURATION   = 90;

    public function up(): void
    {
        Schema::create('course_group_map', function (Blueprint $table): void {
            $table->unsignedBigInteger('course_id')->primary();
            $table->unsignedBigInteger('teaching_assignment_id');
            $table->unsignedBigInteger('teaching_group_id');
        });

        if (! Schema::hasTable('courses')) {
            return;
        }

        $now              = now();
        $fallbackGradeId  = DB::table('grade_levels')->orderBy('id')->value('id');

        foreach (DB::table('courses')->orderBy('id')->cursor() as $course) {
            $gradeLevelId = $this->resolveGradeLevelId($course, $fallbackGradeId);

            if (! $gradeLevelId || ! $course->teacher_id || ! $course->subject_id) {
                continue; // Nothing sane to map it onto.
            }

            $assignmentId = $this->firstOrCreateAssignment(
                (int) $course->teacher_id,
                (int) $course->subject_id,
                (int) $gradeLevelId,
                $now,
            );

            $groupId = $this->firstOrCreateGroup($assignmentId, $course, $now);

            DB::table('course_group_map')->insert([
                'course_id'              => $course->id,
                'teaching_assignment_id' => $assignmentId,
                'teaching_group_id'      => $groupId,
            ]);
        }

        $this->convertEnrollmentsToSubscriptions($now);
    }

    public function down(): void
    {
        Schema::dropIfExists('course_group_map');
    }

    /** Course grade is a key string ("grade_12"); assignments need the row id. */
    private function resolveGradeLevelId(object $course, ?int $fallback): ?int
    {
        $key = $course->grade_level ?? null;

        if ($key && $key !== 'all') {
            $id = DB::table('grade_levels')->where('key', $key)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        $subjectKey = DB::table('subjects')->where('id', $course->subject_id)->value('grade_level');
        if ($subjectKey && $subjectKey !== 'all') {
            $id = DB::table('grade_levels')->where('key', $subjectKey)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return $fallback;
    }

    private function firstOrCreateAssignment(int $teacherId, int $subjectId, int $gradeLevelId, $now): int
    {
        $existing = DB::table('teaching_assignments')
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('grade_level_id', $gradeLevelId)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('teaching_assignments')->insertGetId([
            'teacher_id'            => $teacherId,
            'subject_id'            => $subjectId,
            'grade_level_id'        => $gradeLevelId,
            'private_monthly_price' => 0,
            'currency'              => 'QAR',
            'accepts_private'       => true,
            'is_active'             => true,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
    }

    private function firstOrCreateGroup(int $assignmentId, object $course, $now): int
    {
        // Group names are unique per assignment; suffix on collision.
        $baseName = trim((string) ($course->title ?? 'مجموعة')) ?: 'مجموعة';
        $baseName = mb_substr($baseName, 0, 90);
        $name     = $baseName;
        $suffix   = 2;

        while (DB::table('teaching_groups')
            ->where('teaching_assignment_id', $assignmentId)
            ->where('name', $name)
            ->exists()
        ) {
            $name = mb_substr($baseName, 0, 90) . ' ' . $suffix++;
        }

        $monthlyPrice = (int) ($course->discount_price ?? $course->price ?? 0);

        $groupId = (int) DB::table('teaching_groups')->insertGetId([
            'teaching_assignment_id' => $assignmentId,
            'name'                   => $name,
            'capacity'               => self::DEFAULT_CAPACITY,
            'monthly_price'          => $monthlyPrice,
            'currency'               => 'QAR',
            'day_of_week'            => self::DEFAULT_DAY,
            'start_time'             => self::DEFAULT_START,
            'end_time'               => self::DEFAULT_END,
            'duration_minutes'       => self::DEFAULT_DURATION,
            'timezone'               => 'Asia/Qatar',
            'is_active'              => (bool) ($course->is_published ?? false),
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);

        DB::table('teaching_group_schedules')->insert([
            'teaching_group_id' => $groupId,
            'day_of_week'       => self::DEFAULT_DAY,
            'start_time'        => self::DEFAULT_START,
            'end_time'          => self::DEFAULT_END,
            'duration_minutes'  => self::DEFAULT_DURATION,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        return $groupId;
    }

    /**
     * Every enrollment becomes one month of subscription starting the day the
     * student enrolled, so existing students keep access while the new billing
     * cycle takes over.
     */
    private function convertEnrollmentsToSubscriptions($now): void
    {
        if (! Schema::hasTable('enrollments')) {
            return;
        }

        foreach (DB::table('enrollments')->orderBy('id')->cursor() as $enrollment) {
            $map = DB::table('course_group_map')->where('course_id', $enrollment->course_id)->first();

            if (! $map) {
                continue;
            }

            $periodStart = \Illuminate\Support\Carbon::parse($enrollment->enrolled_at ?? $enrollment->created_at ?? $now)->startOfDay();
            $periodEnd   = (clone $periodStart)->addMonth();

            $alreadyExists = DB::table('subscriptions')
                ->where('student_id', $enrollment->user_id)
                ->where('teaching_group_id', $map->teaching_group_id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            DB::table('subscriptions')->insert([
                'student_id'             => $enrollment->user_id,
                'type'                   => 'group',
                'teaching_assignment_id' => $map->teaching_assignment_id,
                'teaching_group_id'      => $map->teaching_group_id,
                'monthly_price'          => (int) DB::table('teaching_groups')->where('id', $map->teaching_group_id)->value('monthly_price'),
                'currency'               => 'QAR',
                'period_start'           => $periodStart->toDateString(),
                'period_end'             => $periodEnd->toDateString(),
                'status'                 => $periodEnd->isFuture() ? 'active' : 'expired',
                'auto_renew'             => false,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);

            // Keep the seat reservation consistent with the new booking model.
            $hasBooking = DB::table('session_bookings')
                ->where('student_id', $enrollment->user_id)
                ->where('teaching_group_id', $map->teaching_group_id)
                ->exists();

            if (! $hasBooking) {
                DB::table('session_bookings')->insert([
                    'student_id'        => $enrollment->user_id,
                    'teaching_group_id' => $map->teaching_group_id,
                    'status'            => 'confirmed',
                    'booked_at'         => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }
    }
};

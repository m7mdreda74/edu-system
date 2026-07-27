<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce one subject per teacher on the data that already exists.
 *
 * The previous migration recorded each teacher's subject but left anything
 * outside it in place, because deleting it takes paying students' groups with
 * it. That call has now been made: whatever a teacher covers outside their
 * subject goes.
 *
 * The delete cascades — assignment → groups → bookings, subscriptions and
 * content. Payments survive with their subscription link nulled, so the
 * revenue history stays intact and auditable even though what was bought no
 * longer exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'subject_id')) {
            return;
        }

        // A teacher with no recorded subject has nothing to be off it.
        $offSubject = DB::table('teaching_assignments as ta')
            ->join('users as u', 'u.id', '=', 'ta.teacher_id')
            ->whereNotNull('u.subject_id')
            ->whereColumn('ta.subject_id', '!=', 'u.subject_id')
            ->pluck('ta.id');

        if ($offSubject->isEmpty()) {
            return;
        }

        // Detach payments first so the revenue record is not cascaded away
        // with the subscription it belonged to.
        $groupIds = DB::table('teaching_groups')->whereIn('teaching_assignment_id', $offSubject)->pluck('id');

        $subscriptionIds = DB::table('subscriptions')
            ->whereIn('teaching_assignment_id', $offSubject)
            ->orWhereIn('teaching_group_id', $groupIds)
            ->pluck('id');

        if ($subscriptionIds->isNotEmpty()) {
            DB::table('payments')->whereIn('subscription_id', $subscriptionIds)
                ->update(['subscription_id' => null]);
        }

        DB::table('teaching_assignments')->whereIn('id', $offSubject)->delete();
    }

    public function down(): void
    {
        throw new RuntimeException('لا يمكن استرجاع تكليفات التدريس المحذوفة.');
    }
};

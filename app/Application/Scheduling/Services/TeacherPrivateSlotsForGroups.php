<?php

declare(strict_types=1);

namespace App\Application\Scheduling\Services;

use App\Domain\Scheduling\Models\SessionBooking;
use Illuminate\Support\Collection;

class TeacherPrivateSlotsForGroups
{
    /** Private lessons are shown only to the teacher who owns their active assignment. */
    public function forTeacher(int $teacherId): Collection
    {
        return SessionBooking::query()
            ->where('status', 'confirmed')
            ->whereNotNull('private_session_slot_id')
            ->whereHas('privateSlot', fn ($query) => $query
                ->where('status', '!=', 'cancelled')
                ->whereHas('assignment', fn ($assignment) => $assignment
                    ->where('teacher_id', $teacherId)
                    ->where('is_active', true)))
            ->with([
                'student:id,name,email,avatar',
                'privateSlot.assignment.subject:id,name',
                'privateSlot.assignment.gradeLevel:id,key,name,stage,track',
            ])
            ->get()
            ->groupBy('private_session_slot_id')
            ->map(function (Collection $bookings): array {
                $slot = $bookings->first()->privateSlot;
                $assignment = $slot->assignment;
                $start = $slot->starts_at->copy()->timezone($slot->timezone);
                $end = $slot->ends_at->copy()->timezone($slot->timezone);
                $students = $bookings->map(fn (SessionBooking $booking) => $booking->student)
                    ->filter()
                    ->unique('id')
                    ->values()
                    ->map(fn ($student): array => $student->only(['id', 'name', 'email', 'avatar']));

                return [
                    'id' => $slot->id,
                    'source' => 'private_slot',
                    'assignment_id' => $assignment->id,
                    'name' => 'حصة برايفت'.($assignment->subject ? ' - '.$assignment->subject->name : ''),
                    'is_private' => true,
                    'is_active' => true,
                    'capacity' => 1,
                    'subject' => $assignment->subject?->only(['id', 'name']),
                    'grade' => $assignment->gradeLevel?->only(['id', 'key', 'name', 'stage', 'track']),
                    'schedules' => [[
                        'id' => $slot->id,
                        'date' => $start->toDateString(),
                        'end_date' => $end->toDateString(),
                        'day_of_week' => $start->dayOfWeek,
                        'start_time' => $start->format('H:i'),
                        'end_time' => $end->format('H:i'),
                        'timezone' => $slot->timezone,
                    ]],
                    'students_count' => $students->count(),
                    'students' => $students->all(),
                ];
            })
            ->sortBy(fn (array $slot) => $slot['schedules'][0]['date'].' '.$slot['schedules'][0]['start_time'])
            ->values();
    }
}

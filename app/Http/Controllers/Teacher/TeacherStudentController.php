<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeacherStudentController extends Controller
{
    public function index(Request $request): Response
    {
        $teacherId = (int) Auth::id();

        $assignmentIds = TeachingAssignment::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->pluck('id');

        $teacherGroups = TeachingGroup::whereIn('teaching_assignment_id', $assignmentIds)
            ->with(['assignment.subject:id,name', 'assignment.gradeLevel:id,key,name'])
            ->get(['id', 'name', 'teaching_assignment_id']);

        $selectedGroupId = $request->filled('group_id') ? (int) $request->input('group_id') : null;
        $search = trim((string) $request->input('q', ''));

        $teacherGroupIds = $teacherGroups->pluck('id')->all();
        $targetGroupIds = $selectedGroupId ? [$selectedGroupId] : $teacherGroupIds;

        // 1. Group Bookings
        $groupBookings = ! empty($targetGroupIds)
            ? SessionBooking::with([
                'student:id,name,email,avatar',
                'group:id,name,teaching_assignment_id',
                'group.assignment.subject:id,name',
                'group.assignment.gradeLevel:id,key,name',
            ])
                ->whereIn('teaching_group_id', $targetGroupIds)
                ->where('status', 'confirmed')
                ->get()
            : collect();

        // 2. Group Subscriptions
        $subscriptions = ! empty($targetGroupIds)
            ? Subscription::with([
                'student:id,name,email,avatar',
                'group:id,name,teaching_assignment_id',
                'group.assignment.subject:id,name',
                'group.assignment.gradeLevel:id,key,name',
            ])
                ->whereIn('teaching_group_id', $targetGroupIds)
                ->where('status', 'active')
                ->get()
            : collect();

        // 3. Private Bookings (if no group filter is applied)
        $privateBookings = collect();
        if (! $selectedGroupId) {
            $privateSlotIds = PrivateSessionSlot::whereIn('teaching_assignment_id', $assignmentIds)->pluck('id')->all();
            $privateBookings = ! empty($privateSlotIds)
                ? SessionBooking::with([
                    'student:id,name,email,avatar',
                    'privateSlot:id,teaching_assignment_id,starts_at,ends_at,is_free_intro',
                    'privateSlot.assignment.subject:id,name',
                    'privateSlot.assignment.gradeLevel:id,key,name',
                ])
                    ->whereIn('private_session_slot_id', $privateSlotIds)
                    ->where('status', 'confirmed')
                    ->get()
                : collect();
        }

        // Aggregate by distinct student
        $studentMap = [];

        foreach ($groupBookings as $booking) {
            $student = $booking->student;
            if (! $student) {
                continue;
            }

            $id = $student->id;
            if (! isset($studentMap[$id])) {
                $studentMap[$id] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'avatar' => $student->avatar,
                    'groups' => [],
                    'subjects' => [],
                    'has_group' => true,
                    'has_private' => false,
                    'joined_at' => $booking->booked_at?->toIso8601String() ?? $booking->created_at?->toIso8601String(),
                ];
            }

            if ($booking->group) {
                $studentMap[$id]['groups'][$booking->group->id] = $booking->group->name;
                if ($booking->group->assignment?->subject) {
                    $studentMap[$id]['subjects'][$booking->group->assignment->subject->id] = $booking->group->assignment->subject->name;
                }
            }
        }

        foreach ($subscriptions as $sub) {
            $student = $sub->student;
            if (! $student) {
                continue;
            }

            $id = $student->id;
            if (! isset($studentMap[$id])) {
                $studentMap[$id] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'avatar' => $student->avatar,
                    'groups' => [],
                    'subjects' => [],
                    'has_group' => true,
                    'has_private' => false,
                    'joined_at' => $sub->period_start?->toIso8601String() ?? $sub->created_at?->toIso8601String(),
                ];
            }

            if ($sub->group) {
                $studentMap[$id]['groups'][$sub->group->id] = $sub->group->name;
                if ($sub->group->assignment?->subject) {
                    $studentMap[$id]['subjects'][$sub->group->assignment->subject->id] = $sub->group->assignment->subject->name;
                }
            }
        }

        foreach ($privateBookings as $booking) {
            $student = $booking->student;
            if (! $student) {
                continue;
            }

            $id = $student->id;
            if (! isset($studentMap[$id])) {
                $studentMap[$id] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'avatar' => $student->avatar,
                    'groups' => [],
                    'subjects' => [],
                    'has_group' => false,
                    'has_private' => true,
                    'joined_at' => $booking->booked_at?->toIso8601String() ?? $booking->created_at?->toIso8601String(),
                ];
            } else {
                $studentMap[$id]['has_private'] = true;
            }

            if ($booking->privateSlot?->assignment?->subject) {
                $studentMap[$id]['subjects'][$booking->privateSlot->assignment->subject->id] = $booking->privateSlot->assignment->subject->name;
            }
        }

        // Attendance count for each student with this teacher
        $studentIds = array_keys($studentMap);
        $teacherSessionIds = LiveSession::where('teacher_id', $teacherId)->pluck('id')->all();
        $attendanceCounts = (! empty($studentIds) && ! empty($teacherSessionIds))
            ? LiveSessionAttendee::query()
                ->whereIn('user_id', $studentIds)
                ->whereIn('live_session_id', $teacherSessionIds)
                ->groupBy('user_id')
                ->selectRaw('user_id, count(*) as count')
                ->pluck('count', 'user_id')
            : collect();

        $students = collect($studentMap)->map(function ($s) use ($attendanceCounts) {
            $s['groups'] = array_values($s['groups']);
            $s['subjects'] = array_values($s['subjects']);
            $s['attended_sessions'] = (int) ($attendanceCounts[$s['id']] ?? 0);

            return $s;
        });

        // Filter by search query if provided
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $students = $students->filter(function ($s) use ($searchLower) {
                return str_contains(mb_strtolower($s['name']), $searchLower)
                    || str_contains(mb_strtolower($s['email']), $searchLower);
            });
        }

        $studentsList = $students->sortBy('name')->values();

        return Inertia::render('Teacher/Students', [
            'students' => $studentsList,
            'groups' => $teacherGroups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'subject' => $g->assignment?->subject?->name,
            ]),
            'filters' => [
                'q' => $search,
                'group_id' => $selectedGroupId,
            ],
            'stats' => [
                'total_students' => $studentsList->count(),
                'group_students' => $studentsList->where('has_group', true)->count(),
                'private_students' => $studentsList->where('has_private', true)->count(),
            ],
        ]);
    }
}

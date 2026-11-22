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

        $teacherGroupIds = TeachingGroup::whereIn('teaching_assignment_id', $assignmentIds)->pluck('id')->all();
        $selectedType = in_array($request->input('type'), ['group', 'private'], true)
            ? $request->input('type')
            : null;
        $search = trim((string) $request->input('q', ''));

        // 1. Group Bookings
        $groupBookings = ! empty($teacherGroupIds)
            ? SessionBooking::with([
                'student:id,name,email,avatar',
                'group:id,name,teaching_assignment_id,is_private,capacity',
                'group.assignment.subject:id,name',
                'group.assignment.gradeLevel:id,key,name',
            ])
                ->whereIn('teaching_group_id', $teacherGroupIds)
                ->where('status', 'confirmed')
                ->get()
            : collect();

        // 2. Group Subscriptions
        $subscriptions = ! empty($teacherGroupIds)
            ? Subscription::with([
                'student:id,name,email,avatar',
                'group:id,name,teaching_assignment_id,is_private,capacity',
                'group.assignment.subject:id,name',
                'group.assignment.gradeLevel:id,key,name',
            ])
                ->whereIn('teaching_group_id', $teacherGroupIds)
                ->where('status', 'active')
                ->get()
            : collect();

        // 3. Private Bookings
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
                    'has_group' => false,
                    'has_private' => false,
                    'joined_at' => $booking->booked_at?->toIso8601String() ?? $booking->created_at?->toIso8601String(),
                ];
            }

            if ($booking->group) {
                $studentMap[$id][$booking->group->isPrivate() ? 'has_private' : 'has_group'] = true;
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
                    'has_group' => false,
                    'has_private' => false,
                    'joined_at' => $sub->period_start?->toIso8601String() ?? $sub->created_at?->toIso8601String(),
                ];
            }

            if ($sub->group) {
                $studentMap[$id][$sub->group->isPrivate() ? 'has_private' : 'has_group'] = true;
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

        $allStudents = collect($studentMap)->map(function ($s) use ($attendanceCounts) {
            $s['groups'] = array_values($s['groups']);
            $s['subjects'] = array_values($s['subjects']);
            $s['attended_sessions'] = (int) ($attendanceCounts[$s['id']] ?? 0);

            return $s;
        });

        $students = $allStudents;

        if ($selectedType) {
            $typeKey = $selectedType === 'group' ? 'has_group' : 'has_private';
            $students = $students->filter(fn ($student) => $student[$typeKey]);
        }

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
            'filters' => [
                'q' => $search,
                'type' => $selectedType,
            ],
            'stats' => [
                'total_students' => $allStudents->count(),
                'group_students' => $allStudents->where('has_group', true)->count(),
                'private_students' => $allStudents->where('has_private', true)->count(),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Http\Controllers\Controller;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

class ParentPrivateLessonRequestController extends Controller
{
    public function store(Request $request, int $assignmentId): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            [$privateRequest, $conversation] = DB::transaction(function () use ($assignmentId, $validated): array {
                $parent = Auth::user();

                abort_unless(
                    ParentStudentLink::where('parent_user_id', $parent->id)
                        ->where('student_user_id', $validated['student_id'])
                        ->whereNotNull('verified_at')
                        ->exists(),
                    403,
                    'هذا الطالب غير مرتبط بحسابك.',
                );

                $student = \App\Domain\User\Models\User::findOrFail($validated['student_id']);
                $assignment = TeachingAssignment::with([
                        'teacher:id,name,is_active',
                        'subject:id,name',
                        'gradeLevel:id,key',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($assignmentId);

                if (! $assignment->is_active || ! $assignment->teacher?->is_active || ! $assignment->offersPrivate()) {
                    throw new LogicException('الحصص البرايفت غير متاحة مع هذا المدرس حاليًا.');
                }

                if ($assignment->gradeLevel?->key !== $student->grade_level) {
                    throw new LogicException('هذا المدرس لا يدرّس الصف الدراسي للطالب.');
                }

                if (Subscription::active()
                    ->where('student_id', $student->id)
                    ->where('teaching_assignment_id', $assignment->id)
                    ->where('type', Subscription::TYPE_PRIVATE)
                    ->exists()) {
                    throw new LogicException('لدى الطالب اشتراك برايفت نشط بالفعل مع هذا المدرس.');
                }

                $conversation = Conversation::firstOrCreate(
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'student_id' => $student->id,
                        'teacher_id' => $assignment->teacher_id,
                    ],
                    ['last_message_at' => now()],
                );
                $conversation->participants()->syncWithoutDetaching([
                    $parent->id => ['participant_role' => 'parent'],
                ]);

                $privateRequest = PrivateLessonRequest::where('student_id', $student->id)
                    ->where('teaching_assignment_id', $assignment->id)
                    ->where('status', PrivateLessonRequest::STATUS_PENDING)
                    ->first();

                if ($privateRequest) {
                    return [$privateRequest, $conversation];
                }

                $privateRequest = PrivateLessonRequest::create([
                    'student_id' => $student->id,
                    'teaching_assignment_id' => $assignment->id,
                    'conversation_id' => $conversation->id,
                    'student_note' => $validated['note'] ?? null,
                    'status' => PrivateLessonRequest::STATUS_PENDING,
                ]);

                $message = 'ولي الأمر يطلب حجز حصص برايفت للطالب '.$student->name.'، ونود الاتفاق على الموعد المناسب.'
                    .(! empty($validated['note']) ? "\n\nالأوقات أو الملاحظات المفضلة: {$validated['note']}" : '');

                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $parent->id,
                    'message' => $message,
                ]);
                $conversation->update(['last_message_at' => now()]);

                $assignment->teacher?->notify(new GenericDatabaseNotification([
                    'title' => 'طلب برايفت من ولي أمر',
                    'message' => "أرسل ولي الأمر طلب برايفت للطالب {$student->name} في مادة {$assignment->subject?->name}.",
                    'link' => route('chat.index', ['conversation' => $conversation->id]),
                ]));

                return [$privateRequest, $conversation];
            });
        } catch (LogicException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('chat.index', ['conversation' => $conversation->id])
            ->with('success', $privateRequest->wasRecentlyCreated
                ? 'تم إرسال طلب البرايفت للمدرس.'
                : 'يوجد طلب برايفت قائم بالفعل؛ يمكنك متابعة الاتفاق من الرسائل.');
    }
}

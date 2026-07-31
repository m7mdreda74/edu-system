<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

class PrivateLessonRequestController extends Controller
{
    public function store(Request $request, int $assignmentId): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            [$privateRequest, $conversation] = DB::transaction(function () use ($assignmentId, $validated): array {
                $student = Auth::user();
                $assignment = TeachingAssignment::with(['teacher:id,name,is_active', 'subject:id,name'])
                    ->lockForUpdate()
                    ->findOrFail($assignmentId);

                if (! $assignment->is_active || ! $assignment->teacher?->is_active || ! $assignment->offersPrivate()) {
                    throw new LogicException('الحصص البرايفت غير متاحة مع هذا المدرس حاليًا.');
                }

                $hasPrivateSubscription = Subscription::active()
                    ->where('student_id', $student->id)
                    ->where('teaching_assignment_id', $assignment->id)
                    ->where('type', Subscription::TYPE_PRIVATE)
                    ->exists();

                if ($hasPrivateSubscription) {
                    throw new LogicException('لديك اشتراك برايفت نشط بالفعل مع هذا المدرس.');
                }

                $existing = PrivateLessonRequest::where('student_id', $student->id)
                    ->where('teaching_assignment_id', $assignment->id)
                    ->where('status', PrivateLessonRequest::STATUS_PENDING)
                    ->first();

                if ($existing) {
                    $conversation = Conversation::firstOrCreate(
                        [
                            'teaching_assignment_id' => $assignment->id,
                            'student_id' => $student->id,
                            'teacher_id' => $assignment->teacher_id,
                        ],
                        ['last_message_at' => now()],
                    );

                    if ($existing->conversation_id !== $conversation->id) {
                        $existing->update(['conversation_id' => $conversation->id]);
                    }

                    return [$existing, $conversation];
                }

                $conversation = Conversation::firstOrCreate(
                    [
                        'teaching_assignment_id' => $assignment->id,
                        'student_id' => $student->id,
                        'teacher_id' => $assignment->teacher_id,
                    ],
                    ['last_message_at' => now()],
                );

                $privateRequest = PrivateLessonRequest::create([
                    'student_id' => $student->id,
                    'teaching_assignment_id' => $assignment->id,
                    'conversation_id' => $conversation->id,
                    'student_note' => $validated['note'] ?? null,
                    'status' => PrivateLessonRequest::STATUS_PENDING,
                ]);

                $message = trim((string) ($validated['note'] ?? ''));
                $message = 'أرغب في حجز حصص برايفت، وأود الاتفاق على الموعد المناسب.'
                    .($message !== '' ? "\n\nالأوقات أو الملاحظات المفضلة: {$message}" : '');

                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $student->id,
                    'message' => $message,
                ]);

                $conversation->update(['last_message_at' => now()]);

                $assignment->teacher?->notify(new GenericDatabaseNotification([
                    'title' => 'طلب حجز برايفت جديد',
                    'message' => "{$student->name} أرسل طلب حجز برايفت في مادة {$assignment->subject?->name}. اتفق معه على الموعد عبر الرسائل.",
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
                ? 'تم إرسال طلب البرايفت. اتفق الآن مع المدرس على الموعد المناسب.'
                : 'لديك طلب برايفت قائم بالفعل؛ يمكنك متابعة الاتفاق مع المدرس.');
    }
}

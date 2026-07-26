<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communication;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Communication\Notifications\NewChatMessageNotification;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Threads are scoped to a teaching assignment — one conversation per
 * student ↔ teacher ↔ subject.
 */
class ChatController extends Controller
{
    private const THREAD_RELATIONS = [
        'assignment:id,subject_id,teacher_id',
        'assignment.subject:id,name',
    ];

    public function index(Request $request): Response
    {
        $user = Auth::user();

        $conversations = Conversation::with([
            ...self::THREAD_RELATIONS,
            $user->hasRole('teacher') ? 'student:id,name,avatar' : 'teacher:id,name,avatar',
        ])
            ->where($user->hasRole('teacher') ? 'teacher_id' : 'student_id', $user->id)
            ->orderByDesc('last_message_at')
            ->get();

        $activeConversation = null;
        $messages           = [];

        if ($request->has('conversation')) {
            $activeConversation = Conversation::with([
                ...self::THREAD_RELATIONS,
                'student:id,name,avatar',
                'teacher:id,name,avatar',
            ])
                ->where('id', $request->query('conversation'))
                ->where(fn ($q) => $q->where('student_id', $user->id)->orWhere('teacher_id', $user->id))
                ->firstOrFail();

            $messages = ChatMessage::with('sender:id,name,avatar')
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('created_at')
                ->get();

            ChatMessage::where('conversation_id', $activeConversation->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return Inertia::render('Chat/Index', [
            'conversations'      => $conversations,
            'activeConversation' => $activeConversation,
            'messages'           => $messages,
            'contacts'           => $this->contactsFor($user),
        ]);
    }

    public function startConversation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teaching_assignment_id' => ['required', 'exists:teaching_assignments,id'],
            'student_id'             => ['nullable', 'exists:users,id'],
        ]);

        $user       = Auth::user();
        $assignment = TeachingAssignment::findOrFail($validated['teaching_assignment_id']);

        if ($user->hasRole('teacher')) {
            abort_if($assignment->teacher_id !== $user->id, 403, 'هذه المجموعة ليست ضمن جدولك.');

            $studentId = $validated['student_id'] ?? null;
            abort_if(! $studentId, 400, 'يجب تحديد الطالب لبدء المحادثة.');

            $teacherId = $user->id;
        } else {
            $studentId = $user->id;
            $teacherId = $assignment->teacher_id;

            // A student may only message a teacher they actually study with.
            abort_unless(
                $user->hasActiveSubscriptionToAssignment($assignment->id),
                403,
                'يمكنك مراسلة المعلم بعد الاشتراك معه.',
            );
        }

        $conversation = Conversation::firstOrCreate(
            [
                'teaching_assignment_id' => $assignment->id,
                'student_id'             => $studentId,
                'teacher_id'             => $teacherId,
            ],
            ['last_message_at' => now()],
        );

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'message'         => ['nullable', 'required_without:attachment', 'string', 'max:2000'],
            'attachment'      => ['nullable', 'required_without:message', 'file', 'max:10240'],
        ]);

        $user         = Auth::user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        abort_if($conversation->student_id !== $user->id && $conversation->teacher_id !== $user->id, 403);

        $attachmentPath = $request->hasFile('attachment')
            ? '/storage/' . $request->file('attachment')->store('chat_attachments', 'public')
            : null;

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $validated['message'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $recipientId = $user->id === $conversation->student_id
            ? $conversation->teacher_id
            : $conversation->student_id;

        User::find($recipientId)?->notify(new NewChatMessageNotification($message, $user->name));

        return back();
    }

    public function fetchMessages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'last_message_id' => ['nullable', 'integer'],
        ]);

        $user         = Auth::user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ($conversation->student_id !== $user->id && $conversation->teacher_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ChatMessage::with('sender:id,name,avatar')
            ->where('conversation_id', $conversation->id);

        if ($validated['last_message_id'] ?? null) {
            $query->where('id', '>', $validated['last_message_id']);
        }

        $messages = $query->orderBy('created_at')->get();

        if ($messages->isNotEmpty()) {
            ChatMessage::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->whereIn('id', $messages->pluck('id'))
                ->update(['is_read' => true]);
        }

        return response()->json(['messages' => $messages]);
    }

    // ─── Internals ────────────────────────────────────────────────

    /**
     * Who this user can start a new thread with: for a teacher, their current
     * subscribers; for a student, the teachers they are subscribed to.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contactsFor(User $user): array
    {
        $subscriptions = Subscription::active()
            ->with(['student:id,name,avatar', 'assignment.subject:id,name', 'assignment.teacher:id,name,avatar'])
            ->when(
                $user->hasRole('teacher'),
                fn ($q) => $q->whereIn('teaching_assignment_id', TeachingAssignment::where('teacher_id', $user->id)->select('id')),
                fn ($q) => $q->where('student_id', $user->id),
            )
            ->get();

        return $subscriptions
            ->map(function (Subscription $subscription) use ($user) {
                $counterpart = $user->hasRole('teacher')
                    ? $subscription->student
                    : $subscription->assignment?->teacher;

                if (! $counterpart) {
                    return null;
                }

                return [
                    'id'                     => $counterpart->id,
                    'name'                   => $counterpart->name,
                    'avatar'                 => $counterpart->avatar,
                    'teaching_assignment_id' => $subscription->teaching_assignment_id,
                    'subject'                => $subscription->assignment?->subject?->name,
                ];
            })
            ->filter()
            ->unique(fn (array $c) => $c['id'] . '-' . $c['teaching_assignment_id'])
            ->values()
            ->all();
    }
}

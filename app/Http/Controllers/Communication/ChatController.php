<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communication;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Communication\Notifications\NewChatMessageNotification;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
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
        'contextStudent:id,name',
        'participants:id,name,avatar',
    ];

    public function index(Request $request): Response
    {
        $user = Auth::user();

        $conversations = Conversation::with([
            ...self::THREAD_RELATIONS,
            'student:id,name,avatar',
            'teacher:id,name,avatar',
        ])
            ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
            ->orderByDesc('last_message_at')
            ->get()
            ->each(fn (Conversation $conversation) => $this->decorateConversation($conversation, $user));

        $activeConversation = null;
        $messages           = [];

        if ($request->has('conversation')) {
            $activeConversation = Conversation::with([
                ...self::THREAD_RELATIONS,
                'student:id,name,avatar',
                'teacher:id,name,avatar',
            ])
                ->where('id', $request->query('conversation'))
                ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
                ->firstOrFail();
            $this->decorateConversation($activeConversation, $user);

            $messages = ChatMessage::with('sender:id,name,avatar')
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('created_at')
                ->get();
            $this->decorateMessages($messages);

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
            'kind'                   => ['nullable', 'in:academic,support'],
            'teaching_assignment_id' => ['nullable', 'required_unless:kind,support', 'exists:teaching_assignments,id'],
            'student_id'             => ['nullable', 'exists:users,id'],
            'recipient_id'           => ['nullable', 'exists:users,id'],
        ]);

        /** @var \App\Domain\User\Models\User $user */
        $user       = Auth::user();
        $kind       = $validated['kind'] ?? 'academic';

        if ($kind === 'support') {
            abort_unless($user->hasRole('parent'), 403);

            $studentId = isset($validated['student_id']) ? (int) $validated['student_id'] : null;
            if ($studentId) {
                $this->assertLinkedParent($user, $studentId);
            }

            $admin = isset($validated['recipient_id'])
                ? User::role('admin')->where('is_active', true)->findOrFail($validated['recipient_id'])
                : User::role('admin')->where('is_active', true)->orderBy('id')->firstOrFail();

            $conversation = Conversation::where('kind', 'support')
                ->where('context_student_id', $studentId)
                ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
                ->whereHas('participants', fn ($query) => $query->where('users.id', $admin->id))
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'kind' => 'support',
                    'context_student_id' => $studentId,
                    'subject' => $studentId ? 'متابعة إدارية ومالية للطالب' : 'تواصل مع إدارة المنصة',
                    'last_message_at' => now(),
                ]);
            }

            $this->attachParticipant($conversation, $user, 'parent');
            $this->attachParticipant($conversation, $admin, 'admin');

            return redirect()->route('chat.index', ['conversation' => $conversation->id]);
        }

        $assignment = TeachingAssignment::findOrFail($validated['teaching_assignment_id']);

        if ($user->hasRole('teacher')) {
            abort_if($assignment->teacher_id !== $user->id, 403, 'هذه المجموعة ليست ضمن جدولك.');

            $studentId = $validated['student_id'] ?? null;
            abort_if(! $studentId, 400, 'يجب تحديد الطالب لبدء المحادثة.');
            abort_unless(
                Subscription::active()
                    ->where('student_id', $studentId)
                    ->where('teaching_assignment_id', $assignment->id)
                    ->exists(),
                403,
                'يمكنك مراسلة الطلاب المشتركين في هذا التكليف فقط.',
            );

            $teacherId = $user->id;
        } elseif ($user->hasRole('parent')) {
            $studentId = (int) ($validated['student_id'] ?? 0);
            abort_if(! $studentId, 422, 'اختر الطالب المرتبط بالمحادثة.');
            $this->assertLinkedParent($user, $studentId);

            $canContactTeacher = Subscription::active()
                ->where('student_id', $studentId)
                ->where('teaching_assignment_id', $assignment->id)
                ->exists()
                || PrivateLessonRequest::where('student_id', $studentId)
                    ->where('teaching_assignment_id', $assignment->id)
                    ->where('status', PrivateLessonRequest::STATUS_PENDING)
                    ->exists();

            abort_unless($canContactTeacher, 403, 'يمكنك مراسلة المدرس بعد الاشتراك أو تقديم طلب برايفت للطالب.');
            $teacherId = $assignment->teacher_id;
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

        if ($user->hasRole('parent')) {
            $this->attachParticipant($conversation, $user, 'parent');
        }

        return redirect()->route('chat.index', ['conversation' => $conversation->id]);
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'message'         => ['nullable', 'required_without:attachment', 'string', 'max:2000'],
            'attachment'      => [
                'nullable',
                'required_without:message',
                'file',
                'mimes:pdf,doc,docx,ppt,pptx,zip,png,jpg,jpeg',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,application/zip,image/png,image/jpeg',
                'max:10240',
            ],
        ]);

        $user         = Auth::user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        abort_unless($this->isParticipant($conversation, $user), 403);

        $attachmentPath = $request->hasFile('attachment')
            ? 'private://' . $request->file('attachment')->store('chat_attachments', 'local')
            : null;

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $validated['message'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $conversation->participants()
            ->where('users.id', '!=', $user->id)
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new NewChatMessageNotification($message, $user->name),
            ));

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

        if (! $this->isParticipant($conversation, $user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ChatMessage::with('sender:id,name,avatar')
            ->where('conversation_id', $conversation->id);

        if ($validated['last_message_id'] ?? null) {
            $query->where('id', '>', $validated['last_message_id']);
        }

        $messages = $query->orderBy('created_at')->limit(200)->get();
        $this->decorateMessages($messages);

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
        if ($user->hasRole('parent')) {
            $studentIds = ParentStudentLink::where('parent_user_id', $user->id)
                ->whereNotNull('verified_at')
                ->pluck('student_user_id');

            return Subscription::active()
                ->whereIn('student_id', $studentIds)
                ->with(['student:id,name', 'assignment.subject:id,name', 'assignment.teacher:id,name,avatar'])
                ->get()
                ->map(fn (Subscription $subscription) => [
                    'id' => $subscription->assignment?->teacher?->id,
                    'name' => $subscription->assignment?->teacher?->name,
                    'avatar' => $subscription->assignment?->teacher?->avatar,
                    'teaching_assignment_id' => $subscription->teaching_assignment_id,
                    'student_id' => $subscription->student_id,
                    'student_name' => $subscription->student?->name,
                    'subject' => $subscription->assignment?->subject?->name,
                    'kind' => 'academic',
                ])
                ->filter(fn (array $contact) => ! empty($contact['id']))
                ->unique(fn (array $contact) => $contact['student_id'].'-'.$contact['teaching_assignment_id'])
                ->values()
                ->all();
        }

        if ($user->hasRole('admin')) {
            return [];
        }

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

    private function isParticipant(Conversation $conversation, User $user): bool
    {
        return $conversation->participants()->where('users.id', $user->id)->exists();
    }

    private function decorateMessages(\Illuminate\Database\Eloquent\Collection $messages): void
    {
        $messages->each(function (ChatMessage $message): void {
            $storedPath = $message->attachment_path;
            $extension = strtolower((string) pathinfo((string) $storedPath, PATHINFO_EXTENSION));

            $message->setAttribute('attachment_name', $storedPath ? basename((string) $storedPath) : null);
            $message->setAttribute('attachment_is_image', in_array($extension, ['jpg', 'jpeg', 'png'], true));
            $message->setAttribute(
                'attachment_path',
                filled($storedPath)
                    ? route('chat.attachment.download', $message->id)
                    : null,
            );
        });
    }

    private function attachParticipant(Conversation $conversation, User $user, string $role): void
    {
        $conversation->participants()->syncWithoutDetaching([
            $user->id => ['participant_role' => $role],
        ]);
    }

    private function assertLinkedParent(User $parent, int $studentId): void
    {
        abort_unless(
            ParentStudentLink::where('parent_user_id', $parent->id)
                ->where('student_user_id', $studentId)
                ->whereNotNull('verified_at')
                ->exists(),
            403,
            'هذا الطالب غير مرتبط بحسابك.',
        );
    }

    private function decorateConversation(Conversation $conversation, User $viewer): void
    {
        $counterpart = $conversation->participants->firstWhere('id', '!=', $viewer->id);

        if ($viewer->hasRole('teacher') && $conversation->student) {
            $counterpart = $conversation->student;
        } elseif ($viewer->hasRole('parent') && $conversation->teacher) {
            $counterpart = $conversation->teacher;
        }

        $conversation->setAttribute('counterpart', $counterpart?->only(['id', 'name', 'avatar']));
    }
}

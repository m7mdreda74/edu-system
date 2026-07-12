<?php

declare(strict_types=1);

namespace App\Http\Controllers\Communication;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Communication\Notifications\NewChatMessageNotification;
use App\Domain\User\Models\User;
use App\Domain\Course\Models\Course;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        // Get conversations based on user role
        if ($user->hasRole('teacher')) {
            $conversations = Conversation::with(['student:id,name,avatar', 'course:id,title'])
                ->where('teacher_id', $user->id)
                ->orderBy('last_message_at', 'desc')
                ->get();
        } else {
            $conversations = Conversation::with(['teacher:id,name,avatar', 'course:id,title'])
                ->where('student_id', $user->id)
                ->orderBy('last_message_at', 'desc')
                ->get();
        }

        $activeConversation = null;
        $messages = [];

        if ($request->has('conversation')) {
            $activeConversation = Conversation::with(['student:id,name,avatar', 'teacher:id,name,avatar', 'course:id,title'])
                ->where('id', $request->query('conversation'))
                ->where(function ($q) use ($user) {
                    $q->where('student_id', $user->id)->orWhere('teacher_id', $user->id);
                })
                ->firstOrFail();

            $messages = ChatMessage::with('sender:id,name,avatar')
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Mark messages as read
            ChatMessage::where('conversation_id', $activeConversation->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        // Fetch enrolled students for teacher to start new conversations
        $enrolledStudents = [];
        if ($user->hasRole('teacher')) {
            $enrolledStudents = \App\Domain\Enrollment\Models\Enrollment::with(['user:id,name,avatar', 'course:id,title'])
                ->whereHas('course', function ($q) use ($user) {
                    $q->where('teacher_id', $user->id);
                })
                ->get()
                ->map(function ($enrollment) {
                    return [
                        'id'           => $enrollment->user->id,
                        'name'         => $enrollment->user->name,
                        'avatar'       => $enrollment->user->avatar,
                        'course_id'    => $enrollment->course->id,
                        'course_title' => $enrollment->course->title,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Chat/Index', [
            'conversations'      => $conversations,
            'activeConversation' => $activeConversation,
            'messages'           => $messages,
            'enrolledStudents'   => $enrolledStudents,
        ]);
    }

    public function startConversation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'course_id'  => ['required', 'exists:courses,id'],
            'teacher_id' => ['required', 'exists:users,id'],
            'student_id' => ['nullable', 'exists:users,id'],
        ]);

        $user = auth()->user();

        if ($user->hasRole('teacher')) {
            $teacherId = $user->id;
            $studentId = $validated['student_id'] ?? null;
            abort_if(!$studentId, 400, 'يجب تحديد الطالب لبدء المحادثة.');
        } else {
            $studentId = $user->id;
            $teacherId = $validated['teacher_id'];
        }

        // Check if conversation exists
        $conversation = Conversation::firstOrCreate(
            [
                'course_id'  => $validated['course_id'],
                'student_id' => $studentId,
                'teacher_id' => $teacherId,
            ],
            [
                'last_message_at' => now(),
            ]
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

        $user = auth()->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        abort_if($conversation->student_id !== $user->id && $conversation->teacher_id !== $user->id, 403);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('chat_attachments', 'public');
            $attachmentPath = '/storage/' . $path;
        }

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $validated['message'] ?? null,
            'attachment_path' => $attachmentPath,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Send notification to the other participant
        $recipientId = ($user->id === $conversation->student_id) ? $conversation->teacher_id : $conversation->student_id;
        $recipient = User::find($recipientId);
        if ($recipient) {
            $recipient->notify(new NewChatMessageNotification($message, $user->name));
        }

        return back();
    }

    public function fetchMessages(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'exists:conversations,id'],
            'last_message_id' => ['nullable', 'integer'],
        ]);

        $user = auth()->user();
        $conversation = Conversation::findOrFail($validated['conversation_id']);

        if ($conversation->student_id !== $user->id && $conversation->teacher_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ChatMessage::with('sender:id,name,avatar')
            ->where('conversation_id', $conversation->id);

        if ($validated['last_message_id']) {
            $query->where('id', '>', $validated['last_message_id']);
        }

        $messages = $query->orderBy('created_at', 'asc')->get();

        if ($messages->isNotEmpty()) {
            // Mark new messages as read
            ChatMessage::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->where('is_read', false)
                ->whereIn('id', $messages->pluck('id'))
                ->update(['is_read' => true]);
        }

        return response()->json(['messages' => $messages]);
    }
}

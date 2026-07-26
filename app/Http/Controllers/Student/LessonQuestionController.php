<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Learning\Models\LessonQuestion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonQuestionController extends Controller
{
    /**
     * Fetch all questions and their replies for a lesson.
     */
    public function index(int $lessonId): JsonResponse
    {
        $questions = LessonQuestion::with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->where('lesson_id', $lessonId)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($questions);
    }

    /**
     * Post a new question or reply to an existing one.
     */
    public function store(Request $request, int $lessonId): JsonResponse
    {
        $validated = $request->validate([
            'content'         => ['required', 'string', 'max:2000'],
            'video_timestamp' => ['nullable', 'integer', 'min:0'],
            'parent_id'       => ['nullable', 'integer', 'exists:lesson_questions,id'],
        ]);

        $question = LessonQuestion::create([
            'user_id'         => auth()->id(),
            'lesson_id'       => $lessonId,
            'parent_id'       => $validated['parent_id'] ?? null,
            'content'         => $validated['content'],
            'video_timestamp' => $validated['parent_id'] ? null : ($validated['video_timestamp'] ?? null),
        ]);

        // Load relations for return payload
        $question->load(['user:id,name,avatar']);

        return response()->json($question, 201);
    }
}

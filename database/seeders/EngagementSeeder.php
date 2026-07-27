<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LessonQuestion;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use Illuminate\Database\Seeder;

/**
 * Everything students actually do once they are subscribed: watch, ask, submit,
 * sit quizzes, message their teacher, and rate them.
 *
 * All of it hangs off live subscriptions, so a student never has progress in a
 * group they were never in.
 */
class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $subscriptions = Subscription::active()
            ->with(['student', 'group', 'assignment.teacher'])
            ->get();

        foreach ($subscriptions as $index => $subscription) {
            $this->seedProgress($subscription, $index);
            $this->seedQuizAttempts($subscription, $index);
            $this->seedSubmissions($subscription, $index);
            $this->seedQuestions($subscription, $index);
            $this->seedConversation($subscription, $index);
        }

        $this->seedReviews();
        $this->seedPurchaseRequests();
    }

    /** Part-way through the material, not everyone at the same point. */
    private function seedProgress(Subscription $subscription, int $index): void
    {
        $materials = $subscription->group?->materials()->get() ?? collect();

        if ($materials->isEmpty()) {
            return;
        }

        $completed = $index % 4 === 0
            ? $materials->count()          // one student in four has finished
            : random_int(1, max(1, $materials->count() - 1));

        foreach ($materials->take($completed) as $material) {
            LessonProgress::firstOrCreate(
                ['student_id' => $subscription->student_id, 'lesson_id' => $material->id],
                ['watched_seconds' => $material->duration_seconds, 'is_completed' => true],
            );
        }

        // And one they are midway through.
        $inProgress = $materials->get($completed);

        if ($inProgress) {
            LessonProgress::firstOrCreate(
                ['student_id' => $subscription->student_id, 'lesson_id' => $inProgress->id],
                ['watched_seconds' => (int) ($inProgress->duration_seconds * 0.4), 'is_completed' => false],
            );
        }
    }

    private function seedQuizAttempts(Subscription $subscription, int $index): void
    {
        if ($index % 2 !== 0) {
            return;
        }

        // Quizzes hang off the syllabus, which belongs to the assignment.
        $quizzes = Quiz::whereHas('unit', fn ($q) => $q->where('teaching_assignment_id', $subscription->teaching_assignment_id))
            ->where('is_active', true)
            ->take(2)
            ->get();

        foreach ($quizzes as $position => $quiz) {
            $alreadySat = QuizAttempt::where('user_id', $subscription->student_id)
                ->where('quiz_id', $quiz->id)
                ->exists();

            if ($alreadySat) {
                continue;
            }

            $score = random_int(45, 100);

            QuizAttempt::create([
                'user_id'      => $subscription->student_id,
                'quiz_id'      => $quiz->id,
                'score'        => $score,
                'passed'       => $score >= $quiz->passing_score,
                'started_at'   => now()->subDays(3 + $position)->subMinutes(20),
                'submitted_at' => now()->subDays(3 + $position),
                // The odd student switched tabs mid-exam.
                'violations'   => $index % 6 === 0 ? random_int(1, 2) : 0,
            ]);
        }
    }

    private function seedSubmissions(Subscription $subscription, int $index): void
    {
        if ($index % 3 !== 0) {
            return;
        }

        $worksheets = Worksheet::whereHas('unit', fn ($q) => $q->where('teaching_assignment_id', $subscription->teaching_assignment_id))
            ->where('requires_submission', true)
            ->get();

        foreach ($worksheets as $position => $worksheet) {
            $alreadySubmitted = WorksheetSubmission::where('worksheet_id', $worksheet->id)
                ->where('student_id', $subscription->student_id)
                ->exists();

            if ($alreadySubmitted) {
                continue;
            }

            // The first is marked, the second still sitting in the queue.
            $isGraded = $position === 0;

            WorksheetSubmission::create([
                'worksheet_id'        => $worksheet->id,
                'student_id'          => $subscription->student_id,
                'submitted_file_path' => '/storage/submissions/sample-answer.pdf',
                'submitted_at'        => now()->subDays(4 - $position),
                'score'               => $isGraded ? random_int(12, (int) ($worksheet->max_score ?? 20)) : null,
                'teacher_feedback'    => $isGraded ? 'إجابة جيدة، انتبه لخطوات الحل في السؤال الثالث.' : null,
                'graded_at'           => $isGraded ? now()->subDays(2) : null,
            ]);
        }
    }

    private function seedQuestions(Subscription $subscription, int $index): void
    {
        if ($index % 4 !== 0) {
            return;
        }

        $material = $subscription->group?->materials()->first();
        $teacher  = $subscription->assignment?->teacher;

        if (! $material || ! $teacher) {
            return;
        }

        $question = LessonQuestion::create([
            'user_id'         => $subscription->student_id,
            'lesson_id'       => $material->id,
            'content'         => 'لو سمحت، ممكن توضيح الخطوة الثانية في المثال؟ لم أفهم من أين جاء الرقم.',
            'video_timestamp' => random_int(120, 900),
        ]);

        LessonQuestion::create([
            'user_id'   => $teacher->id,
            'lesson_id' => $material->id,
            'parent_id' => $question->id,
            'content'   => 'سؤال ممتاز — الرقم ناتج عن تعويض القيمة في القانون السابق. راجع الدقيقة 4:30 وستجدها موضحة.',
        ]);
    }

    private function seedConversation(Subscription $subscription, int $index): void
    {
        if ($index % 3 !== 0) {
            return;
        }

        $teacher = $subscription->assignment?->teacher;

        if (! $teacher) {
            return;
        }

        $conversation = Conversation::firstOrCreate(
            [
                'teaching_assignment_id' => $subscription->teaching_assignment_id,
                'student_id'             => $subscription->student_id,
                'teacher_id'             => $teacher->id,
            ],
            ['last_message_at' => now()->subHours(3)],
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        $thread = [
            [$subscription->student_id, 'السلام عليكم أستاذ، هل ستكون حصة الأربعاء في موعدها؟', true],
            [$teacher->id, 'وعليكم السلام، نعم في موعدها بإذن الله. جهّز أسئلة الواجب معك.', true],
            [$subscription->student_id, 'تمام، شكراً جزيلاً.', false],
        ];

        foreach ($thread as $position => [$senderId, $message, $isRead]) {
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $senderId,
                'message'         => $message,
                'is_read'         => $isRead,
                'created_at'      => now()->subHours(5 - $position),
                'updated_at'      => now()->subHours(5 - $position),
            ]);
        }

        $conversation->update(['last_message_at' => now()->subHours(3)]);
    }

    /** Ratings on teachers, most approved and a couple still awaiting review. */
    private function seedReviews(): void
    {
        $comments = [
            'شرح ممتاز وواضح، استفدت كثيراً وتحسّنت درجاتي.',
            'أسلوب مبسّط جداً ويوصل المعلومة بسرعة. أنصح به.',
            'أفضل معلم درست معه، متابعة مستمرة وردود سريعة على الأسئلة.',
            'الحصص منظمة والملازم مفيدة جداً قبل الامتحان.',
            'صبور جداً مع الطلاب ويعيد الشرح حتى نفهم.',
        ];

        $studied = Subscription::with('assignment')
            ->get()
            ->groupBy(fn (Subscription $s) => $s->student_id . '-' . $s->assignment?->teacher_id);

        foreach ($studied as $key => $subscriptions) {
            [$studentId, $teacherId] = explode('-', (string) $key);

            if (! $teacherId || $teacherId === '') {
                continue;
            }

            // Not everybody leaves a review.
            if ((int) $studentId % 3 === 1) {
                continue;
            }

            TeacherReview::firstOrCreate(
                ['user_id' => (int) $studentId, 'teacher_id' => (int) $teacherId],
                [
                    'rating'      => random_int(4, 5),
                    'comment'     => $comments[(int) $studentId % count($comments)],
                    // A few sit unapproved so the moderation view is not empty.
                    'is_approved' => (int) $studentId % 7 !== 0,
                ],
            );
        }
    }

    /** Students asking a linked parent to pay, in each of the three states. */
    private function seedPurchaseRequests(): void
    {
        $links = ParentStudentLink::with('student')->get();

        $states = [
            PurchaseRequest::STATUS_PENDING,
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_REJECTED,
        ];

        foreach ($links as $index => $link) {
            $group = \App\Domain\Scheduling\Models\TeachingGroup::where('is_active', true)
                ->whereNotIn('id', Subscription::where('student_id', $link->student_user_id)
                    ->whereNotNull('teaching_group_id')
                    ->select('teaching_group_id'))
                ->skip($index)
                ->first();

            if (! $group) {
                continue;
            }

            $status = $states[$index % count($states)];

            PurchaseRequest::firstOrCreate(
                [
                    'student_user_id'   => $link->student_user_id,
                    'teaching_group_id' => $group->id,
                ],
                [
                    'parent_user_id' => $link->parent_user_id,
                    'status'         => $status,
                    'notes'          => $status === PurchaseRequest::STATUS_REJECTED
                        ? 'نؤجلها للشهر القادم.'
                        : null,
                ],
            );
        }
    }
}

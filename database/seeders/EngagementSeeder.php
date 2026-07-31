<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Learning\Models\LessonProgress;
use App\Domain\Learning\Models\LessonQuestion;
use App\Domain\Learning\Models\LiveSessionAttendee;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizAttempt;
use App\Domain\Scheduling\Models\PrivateLessonRequest;
use App\Domain\Subscription\Models\PurchaseRequest;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Everything students actually do once subscribed:
 * watch, ask, submit, sit quizzes, message their teacher, rate them,
 * request private lessons, and attend live sessions.
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

        $this->seedLiveSessionAttendees();
        $this->seedReviews();
        $this->seedPurchaseRequests();
        $this->seedPrivateLessonRequests();
    }

    /** Part-way through the material, not everyone at the same point. */
    private function seedProgress(Subscription $subscription, int $index): void
    {
        $materials = $subscription->group?->materials()->get() ?? collect();

        if ($materials->isEmpty()) {
            return;
        }

        $completed = $index % 4 === 0
            ? $materials->count()
            : random_int(1, max(1, $materials->count() - 1));

        foreach ($materials->take($completed) as $material) {
            LessonProgress::firstOrCreate(
                ['student_id' => $subscription->student_id, 'lesson_id' => $material->id],
                ['watched_seconds' => $material->duration_seconds, 'is_completed' => true],
            );
        }

        // One in-progress material
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

        $questions = [
            'لو سمحت، ممكن توضيح الخطوة الثانية في المثال؟ لم أفهم من أين جاء الرقم.',
            'هل يمكنني حل المسألة بطريقة مختلفة عما شُرح؟',
            'ما الفرق بين هذه الطريقة وطريقة الكتاب المدرسي؟',
        ];

        $answers = [
            'سؤال ممتاز — الرقم ناتج عن تعويض القيمة في القانون السابق. راجع الدقيقة 4:30 وستجدها موضحة.',
            'بالتأكيد، الطريقتان صحيحتان. الطريقة الثانية أبسط في بعض الحالات.',
            'الفرق أن المنهج يستخدم الطريقة الأساسية، لكن ما شرحناه أسرع في الامتحانات.',
        ];

        $questionIdx = $index % count($questions);

        $question = LessonQuestion::create([
            'user_id'         => $subscription->student_id,
            'lesson_id'       => $material->id,
            'content'         => $questions[$questionIdx],
            'video_timestamp' => random_int(120, 900),
        ]);

        LessonQuestion::create([
            'user_id'   => $teacher->id,
            'lesson_id' => $material->id,
            'parent_id' => $question->id,
            'content'   => $answers[$questionIdx],
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

        // Seed conversation_participants
        foreach ([
            [$subscription->student_id, 'student'],
            [$teacher->id, 'teacher'],
        ] as [$userId, $role]) {
            DB::table('conversation_participants')->insertOrIgnore([
                'conversation_id'  => $conversation->id,
                'user_id'          => $userId,
                'participant_role' => $role,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        if ($conversation->messages()->exists()) {
            return;
        }

        $threads = [
            [
                [$subscription->student_id, 'السلام عليكم أستاذ، هل ستكون حصة الأربعاء في موعدها؟', true],
                [$teacher->id, 'وعليكم السلام، نعم في موعدها بإذن الله. جهّز أسئلة الواجب معك.', true],
                [$subscription->student_id, 'تمام، شكراً جزيلاً.', false],
            ],
            [
                [$subscription->student_id, 'أستاذ، لم أفهم الفصل الأخير. هل يمكنني حضور حصة إضافية؟', true],
                [$teacher->id, 'بالتأكيد، سنضيف وقتاً في نهاية الحصة القادمة لمراجعة الفصل.', true],
                [$subscription->student_id, 'جزاك الله خيراً أستاذ.', false],
            ],
            [
                [$subscription->student_id, 'هل المادة المرفوعة تغطي امتحان الفصل الأول؟', true],
                [$teacher->id, 'نعم، كل مقاطع الفيديو مرتّبة حسب الامتحان. ابدأ بالوحدة الثانية.', true],
            ],
        ];

        $thread = $threads[$index % count($threads)];

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

    /**
     * Add attendees to ended live sessions so attendance records exist.
     */
    private function seedLiveSessionAttendees(): void
    {
        $endedSessions = \App\Domain\Learning\Models\LiveSession::where('status', 'ended')
            ->with('teachingGroup.subscriptions.student')
            ->take(30)
            ->get();

        foreach ($endedSessions as $session) {
            $students = $session->teachingGroup?->subscriptions
                ?->where('status', 'active')
                ->pluck('student')
                ->filter()
                ->take(10) ?? collect();

            foreach ($students as $student) {
                LiveSessionAttendee::firstOrCreate(
                    ['live_session_id' => $session->id, 'user_id' => $student->id],
                    [
                        'joined_at' => $session->started_at?->addMinutes(random_int(1, 10)),
                        'left_at'   => $session->ended_at?->subMinutes(random_int(0, 5)),
                    ],
                );
            }
        }
    }

    /** Ratings on teachers, most approved, a few still awaiting review. */
    private function seedReviews(): void
    {
        $comments = [
            'شرح ممتاز وواضح، استفدت كثيراً وتحسّنت درجاتي.',
            'أسلوب مبسّط جداً ويوصل المعلومة بسرعة. أنصح به.',
            'أفضل معلم درست معه، متابعة مستمرة وردود سريعة على الأسئلة.',
            'الحصص منظمة والملازم مفيدة جداً قبل الامتحان.',
            'صبور جداً مع الطلاب ويعيد الشرح حتى نفهم.',
            'المادة العلمية قوية جداً والشرح مرتّب خطوة بخطوة.',
            'استفدت من الاختبارات القصيرة في نهاية كل حصة.',
            'الأستاذ يشرح بطريقة عملية مرتبطة بالحياة اليومية.',
        ];

        $studied = Subscription::with('assignment')
            ->get()
            ->groupBy(fn (Subscription $s) => $s->student_id . '-' . $s->assignment?->teacher_id);

        foreach ($studied as $key => $subscriptions) {
            [$studentId, $teacherId] = explode('-', (string) $key);

            if (! $teacherId || $teacherId === '') {
                continue;
            }

            // ~70% of students leave a review
            if ((int) $studentId % 10 >= 7) {
                continue;
            }

            TeacherReview::firstOrCreate(
                ['user_id' => (int) $studentId, 'teacher_id' => (int) $teacherId],
                [
                    'rating'      => random_int(3, 5),
                    'comment'     => $comments[(int) $studentId % count($comments)],
                    'is_approved' => (int) $studentId % 5 !== 0, // ~80% approved
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
                ->skip($index % 5)
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

    /**
     * Seed private lesson requests — students requesting private sessions
     * from teachers they are subscribed to or interested in.
     * Covers: pending, accepted, rejected states.
     */
    private function seedPrivateLessonRequests(): void
    {
        $statuses = ['pending', 'accepted', 'rejected'];

        $notes = [
            'أريد تقوية في الفصل الثالث، وخاصة مسائل التفاضل.',
            'أحتاج مراجعة مكثفة قبل الامتحان النهائي بأسبوعين.',
            'لدي ضعف في الجزء الحسابي من المادة وأريد درساً خاصاً.',
            'ابني يجد صعوبة في الحل التحليلي، هل يمكن ترتيب درس خاص؟',
            'أرغب في تسريع الفهم قبل بدء الفصل القادم.',
            'الامتحان الوزاري بعد ثلاثة أسابيع وأحتاج مراجعة شاملة.',
        ];

        // Pick students with active subscriptions and request a private lesson
        // from their teacher (a different assignment than the group one).
        $students = User::role('student')->take(80)->get();

        foreach ($students as $index => $student) {
            // Find an assignment the student is NOT subscribed to yet
            $takenAssignmentIds = Subscription::where('student_id', $student->id)
                ->pluck('teaching_assignment_id')
                ->all();

            $assignment = \App\Domain\Scheduling\Models\TeachingAssignment::where('accepts_private', true)
                ->where('is_active', true)
                ->whereNotIn('id', $takenAssignmentIds)
                ->inRandomOrder()
                ->first();

            if (! $assignment) {
                continue;
            }

            $already = PrivateLessonRequest::where('student_id', $student->id)
                ->where('teaching_assignment_id', $assignment->id)
                ->exists();

            if ($already) {
                continue;
            }

            PrivateLessonRequest::create([
                'student_id'             => $student->id,
                'teaching_assignment_id' => $assignment->id,
                'student_note'           => $notes[$index % count($notes)],
                'status'                 => $statuses[$index % count($statuses)],
            ]);
        }
    }
}

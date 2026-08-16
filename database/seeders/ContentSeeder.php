<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizOption;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupLesson;
use Illuminate\Database\Seeder;

/**
 * What a teacher publishes to a group: recorded material, the lesson plan,
 * quizzes, worksheets, and the live sessions themselves.
 *
 * Live sessions are spread across all three states — one already finished with
 * a recording, one running now, and a couple still scheduled — so every branch
 * of the classroom UI has something to render. Media URLs remain empty until
 * an admin or teacher provides real platform content; demo data must not point
 * students at unrelated third-party videos.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $groups = TeachingGroup::with('assignment.subject')->get();

        foreach ($groups as $index => $group) {
            $subject = $group->assignment?->subject?->name ?? 'المادة';

            // Content belongs to the assignment, so groups sharing one teacher
            // and subject share a unit — the guards below stop the second group
            // seeding it all over again.
            $unit = CurriculumUnit::firstFor($group);

            $materials = $this->seedMaterials($group, $unit, $subject);
            $this->seedLessonPlan($group, $subject);
            $this->seedQuizzes($group, $unit, $subject, $materials);
            $this->seedWorksheets($group, $unit, $subject, $materials);
            $this->seedLiveSessions($group, $subject, $index);
        }
    }

    /** @return array<int, GroupMaterial> */
    private function seedMaterials(TeachingGroup $group, CurriculumUnit $unit, string $subject): array
    {
        if ($group->materials()->exists()) {
            return $group->materials()->get()->all();
        }

        $titles = [
            "مقدمة {$subject} — نظرة عامة على المنهج",
            'الوحدة الأولى — الأساسيات والمفاهيم',
            'الوحدة الأولى — تطبيقات وأمثلة محلولة',
            'الوحدة الثانية — الشرح النظري',
            'الوحدة الثانية — حل تمارين الكتاب',
            'مراجعة شاملة قبل الاختبار',
        ];

        $materials = [];

        foreach ($titles as $position => $title) {
            $materials[] = GroupMaterial::create([
                'curriculum_unit_id' => $unit->id,
                'academic_term_id'   => $group->academic_term_id,
                'title'              => $title,
                'video_url'          => null,
                'duration_seconds'   => 60 * random_int(22, 58),
                'order'              => $position + 1,
                // The opener is the free sample a visitor can watch.
                'is_free_preview'    => $position === 0,
                'description'        => 'شرح مفصّل مع أمثلة من الامتحانات الوزارية السابقة، وملخص في نهاية الحصة.',
            ]);
        }

        return $materials;
    }

    /** The teacher's plan for the term, ahead of scheduling each live class. */
    private function seedLessonPlan(TeachingGroup $group, string $subject): void
    {
        if ($group->lessons()->exists()) {
            return;
        }

        $titles = [
            'الحصة الأولى — التمهيد والتعريف',
            'الحصة الثانية — القواعد الأساسية',
            'الحصة الثالثة — التطبيق العملي',
            'الحصة الرابعة — حل مسائل متقدمة',
            'الحصة الخامسة — مراجعة ونماذج امتحانات',
        ];

        foreach ($titles as $position => $title) {
            TeachingGroupLesson::create([
                'teaching_group_id' => $group->id,
                'position'          => $position + 1,
                'title'             => $title,
                'description'       => "خطة حصة {$subject} — {$title}.",
                // Only the first two have been scheduled so far.
                'status'            => $position < 2 ? 'scheduled' : 'pending',
            ]);
        }
    }

    /** @param array<int, GroupMaterial> $materials */
    private function seedQuizzes(TeachingGroup $group, CurriculumUnit $unit, string $subject, array $materials): void
    {
        if ($group->quizzes()->exists() || $materials === []) {
            return;
        }

        $quizzes = [
            ['title' => "اختبار قصير — أساسيات {$subject}", 'material' => $materials[1] ?? $materials[0], 'passing' => 60, 'limit' => 15, 'active' => true],
            ['title' => "اختبار الوحدة الثانية — {$subject}", 'material' => $materials[3] ?? $materials[0], 'passing' => 70, 'limit' => 30, 'active' => true],
            ['title' => 'الاختبار النهائي (لم يُفتح بعد)',    'material' => null,                          'passing' => 75, 'limit' => 45, 'active' => false],
        ];

        foreach ($quizzes as $definition) {
            $quiz = Quiz::create([
                'curriculum_unit_id' => $unit->id,
                'lesson_id'          => $definition['material']?->id,
                'title'              => $definition['title'],
                'passing_score'      => $definition['passing'],
                'time_limit_minutes' => $definition['limit'],
                // Open for the rest of the term so the student side has a live window.
                'available_from'     => now()->subWeek(),
                'available_until'    => now()->addMonths(2),
                'is_active'          => $definition['active'],
            ]);

            $this->seedQuestions($quiz, $subject);
        }
    }

    private function seedQuestions(Quiz $quiz, string $subject): void
    {
        $types = ['single', 'single', 'true_false', 'multiple'];

        foreach ($types as $index => $type) {
            $question = QuizQuestion::create([
                'quiz_id'       => $quiz->id,
                'question_text' => 'السؤال ' . ($index + 1) . ": اختر الإجابة الصحيحة في {$subject}.",
                'type'          => $type,
                'order'         => $index + 1,
            ]);

            if ($type === 'true_false') {
                QuizOption::create(['question_id' => $question->id, 'option_text' => 'صح',  'is_correct' => true]);
                QuizOption::create(['question_id' => $question->id, 'option_text' => 'خطأ', 'is_correct' => false]);
                continue;
            }

            // "multiple" has two right answers so the grader's all-or-nothing
            // rule gets exercised by the demo data too.
            $correctCount = $type === 'multiple' ? 2 : 1;

            foreach (range(1, 4) as $position) {
                QuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => 'الخيار ' . $position,
                    'is_correct'  => $position <= $correctCount,
                ]);
            }
        }
    }

    /** @param array<int, GroupMaterial> $materials */
    private function seedWorksheets(TeachingGroup $group, CurriculumUnit $unit, string $subject, array $materials): void
    {
        if ($group->worksheets()->exists()) {
            return;
        }

        Worksheet::create([
            'curriculum_unit_id'  => $unit->id,
            'lesson_id'           => $materials[0]->id ?? null,
            'title'               => "ملزمة {$subject} — الوحدة الأولى",
            'file_path'           => '/storage/worksheets/sample-notes.pdf',
            'type'                => Worksheet::TYPE_STUDY,
            'requires_submission' => false,
            'max_score'           => null,
        ]);

        Worksheet::create([
            'curriculum_unit_id'  => $unit->id,
            'lesson_id'           => $materials[1]->id ?? null,
            'title'               => 'واجب الوحدة الأولى',
            'file_path'           => '/storage/worksheets/sample-homework.pdf',
            'type'                => Worksheet::TYPE_HOMEWORK,
            'requires_submission' => true,
            'due_date'            => now()->addWeek()->toDateString(),
            'max_score'           => 20,
        ]);

        Worksheet::create([
            'curriculum_unit_id'  => $unit->id,
            'lesson_id'           => null,
            'title'               => 'واجب المراجعة النهائية',
            'file_path'           => '/storage/worksheets/sample-revision.pdf',
            'type'                => Worksheet::TYPE_HOMEWORK,
            'requires_submission' => true,
            'due_date'            => now()->addWeeks(3)->toDateString(),
            'max_score'           => 30,
        ]);

        Worksheet::create([
            'curriculum_unit_id'  => $unit->id,
            'lesson_id'           => null,
            'title'               => "الاختبار الورقي — {$subject}",
            'file_path'           => '/storage/worksheets/sample-paper-exam.pdf',
            'type'                => Worksheet::TYPE_PAPER_EXAM,
            'requires_submission' => true,
            'due_date'            => now()->addMonth()->toDateString(),
            'max_score'           => 50,
        ]);
    }

    private function seedLiveSessions(TeachingGroup $group, string $subject, int $groupIndex): void
    {
        if ($group->liveSessions()->exists()) {
            return;
        }

        $teacherId = $group->assignment?->teacher_id;

        if (! $teacherId) {
            return;
        }

        // A finished one, with the recording published.
        $finished = LiveSession::create([
            'teacher_id'        => $teacherId,
            'teaching_group_id' => $group->id,
            'title'             => "حصة {$subject} — الوحدة الأولى",
            'description'       => 'شرح مباشر مع حل أسئلة الطلاب في نهاية الحصة.',
            'scheduled_at'      => now()->subDays(7),
            'started_at'        => now()->subDays(7),
            'ended_at'          => now()->subDays(7)->addMinutes(90),
            'status'            => LiveSession::STATUS_ENDED,
            'recording_url'     => null,
        ]);
        $this->linkPlanLesson($group, 1, $finished->id);

        $nextScheduledPosition = 2;

        // Every few groups, leave one running so the "join now" state is visible.
        if ($groupIndex % 5 === 0) {
            $live = LiveSession::create([
                'teacher_id'        => $teacherId,
                'teaching_group_id' => $group->id,
                'title'             => "حصة {$subject} — مباشر الآن",
                'description'       => 'الحصة جارية، ادخل القاعة.',
                'scheduled_at'      => now()->subMinutes(20),
                'started_at'        => now()->subMinutes(20),
                'status'            => LiveSession::STATUS_LIVE,
            ]);
            $this->linkPlanLesson($group, 2, $live->id);
            $nextScheduledPosition = 3;
        }

        foreach ([3, 10] as $daysAhead) {
            $scheduled = LiveSession::create([
                'teacher_id'        => $teacherId,
                'teaching_group_id' => $group->id,
                'title'             => "حصة {$subject} — " . ($daysAhead === 3 ? 'الوحدة الثانية' : 'مراجعة عامة'),
                'scheduled_at'      => now()->addDays($daysAhead)->setTime(17, 0),
                'status'            => LiveSession::STATUS_SCHEDULED,
            ]);
            $this->linkPlanLesson($group, $nextScheduledPosition++, $scheduled->id);
        }
    }

    private function linkPlanLesson(TeachingGroup $group, int $position, int $sessionId): void
    {
        $group->lessons()
            ->where('position', $position)
            ->update(['live_session_id' => $sessionId, 'status' => 'scheduled']);
    }
}

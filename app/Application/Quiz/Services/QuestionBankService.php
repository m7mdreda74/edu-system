<?php

declare(strict_types=1);

namespace App\Application\Quiz\Services;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Quiz\Models\QuestionBankQuestion;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Quiz\Models\QuizQuestion;
use App\Domain\User\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionBankService
{
    private const PAGE_SIZE = 18;

    /** @return array<string, mixed> */
    public function index(User $teacher, array $filters = []): array
    {
        $query = QuestionBankQuestion::query()
            ->visibleTo($teacher)
            ->with($this->questionRelations())
            ->latest('id');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('question_text', 'like', "%{$search}%")
                    ->orWhere('topic', 'like', "%{$search}%");
            });
        }

        foreach (['difficulty', 'type', 'visibility'] as $filter) {
            $value = (string) ($filters[$filter] ?? '');
            if (in_array($value, $this->allowedValues($filter), true)) {
                $query->where($filter, $value);
            }
        }

        $questions = $query->paginate(self::PAGE_SIZE)->withQueryString();
        $questions->setCollection($questions->getCollection()->map(
            fn (QuestionBankQuestion $question): array => $this->present($question, $teacher),
        ));

        $units = $this->unitsFor($teacher);

        $stages = $units->filter(fn (array $u): bool => ! empty($u['stage']))->map(fn (array $u): array => [
            'key' => $u['stage'],
            'name' => $u['stage_name'],
        ])->unique('key')->values();

        $grades = $units->filter(fn (array $u): bool => ! empty($u['grade_level_id']))->map(fn (array $u): array => [
            'id' => $u['grade_level_id'],
            'name' => $u['grade_name'],
            'stage' => $u['stage'],
        ])->unique('id')->values();

        $terms = $units->filter(fn (array $u): bool => ! empty($u['academic_term_id']))->map(fn (array $u): array => [
            'id' => $u['academic_term_id'],
            'name' => $u['term_name'],
        ])->unique('id')->values();

        return [
            'questions' => $questions,
            'filters' => [
                'search' => $search,
                'difficulty' => (string) ($filters['difficulty'] ?? ''),
                'type' => (string) ($filters['type'] ?? ''),
                'visibility' => (string) ($filters['visibility'] ?? ''),
            ],
            'subjects' => $this->subjectsFor($teacher),
            'units' => $units,
            'stages' => $stages,
            'grades' => $grades,
            'terms' => $terms,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function forQuiz(User $teacher, Quiz $quiz): Collection
    {
        $quiz->loadMissing('unit.assignment');
        $subjectId = $quiz->unit?->assignment?->subject_id;
        $attachedIds = $quiz->questions()
            ->whereNotNull('question_bank_question_id')
            ->pluck('question_bank_question_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $query = QuestionBankQuestion::query()
            ->visibleTo($teacher)
            ->with($this->questionRelations());

        if ($subjectId !== null) {
            $query->where(function ($subjectQuery) use ($subjectId): void {
                $subjectQuery
                    ->whereNull('subject_id')
                    ->orWhere('subject_id', $subjectId);
            });
        }

        return $query
            ->latest('id')
            ->limit(60)
            ->get()
            ->map(fn (QuestionBankQuestion $question): array => [
                ...$this->present($question, $teacher),
                'attached' => in_array($question->id, $attachedIds, true),
            ])
            ->values();
    }

    public function create(User $teacher, array $data): QuestionBankQuestion
    {
        $data = $this->normalizeData($teacher, $data);

        return DB::transaction(function () use ($teacher, $data): QuestionBankQuestion {
            $question = QuestionBankQuestion::create([
                ...$this->attributesFromData($data),
                'teacher_id' => $teacher->id,
            ]);

            if ($question->type !== QuestionBankQuestion::TYPE_ESSAY) {
                $this->replaceOptions($question, $data['options']);
            }

            return $question->load('options');
        });
    }

    public function update(User $teacher, QuestionBankQuestion $question, array $data): QuestionBankQuestion
    {
        $this->assertOwner($teacher, $question);
        $data = $this->normalizeData($teacher, $data);

        return DB::transaction(function () use ($question, $data): QuestionBankQuestion {
            $question->update($this->attributesFromData($data));

            if ($question->type !== QuestionBankQuestion::TYPE_ESSAY) {
                $this->replaceOptions($question, $data['options']);
            } else {
                $question->options()->delete();
            }

            return $question->fresh('options');
        });
    }

    public function delete(User $teacher, QuestionBankQuestion $question): void
    {
        $this->assertOwner($teacher, $question);
        $question->delete();
    }

    public function attachToQuiz(User $teacher, Quiz $quiz, QuestionBankQuestion $question): QuizQuestion
    {
        $this->assertQuizOwner($teacher, $quiz);

        if (! $question->isVisibleTo($teacher)) {
            abort(403, 'هذا السؤال غير متاح لك.');
        }

        if ($quiz->questions()->where('question_bank_question_id', $question->id)->exists()) {
            throw ValidationException::withMessages([
                'question' => 'تمت إضافة هذا السؤال إلى الاختبار من قبل.',
            ]);
        }

        $question->load('options');

        return DB::transaction(function () use ($quiz, $question): QuizQuestion {
            $copy = $quiz->questions()->create([
                'question_bank_question_id' => $question->id,
                'question_text' => $question->question_text,
                'type' => $question->type,
                'points' => $question->points,
                'order' => (int) $quiz->questions()->max('order') + 1,
                'allow_file_upload' => (bool) $question->allow_file_upload,
                'model_answer' => $question->model_answer,
            ]);

            if ($question->type !== QuestionBankQuestion::TYPE_ESSAY) {
                $copy->options()->createMany($question->options->map(fn ($option): array => [
                    'option_text' => $option->option_text,
                    'is_correct' => $option->is_correct,
                ])->all());
            }

            return $copy->load('options');
        });
    }

    /** @return array<int, string> */
    private function allowedValues(string $filter): array
    {
        return match ($filter) {
            'difficulty' => ['easy', 'medium', 'hard'],
            'type' => ['single', 'multiple', 'true_false', 'essay'],
            'visibility' => ['private', 'shared'],
            default => [],
        };
    }

    /** @return array<int, string> */
    private function questionRelations(): array
    {
        return [
            'teacher:id,name',
            'subject:id,name',
            'unit:id,teaching_assignment_id,academic_term_id,title',
            'unit.assignment:id,teacher_id,subject_id,grade_level_id',
            'unit.assignment.gradeLevel:id,name,stage',
            'unit.term:id,name',
            'lesson:id,title',
            'options:id,question_bank_question_id,option_text,is_correct',
        ];
    }

    /** @return array<string, mixed> */
    private function present(QuestionBankQuestion $question, User $teacher): array
    {
        $stageNames = [
            'primary'     => 'المرحلة الابتدائية',
            'preparatory' => 'المرحلة الإعدادية',
            'secondary'   => 'المرحلة الثانوية',
        ];
        $stageKey = $question->unit?->assignment?->gradeLevel?->stage;
        $stageName = $stageNames[$stageKey] ?? ($stageKey ? 'مرحلة ' . $stageKey : null);
        $gradeName = $question->unit?->assignment?->gradeLevel?->name ?? null;
        $termName = $question->unit?->term?->name ?? null;

        return [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'type' => $question->type,
            'points' => $question->points,
            'topic' => $question->topic,
            'difficulty' => $question->difficulty,
            'explanation' => $question->explanation,
            'tags' => $question->tags ?? [],
            'visibility' => $question->visibility,
            'allow_file_upload' => (bool) $question->allow_file_upload,
            'model_answer' => $question->model_answer,
            'teacher' => $question->teacher?->only(['id', 'name']),
            'subject' => $question->subject?->only(['id', 'name']),
            'unit' => $question->unit?->only(['id', 'title']),
            'lesson' => $question->lesson?->only(['id', 'title']),
            'stage_name' => $stageName,
            'grade_name' => $gradeName,
            'term_name'  => $termName,
            'options' => $question->options->map(fn ($option): array => [
                'id' => $option->id,
                'option_text' => $option->option_text,
                'is_correct' => (bool) $option->is_correct,
            ])->values(),
            'can_edit' => $question->isOwnedBy($teacher),
            'can_delete' => $question->isOwnedBy($teacher),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function subjectsFor(User $teacher): Collection
    {
        return Subject::query()
            ->whereHas('teachingAssignments', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Subject $subject): array => $subject->only(['id', 'name']))
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function unitsFor(User $teacher): Collection
    {
        $stageNames = [
            'primary'     => 'المرحلة الابتدائية',
            'preparatory' => 'المرحلة الإعدادية',
            'secondary'   => 'المرحلة الثانوية',
        ];

        return CurriculumUnit::query()
            ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->with([
                'assignment:id,teacher_id,subject_id,grade_level_id',
                'assignment.subject:id,name',
                'assignment.gradeLevel:id,name,stage',
                'term:id,name',
                'lessons:id,curriculum_unit_id,title,order',
            ])
            ->orderBy('order')
            ->get(['id', 'teaching_assignment_id', 'academic_term_id', 'title', 'order'])
            ->map(function (CurriculumUnit $unit) use ($stageNames): array {
                $stageKey = $unit->assignment?->gradeLevel?->stage;
                $stageName = $stageNames[$stageKey] ?? ($stageKey ? 'مرحلة ' . $stageKey : 'غير محدد');
                $gradeName = $unit->assignment?->gradeLevel?->name ?? 'غير محدد';
                $termName = $unit->term?->name ?? 'غير محدد';

                return [
                    'id'               => $unit->id,
                    'title'            => $unit->title,
                    'order'            => $unit->order,
                    'subject'          => $unit->assignment?->subject?->only(['id', 'name']),
                    'stage'            => $stageKey,
                    'stage_name'       => $stageName,
                    'grade_level_id'   => $unit->assignment?->grade_level_id,
                    'grade_name'       => $gradeName,
                    'academic_term_id' => $unit->academic_term_id,
                    'term_name'        => $termName,
                    'lessons'          => $unit->lessons->map(fn (GroupMaterial $lesson): array => [
                        'id'    => $lesson->id,
                        'title' => $lesson->title,
                        'order' => $lesson->order,
                    ])->values(),
                ];
            })
            ->values();
    }

    /** @return array<string, mixed> */
    private function normalizeData(User $teacher, array $data): array
    {
        $unit = null;
        $lesson = null;
        $unitId = $this->nullableInt($data['curriculum_unit_id'] ?? null);
        $lessonId = $this->nullableInt($data['lesson_id'] ?? null);

        if ($unitId !== null) {
            $unit = CurriculumUnit::with('assignment')->findOrFail($unitId);
            $this->assertAssignmentOwner($teacher, $unit->assignment?->teacher_id);
        }

        if ($lessonId !== null) {
            $lesson = GroupMaterial::with('unit.assignment')->findOrFail($lessonId);
            $this->assertAssignmentOwner($teacher, $lesson->unit?->assignment?->teacher_id);

            if ($unitId !== null && (int) $lesson->curriculum_unit_id !== $unitId) {
                throw ValidationException::withMessages([
                    'lesson_id' => 'يجب أن ينتمي الدرس إلى الوحدة المختارة.',
                ]);
            }

            $unitId ??= (int) $lesson->curriculum_unit_id;
            $unit ??= $lesson->unit;
        }

        $subjectId = $this->nullableInt($data['subject_id'] ?? null);
        $unitSubjectId = $unit?->assignment?->subject_id;

        if ($subjectId !== null && $unitSubjectId !== null && (int) $unitSubjectId !== $subjectId) {
            throw ValidationException::withMessages([
                'subject_id' => 'يجب أن يطابق التصنيف مادة الوحدة المختارة.',
            ]);
        }

        if ($subjectId === null && $unitSubjectId !== null) {
            $subjectId = (int) $unitSubjectId;
        }

        if ($subjectId !== null && ! Subject::query()
            ->whereKey($subjectId)
            ->whereHas('teachingAssignments', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => 'هذه المادة ليست ضمن موادك الدراسية.',
            ]);
        }

        return [
            'subject_id' => $subjectId,
            'curriculum_unit_id' => $unitId,
            'lesson_id' => $lessonId,
            'question_text' => trim((string) $data['question_text']),
            'type' => (string) $data['type'],
            'points' => max(1, (int) ($data['points'] ?? 1)),
            'topic' => $this->nullableString($data['topic'] ?? null),
            'difficulty' => (string) ($data['difficulty'] ?? QuestionBankQuestion::DIFFICULTY_MEDIUM),
            'explanation' => $this->nullableString($data['explanation'] ?? null),
            'tags' => $this->normalizeTags($data['tags'] ?? []),
            'visibility' => (string) ($data['visibility'] ?? QuestionBankQuestion::VISIBILITY_PRIVATE),
            'allow_file_upload' => (bool) ($data['allow_file_upload'] ?? false),
            'model_answer' => $this->nullableString($data['model_answer'] ?? null),
            'options' => ($data['type'] ?? '') === 'essay' ? [] : array_values($data['options'] ?? []),
        ];
    }

    /** @param array<int, array<string, mixed>> $options */
    private function replaceOptions(QuestionBankQuestion $question, array $options): void
    {
        $question->options()->delete();
        $question->options()->createMany(array_map(
            static fn (array $option): array => [
                'option_text' => trim((string) $option['option_text']),
                'is_correct' => (bool) $option['is_correct'],
            ],
            $options,
        ));
    }

    /** @param array<string, mixed> $data */
    private function attributesFromData(array $data): array
    {
        unset($data['options']);

        return $data;
    }

    /** @return array<int, string> */
    private function normalizeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->map(fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function assertOwner(User $teacher, QuestionBankQuestion $question): void
    {
        abort_unless($question->isOwnedBy($teacher), 403, 'لا تملك صلاحية تعديل هذا السؤال.');
    }

    private function assertQuizOwner(User $teacher, Quiz $quiz): void
    {
        $quiz->loadMissing('unit.assignment');
        $this->assertAssignmentOwner($teacher, $quiz->unit?->assignment?->teacher_id);
    }

    private function assertAssignmentOwner(User $teacher, mixed $ownerId): void
    {
        abort_unless((int) $ownerId === (int) $teacher->id, 403, 'هذا المحتوى ليس ضمن جدولك.');
    }
}

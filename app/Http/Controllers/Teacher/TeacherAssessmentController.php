<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\LearningResource;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Learning\Models\WorksheetSubmission;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeacherAssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $assignmentIds = TeachingAssignment::where('teacher_id', $teacher->id)->pluck('id');
        $groups = TeachingGroup::whereIn('teaching_assignment_id', $assignmentIds)
            ->with('assignment.subject:id,name')
            ->get(['id', 'name', 'teaching_assignment_id', 'is_active']);

        $selectedGroupId = $request->query('group_id') ? (int) $request->query('group_id') : null;

        $hasWorksheetGroup = \Illuminate\Support\Facades\Schema::hasColumn('worksheets', 'teaching_group_id');
        $hasQuizGroup = \Illuminate\Support\Facades\Schema::hasColumn('quizzes', 'teaching_group_id');

        // 1. Fetch Assessments (Worksheets: paper exams, homework, study sheets)
        $worksheetWith = [
            'material:id,title',
            'unit:id,title,teaching_assignment_id',
            'unit.assignment.subject:id,name',
        ];
        if ($hasWorksheetGroup) {
            $worksheetWith[] = 'group:id,name';
        }

        $worksheetsQuery = Worksheet::query()
            ->whereHas('unit', fn ($query) => $query->whereIn('teaching_assignment_id', $assignmentIds))
            ->with($worksheetWith)
            ->withCount(['submissions as total_submissions', 'submissions as graded_submissions' => function ($q) {
                $q->whereNotNull('graded_at');
            }]);

        if ($selectedGroupId && $hasWorksheetGroup) {
            $worksheetsQuery->where(function ($q) use ($selectedGroupId) {
                $q->where('teaching_group_id', $selectedGroupId)
                    ->orWhereNull('teaching_group_id');
            });
        }

        $worksheets = $worksheetsQuery->latest('created_at')->get()
            ->map(function (Worksheet $sheet): array {
                $isActive = (bool) ($sheet->getAttribute('is_active') ?? true);
                return [
                    'id' => $sheet->id,
                    'title' => $sheet->title,
                    'type' => $sheet->type,
                    'type_label' => match ($sheet->type) {
                        Worksheet::TYPE_PAPER_EXAM => 'تقييم مقالي / ورقي',
                        Worksheet::TYPE_HOMEWORK => 'تقييم دوري',
                        default => 'ملزمة / شيت',
                    },
                    'teaching_group_id' => $sheet->getAttribute('teaching_group_id'),
                    'group' => $sheet->relationLoaded('group') && $sheet->group ? ['id' => $sheet->group->id, 'name' => $sheet->group->name] : null,
                    'lesson' => $sheet->material ? ['id' => $sheet->material->id, 'title' => $sheet->material->title] : null,
                    'unit' => $sheet->unit ? ['id' => $sheet->unit->id, 'title' => $sheet->unit->title] : null,
                    'subject' => $sheet->unit?->assignment?->subject ? ['id' => $sheet->unit->assignment->subject->id, 'name' => $sheet->unit->assignment->subject->name] : null,
                    'available_from' => $sheet->available_from?->toIso8601String(),
                    'due_date' => $sheet->due_date?->toDateString(),
                    'max_score' => $sheet->max_score ?? 100,
                    'max_attempts' => $sheet->max_attempts ?? 1,
                    'allow_late_submission' => (bool) $sheet->allow_late_submission,
                    'requires_submission' => (bool) $sheet->requires_submission,
                    'is_active' => $isActive,
                    'status_label' => $isActive ? 'منشور' : 'معلق / مسودة',
                    'file_url' => filled($sheet->file_path) ? route('learning.worksheet.download', $sheet->id) : null,
                    'total_submissions' => $sheet->total_submissions,
                    'graded_submissions' => $sheet->graded_submissions,
                    'pending_submissions' => max(0, $sheet->total_submissions - $sheet->graded_submissions),
                ];
            });

        // 2. Fetch Quizzes (Electronic exams)
        $quizWith = [
            'material:id,title',
            'unit:id,title,teaching_assignment_id',
            'unit.assignment.subject:id,name',
        ];
        if ($hasQuizGroup) {
            $quizWith[] = 'group:id,name';
        }

        $quizzesQuery = Quiz::query()
            ->whereHas('unit', fn ($query) => $query->whereIn('teaching_assignment_id', $assignmentIds))
            ->with($quizWith)
            ->withCount(['questions', 'attempts']);

        if ($selectedGroupId && $hasQuizGroup) {
            $quizzesQuery->where(function ($q) use ($selectedGroupId) {
                $q->where('teaching_group_id', $selectedGroupId)
                    ->orWhereNull('teaching_group_id');
            });
        }

        $quizzes = $quizzesQuery->latest('created_at')->get()
            ->map(function (Quiz $quiz): array {
                $isActive = (bool) ($quiz->getAttribute('is_active') ?? true);
                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'mode' => 'electronic',
                    'type_label' => 'اختبار إلكتروني',
                    'teaching_group_id' => $quiz->getAttribute('teaching_group_id'),
                    'group' => $quiz->relationLoaded('group') && $quiz->group ? ['id' => $quiz->group->id, 'name' => $quiz->group->name] : null,
                    'lesson' => $quiz->material ? ['id' => $quiz->material->id, 'title' => $quiz->material->title] : null,
                    'unit' => $quiz->unit ? ['id' => $quiz->unit->id, 'title' => $quiz->unit->title] : null,
                    'subject' => $quiz->unit?->assignment?->subject ? ['id' => $quiz->unit->assignment->subject->id, 'name' => $quiz->unit->assignment->subject->name] : null,
                    'available_from' => $quiz->available_from?->toIso8601String(),
                    'available_until' => $quiz->available_until?->toIso8601String(),
                    'time_limit_minutes' => $quiz->time_limit_minutes,
                    'passing_score' => $quiz->passing_score,
                    'max_attempts' => $quiz->max_attempts ?? 3,
                    'allow_late_submission' => (bool) $quiz->allow_late_submission,
                    'is_active' => $isActive,
                    'status_label' => $isActive ? 'منشور' : 'معلق / مسودة',
                    'questions_count' => $quiz->questions_count,
                    'attempts_count' => $quiz->attempts_count,
                    'edit_url' => route('teacher.quizzes.edit', $quiz->id),
                ];
            });

        // 3. Fetch submissions for grading
        $submissions = WorksheetSubmission::query()
            ->whereHas('worksheet.unit', fn ($q) => $q->whereIn('teaching_assignment_id', $assignmentIds))
            ->with([
                'worksheet:id,title,max_score,due_date,type',
                'student:id,name,email',
            ])
            ->latest('submitted_at')
            ->limit(100)
            ->get()
            ->map(function (WorksheetSubmission $sub): array {
                return [
                    'id' => $sub->id,
                    'worksheet_id' => $sub->worksheet_id,
                    'worksheet_title' => $sub->worksheet?->title,
                    'worksheet_type' => $sub->worksheet?->type,
                    'max_score' => $sub->worksheet?->max_score ?? 100,
                    'student_id' => $sub->student_id,
                    'student_name' => $sub->student?->name ?? 'طالب',
                    'student_email' => $sub->student?->email,
                    'score' => $sub->score,
                    'teacher_feedback' => $sub->teacher_feedback,
                    'submitted_at' => $sub->submitted_at?->toIso8601String(),
                    'graded_at' => $sub->graded_at?->toIso8601String(),
                    'is_graded' => $sub->graded_at !== null,
                    'is_late' => (bool) ($sub->is_late || $sub->isLate()),
                    'file_url' => filled($sub->submitted_file_path) ? route('learning.submission.download', $sub->id) : null,
                ];
            });

        // 4. Units & Lessons for dropdowns
        $units = CurriculumUnit::whereIn('teaching_assignment_id', $assignmentIds)
            ->with(['lessons:id,curriculum_unit_id,title', 'assignment.subject:id,name'])
            ->orderBy('order')
            ->get(['id', 'teaching_assignment_id', 'title', 'order']);

        // 5. Library resources for direct linking
        $libraryResources = LearningResource::where('owner_teacher_id', $teacher->id)
            ->latest('created_at')
            ->limit(50)
            ->get(['id', 'title', 'file_path', 'category'])
            ->map(fn ($res) => [
                'id' => $res->id,
                'title' => $res->title,
                'file_path' => $res->file_path,
                'type' => $res->category ?? 'ملف مورد',
            ]);

        return Inertia::render('Teacher/Assessments', [
            'worksheets' => $worksheets,
            'quizzes' => $quizzes,
            'submissions' => $submissions,
            'groups' => $groups,
            'units' => $units,
            'libraryResources' => $libraryResources,
            'selectedGroupId' => $selectedGroupId,
            'initialTab' => $request->query('tab', 'assessments'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $teacher */
        $teacher = Auth::user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['required', 'string', 'in:homework,paper_exam,study'],
            'teaching_group_id' => ['nullable', 'integer', 'exists:teaching_groups,id'],
            'curriculum_unit_id' => ['nullable', 'integer', 'exists:curriculum_units,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:group_materials,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,zip,png,jpg,jpeg', 'max:10240'],
            'resource_id' => ['nullable', 'integer', 'exists:learning_resources,id'],
            'available_from' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'allow_late_submission' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = 'private://' . $request->file('file')->store('worksheets', 'local');
        } elseif (! empty($validated['resource_id'])) {
            $resource = LearningResource::findOrFail($validated['resource_id']);
            $filePath = $resource->file_path;
        }

        if (! $filePath) {
            $filePath = 'private://placeholder';
        }

        $unitId = $validated['curriculum_unit_id'] ?? null;
        if (! $unitId && ! empty($validated['lesson_id'])) {
            $lesson = GroupMaterial::find($validated['lesson_id']);
            $unitId = $lesson?->curriculum_unit_id;
        }

        if (! $unitId && ! empty($validated['teaching_group_id'])) {
            $group = TeachingGroup::find($validated['teaching_group_id']);
            $unitId = CurriculumUnit::where('teaching_assignment_id', $group?->teaching_assignment_id)->value('id');
        }

        if (! $unitId) {
            $assignmentId = TeachingAssignment::where('teacher_id', $teacher->id)->value('id');
            $unitId = CurriculumUnit::where('teaching_assignment_id', $assignmentId)->value('id');
        }

        abort_if(! $unitId, 422, 'الرجاء اختيار الوحدة أو الدرس المرتبط بالتقييم.');

        Worksheet::create([
            'curriculum_unit_id' => $unitId,
            'lesson_id' => $validated['lesson_id'] ?? null,
            'teaching_group_id' => $validated['teaching_group_id'] ?? null,
            'title' => $validated['title'],
            'file_path' => $filePath,
            'type' => $validated['type'],
            'requires_submission' => true,
            'available_from' => ! empty($validated['available_from']) ? $validated['available_from'] : null,
            'due_date' => ! empty($validated['due_date']) ? $validated['due_date'] : null,
            'allow_late_submission' => (bool) ($validated['allow_late_submission'] ?? true),
            'max_attempts' => (int) ($validated['max_attempts'] ?? 1),
            'max_score' => (int) ($validated['max_score'] ?? 100),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('success', 'تم إنشاء وإسناد التقييم بنجاح.');
    }

    public function update(Request $request, int $worksheetId): RedirectResponse
    {
        $worksheet = Worksheet::with('unit.assignment')->findOrFail($worksheetId);
        $this->assertOwnsAssignment($worksheet->unit?->assignment);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'type' => ['required', 'string', 'in:homework,paper_exam,study'],
            'teaching_group_id' => ['nullable', 'integer', 'exists:teaching_groups,id'],
            'lesson_id' => ['nullable', 'integer', 'exists:group_materials,id'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,zip,png,jpg,jpeg', 'max:10240'],
            'available_from' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'allow_late_submission' => ['nullable', 'boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('file')) {
            $validated['file_path'] = 'private://' . $request->file('file')->store('worksheets', 'local');
        }

        unset($validated['file']);
        $worksheet->update($validated);

        return back()->with('success', 'تم تعديل بيانات التقييم بنجاح.');
    }

    public function toggleStatus(int $worksheetId): RedirectResponse
    {
        $worksheet = Worksheet::with('unit.assignment')->findOrFail($worksheetId);
        $this->assertOwnsAssignment($worksheet->unit?->assignment);

        $worksheet->update(['is_active' => ! $worksheet->is_active]);

        $message = $worksheet->is_active
            ? 'تم نشر التقييم وأصبح متاحاً للطلاب.'
            : 'تم تعليق التقييم وحجبه عن الطلاب مؤقتاً.';

        return back()->with('success', $message);
    }

    public function destroy(int $worksheetId): RedirectResponse
    {
        $worksheet = Worksheet::with('unit.assignment')->findOrFail($worksheetId);
        $this->assertOwnsAssignment($worksheet->unit?->assignment);

        $worksheet->delete();

        return back()->with('success', 'تم حذف التقييم بنجاح.');
    }

    public function toggleQuizStatus(int $quizId): RedirectResponse
    {
        $quiz = Quiz::with('unit.assignment')->findOrFail($quizId);
        $this->assertOwnsAssignment($quiz->unit?->assignment);

        $quiz->update(['is_active' => ! $quiz->is_active]);

        $message = $quiz->is_active
            ? 'تم نشر الاختبار الإلكتروني بنجاح.'
            : 'تم تعليق الاختبار الإلكتروني.';

        return back()->with('success', $message);
    }

    public function gradeSubmission(Request $request, int $submissionId): RedirectResponse
    {
        $submission = WorksheetSubmission::with([
            'worksheet.unit.assignment',
            'student.parentLinks.parent',
        ])->findOrFail($submissionId);

        $this->assertOwnsAssignment($submission->worksheet?->unit?->assignment);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:' . ($submission->worksheet->max_score ?? 1000)],
            'teacher_feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update([
            'score' => $validated['score'],
            'teacher_feedback' => $validated['teacher_feedback'] ?? null,
            'graded_at' => now(),
        ]);

        $assessmentTitle = $submission->worksheet->title;
        $maxScore = $submission->worksheet->max_score ?? 100;
        $msg = "تم تقييم إجابتك في '{$assessmentTitle}' بدرجة {$validated['score']}/{$maxScore}.";
        if (! empty($validated['teacher_feedback'])) {
            $msg .= " ملاحظات المعلم: {$validated['teacher_feedback']}";
        }

        $submission->student?->notify(new GenericDatabaseNotification([
            'title' => 'تم تقييم إجابتك ورصد الدرجة 📝',
            'message' => $msg,
            'link' => route('student.assessments'),
        ]));

        foreach ($submission->student?->parentLinks ?? [] as $link) {
            if ($link->verified_at && $link->parent) {
                $link->parent->notify(new GenericDatabaseNotification([
                    'title' => 'تقييم دراسي جديد للطالب',
                    'message' => "الطالب {$submission->student->name} حصل على {$validated['score']}/{$maxScore} في '{$assessmentTitle}'."
                        . (! empty($validated['teacher_feedback']) ? " تقييم المعلم: {$validated['teacher_feedback']}" : ''),
                    'link' => route('parent.dashboard', ['student_id' => $submission->student_id]),
                ]));
            }
        }

        return back()->with('success', 'تم رصد وحفظ درجة التقييم والملاحظات بنجاح.');
    }

    private function assertOwnsAssignment(?TeachingAssignment $assignment): void
    {
        abort_unless(
            $assignment && $assignment->teacher_id === Auth::id(),
            403,
            'غير مصرح لك بإدارة هذا المحتوى.',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Academic\Models;

use App\Domain\Learning\Models\GroupMaterial;
use App\Domain\Learning\Models\Worksheet;
use App\Domain\Quiz\Models\Quiz;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * "الوحدة" — a chapter of the syllabus: a run of lessons that ends in a unit
 * exam, one electronic and one on paper.
 *
 * It belongs to the assignment rather than to a group, so a teacher writes the
 * syllabus once for a subject and grade and every timetable slot — and every
 * private student — teaches from it.
 */
class CurriculumUnit extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Academic\CurriculumUnitFactory
    {
        return new \Database\Factories\Domain\Academic\CurriculumUnitFactory();
    }

    protected $fillable = [
        'teaching_assignment_id',
        'academic_term_id',
        'order',
        'title',
        'description',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'order'        => 'integer',
            'is_published' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(GroupMaterial::class, 'curriculum_unit_id')->orderBy('order');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'curriculum_unit_id');
    }

    public function worksheets(): HasMany
    {
        return $this->hasMany(Worksheet::class, 'curriculum_unit_id');
    }

    /**
     * "اختبار الوحدة — النموذج الإلكتروني". A quiz attached to a lesson is a
     * short drill for that lesson; the unit exam is the one standing alone.
     */
    public function electronicExam(): HasOne
    {
        return $this->hasOne(Quiz::class, 'curriculum_unit_id')->whereNull('lesson_id');
    }

    /** "اختبار الوحدة — النموذج الورقي": a file the student prints and answers. */
    public function paperExam(): HasOne
    {
        return $this->hasOne(Worksheet::class, 'curriculum_unit_id')
            ->where('type', Worksheet::TYPE_PAPER_EXAM);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    /**
     * Where content lands when it is published from a screen that still speaks
     * in groups. Made on demand so a teacher who has not laid out the syllabus
     * yet can still upload something.
     */
    public static function firstFor(TeachingGroup $group): self
    {
        return self::firstOrCreate(
            [
                'teaching_assignment_id' => $group->teaching_assignment_id,
                'academic_term_id'       => $group->academic_term_id ?? AcademicTerm::currentOrNext()?->id,
                'order'                  => 1,
            ],
            ['title' => 'الوحدة الأولى'],
        );
    }
}

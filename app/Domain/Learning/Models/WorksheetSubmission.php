<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorksheetSubmission extends Model
{
    protected $fillable = [
        'worksheet_id',
        'student_id',
        'submitted_file_path',
        'score',
        'teacher_feedback',
        'is_late',
        'submitted_at',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'score'        => 'integer',
            'is_late'      => 'boolean',
            'submitted_at' => 'datetime',
            'graded_at'    => 'datetime',
        ];
    }

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(Worksheet::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }

    public function isLate(): bool
    {
        if ($this->is_late !== null) {
            return (bool) $this->is_late;
        }

        if (! $this->worksheet || ! $this->worksheet->due_date) {
            return false;
        }

        $submitted = $this->submitted_at ?? $this->created_at;

        return $submitted && $submitted->startOfDay()->isAfter($this->worksheet->due_date);
    }
}

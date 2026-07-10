<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

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
        'submitted_at',
        'graded_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at'    => 'datetime',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(Worksheet::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasFactory;

    public const TYPE_SINGLE = 'single';
    public const TYPE_MULTIPLE = 'multiple';
    public const TYPE_TRUE_FALSE = 'true_false';
    public const TYPE_ESSAY = 'essay';

    protected $fillable = [
        'quiz_id',
        'question_bank_question_id',
        'question_text',
        'type',
        'points',
        'order',
        'allow_file_upload',
        'model_answer',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'points' => 'integer',
            'allow_file_upload' => 'boolean',
        ];
    }

    public function isEssay(): bool
    {
        return $this->type === 'essay';
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionBankQuestion::class, 'question_bank_question_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'question_id');
    }

    public function correctOptions(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'question_id')->where('is_correct', true);
    }
}

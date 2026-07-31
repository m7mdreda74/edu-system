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

    protected $fillable = [
        'quiz_id',
        'question_text',
        'type',
        'points',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'points' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
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

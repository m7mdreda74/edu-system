<?php

declare(strict_types=1);

namespace App\Domain\Quiz\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBankOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_bank_question_id',
        'option_text',
        'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankQuestion::class, 'question_bank_question_id');
    }
}

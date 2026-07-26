<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A chat thread between one student and one teacher about one subject —
 * i.e. scoped to a teaching assignment.
 */
class Conversation extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'student_id',
        'teacher_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}

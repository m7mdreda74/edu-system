<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A chat thread between one student and one teacher about one subject —
 * i.e. scoped to a teaching assignment.
 */
class Conversation extends Model
{
    protected $fillable = [
        'kind',
        'context_student_id',
        'teaching_assignment_id',
        'student_id',
        'teacher_id',
        'subject',
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

    public function contextStudent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'context_student_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('participant_role')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    protected static function booted(): void
    {
        static::created(function (Conversation $conversation): void {
            foreach ([
                [$conversation->student_id, 'student'],
                [$conversation->teacher_id, 'teacher'],
            ] as [$userId, $role]) {
                if ($userId) {
                    DB::table('conversation_participants')->insertOrIgnore([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'participant_role' => $role,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}

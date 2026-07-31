<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\Communication\Models\Conversation;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateLessonRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'student_id',
        'teaching_assignment_id',
        'conversation_id',
        'student_note',
        'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

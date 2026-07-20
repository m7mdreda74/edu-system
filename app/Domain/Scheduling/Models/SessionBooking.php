<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionBooking extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'teaching_group_id', 'private_session_slot_id', 'status', 'notes', 'booked_at'];

    protected function casts(): array
    {
        return ['booked_at' => 'datetime'];
    }

    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function group(): BelongsTo { return $this->belongsTo(TeachingGroup::class, 'teaching_group_id'); }
    public function privateSlot(): BelongsTo { return $this->belongsTo(PrivateSessionSlot::class, 'private_session_slot_id'); }
}

<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrivateSessionSlot extends Model
{
    use HasFactory;

    protected $fillable = ['teaching_assignment_id', 'starts_at', 'ends_at', 'timezone', 'is_free_intro', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_free_intro' => 'boolean'];
    }

    public function assignment(): BelongsTo { return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id'); }
    public function booking(): HasOne { return $this->hasOne(SessionBooking::class)->latestOfMany(); }
    public function isAvailable(): bool { return $this->status === 'available' && $this->starts_at->isFuture() && ! $this->booking()->where('status', 'confirmed')->exists(); }
}

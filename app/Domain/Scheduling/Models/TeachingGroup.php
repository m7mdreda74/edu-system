<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'teaching_assignment_id', 'name', 'capacity', 'day_of_week',
        'start_time', 'end_time', 'duration_minutes', 'timezone', 'is_active',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'day_of_week' => 'integer', 'duration_minutes' => 'integer', 'is_active' => 'boolean'];
    }

    public function assignment(): BelongsTo { return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id'); }
    public function bookings(): HasMany { return $this->hasMany(SessionBooking::class); }
    public function activeBookings(): HasMany { return $this->bookings()->where('status', 'confirmed'); }
    public function hasCapacity(): bool { return $this->activeBookings()->count() < $this->capacity; }
}

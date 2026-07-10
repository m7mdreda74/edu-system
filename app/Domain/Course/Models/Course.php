<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

use App\Domain\Communication\Models\Conversation;
use App\Domain\Course\Models\CourseLesson;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Course\Models\Subject;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\User;
use App\Infrastructure\Observers\CourseObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['effective_price'];

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'title',
        'slug',
        'description',
        'thumbnail',
        'price',
        'discount_price',
        'is_published',
        'grade_level',
        'total_duration',
        'level',
        'total_lessons',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'integer', // stored in smallest currency unit
            'discount_price' => 'integer',
            'is_published'   => 'boolean',
            'total_duration' => 'integer',
            'total_lessons'  => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::observe(CourseObserver::class);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function worksheets(): HasMany
    {
        return $this->hasMany(Worksheet::class);
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    protected static function newFactory(): \Database\Factories\Domain\Course\CourseFactory
    {
        return new \Database\Factories\Domain\Course\CourseFactory();
    }

    /** Returns effective price (discount if set, otherwise full price) */
    public function getEffectivePrice(): int
    {
        return (int) ($this->discount_price ?? $this->price ?? 0);
    }

    public function getEffectivePriceAttribute(): int
    {
        return $this->getEffectivePrice();
    }

    public function isFree(): bool
    {
        return $this->getEffectivePrice() === 0;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->teacher_id === $user->id;
    }

    /** Average rating rounded to 1 decimal */
    public function getAverageRating(): float
    {
        return (float) $this->reviews()
            ->where('is_approved', true)
            ->avg('rating') ?? 0.0;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Payment\Models\Payment;
use App\Domain\Scheduling\Models\SessionBooking;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Infrastructure\Observers\UserObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @mixin \Spatie\Permission\Traits\HasRoles
 */
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected static function newFactory(): \Database\Factories\Domain\User\UserFactory
    {
        return new \Database\Factories\Domain\User\UserFactory();
    }

    // IDE Helper: hasRole()/syncRoles() come from Spatie\Permission\Traits\HasRoles.
    // The @mixin above ensures static analysis resolves them correctly.

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'grade_level',
        'subject_id',
        'bio',
        'headline',
        'intro_video_url',
        'intro_video_thumbnail',
        'years_experience',
        'is_featured',
        'is_active',
        'commission_percent',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_active'          => 'boolean',
            'is_featured'        => 'boolean',
            'years_experience'   => 'integer',
            'commission_percent' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::observe(UserObserver::class);
    }

    // ─── Relationships ────────────────────────────────────────────

    /**
     * The one subject this teacher specialises in. A subject has many
     * teachers; a teacher has one subject.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** The grades this teacher covers, all within their one subject. */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'teacher_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'student_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(SessionBooking::class, 'student_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Ratings this teacher has received. */
    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(TeacherReview::class, 'teacher_id');
    }

    public function studentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'parent_user_id');
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'student_user_id');
    }

    // ─── Role Helpers ──────────────────────────────────────────────

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isParent(): bool
    {
        return $this->hasRole('parent');
    }

    // ─── Domain Helpers ────────────────────────────────────────────

    /** Does this student have a live subscription to the given group? */
    public function hasActiveSubscriptionTo(TeachingGroup $group): bool
    {
        return $this->subscriptions()
            ->active()
            ->where('teaching_group_id', $group->id)
            ->exists();
    }

    /** Live subscription to any group/private slot under a teacher's subject. */
    public function hasActiveSubscriptionToAssignment(int $assignmentId): bool
    {
        return $this->subscriptions()
            ->active()
            ->where('teaching_assignment_id', $assignmentId)
            ->exists();
    }

    /** Average approved rating for a teacher, rounded to one decimal. */
    public function averageRating(): float
    {
        return round((float) ($this->reviewsReceived()->where('is_approved', true)->avg('rating') ?? 0), 1);
    }

    /** Distinct students currently subscribed to any of this teacher's groups. */
    public function activeStudentsCount(): int
    {
        return Subscription::active()
            ->whereIn('teaching_assignment_id', $this->teachingAssignments()->select('id'))
            ->distinct('student_id')
            ->count('student_id');
    }
}

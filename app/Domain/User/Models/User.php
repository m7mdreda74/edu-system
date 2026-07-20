<?php

declare(strict_types=1);

namespace App\Domain\User\Models;

use App\Domain\Communication\Models\ChatMessage;
use App\Domain\Communication\Models\Conversation;
use App\Domain\Course\Models\Course;
use App\Domain\Course\Models\LiveSession;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Payment\Models\Payment;
use App\Domain\User\Models\ParentStudentLink;
use App\Infrastructure\Observers\UserObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'bio',
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
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'commission_percent' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::observe(UserObserver::class);
    }

    // ─── Relationships ────────────────────────────────────────────

    public function coursesAsTeacher(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function studentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'parent_user_id');
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'student_user_id');
    }

    // ─── Domain Helpers ────────────────────────────────────────────

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

    public function isEnrolledIn(Course $course): bool
    {
        return $this->enrollments()
            ->where('course_id', $course->id)
            ->exists();
    }
}

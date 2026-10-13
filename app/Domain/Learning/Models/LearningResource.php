<?php

declare(strict_types=1);

namespace App\Domain\Learning\Models;

use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\User\Models\User;
use Database\Factories\Domain\Learning\LearningResourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningResource extends Model
{
    use HasFactory, SoftDeletes;

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    public const APPROVAL_NONE = 'none';

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected static function newFactory(): LearningResourceFactory
    {
        return LearningResourceFactory::new();
    }

    protected $fillable = [
        'owner_teacher_id',
        'title',
        'description',
        'category',
        'tags',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'visibility',
        'approval_status',
        'approved_by_admin_id',
        'approved_at',
        'rejected_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'size_bytes' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_teacher_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(
            TeachingAssignment::class,
            'learning_resource_assignments',
            'learning_resource_id',
            'teaching_assignment_id',
        )->withPivot(['attached_by_teacher_id'])->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('file_path');
    }

    public function isPendingShare(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED
            && $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isApprovedShared(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED
            && $this->approval_status === self::APPROVAL_APPROVED;
    }

    public function formattedSize(): string
    {
        $bytes = (int) ($this->size_bytes ?? 0);

        if ($bytes < 1024) {
            return $bytes.' ب';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' ك.ب';
        }

        return round($bytes / (1024 * 1024), 1).' م.ب';
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'teaching_group_id',
        'title',
        'body',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class, 'teaching_group_id');
    }
}

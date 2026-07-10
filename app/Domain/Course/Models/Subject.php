<?php

declare(strict_types=1);

namespace App\Domain\Course\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\Domain\Course\SubjectFactory
    {
        return new \Database\Factories\Domain\Course\SubjectFactory();
    }

    protected $fillable = [
        'name',
        'name_en',
        'grade_level',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}

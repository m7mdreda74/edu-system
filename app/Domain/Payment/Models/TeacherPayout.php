<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherPayout extends Model
{
    protected $table = 'teacher_payouts';

    protected $fillable = [
        'teacher_id',
        'amount',
        'platform_commission',
        'period_start',
        'period_end',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'paid_at'      => 'datetime',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}

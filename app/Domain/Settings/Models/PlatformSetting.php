<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * Get all settings as a key-value pair and cache them forever until updated.
     *
     * @return array<string, mixed>
     */
    public static function getAllCached(): array
    {
        return Cache::rememberForever('platform_settings', function () {
            return self::all()->pluck('value', 'key')->toArray();
        });
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('platform_settings');
        });

        static::deleted(function () {
            Cache::forget('platform_settings');
        });
    }
}

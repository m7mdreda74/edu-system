<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Support\PlatformSettingRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    private const HIDDEN_FROM_CLIENT_KEYS = [
        'active_gateway',
        'tap_publishable_key',
        'tap_secret_key',
        'fatora_api_key',
        'manual_payment_methods',
    ];

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
        return Cache::remember('platform_settings', now()->addMinute(), function () {
            return self::query()
                ->whereNotIn('key', self::HIDDEN_FROM_CLIENT_KEYS)
                ->whereIn('key', PlatformSettingRegistry::keys())
                ->pluck('value', 'key')
                ->toArray();
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

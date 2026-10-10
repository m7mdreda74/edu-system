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

    /** @var array<string, mixed>|null */
    private static ?array $runtimeCache = null;

    /**
     * Get all settings as a key-value pair and cache them forever until updated.
     *
     * @return array<string, mixed>
     */
    public static function getAllCached(): array
    {
        if (self::$runtimeCache !== null) {
            return self::$runtimeCache;
        }

        return self::$runtimeCache = Cache::remember('platform_settings', now()->addHours(6), function () {
            return self::query()
                ->whereNotIn('key', self::HIDDEN_FROM_CLIENT_KEYS)
                ->whereIn('key', PlatformSettingRegistry::keys())
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public static function clearRuntimeCache(): void
    {
        self::$runtimeCache = null;
        Cache::forget('platform_settings');
    }

    protected static function booted(): void
    {
        static::saved(function () {
            self::clearRuntimeCache();
        });

        static::deleted(function () {
            self::clearRuntimeCache();
        });
    }
}

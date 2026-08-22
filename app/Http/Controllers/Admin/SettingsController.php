<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\PlatformSettingRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const LIVE_STAT_KEYS = [
        'home_stats_students',
        'home_stats_courses',
        'home_stats_teachers',
    ];

    private const DISABLED_PAYMENT_KEYS = [
        'active_gateway',
        'tap_publishable_key',
        'tap_secret_key',
        'fatora_api_key',
        'manual_payment_methods',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'dbSettings' => PlatformSetting::query()
                ->whereNotIn('key', self::DISABLED_PAYMENT_KEYS)
                ->whereIn('key', PlatformSettingRegistry::keys())
                ->get(),
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'max:100'],
            'settings.*.id' => ['nullable', 'integer', 'min:1'],
            'settings.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,63}$/', 'distinct'],
            'settings.*.value' => ['nullable', 'string', 'max:100000'],
            'settings.*.type' => ['required', 'string', 'in:string,integer,boolean'],
        ]);

        $settings = collect($validated['settings'])
            ->reject(fn (array $setting): bool => in_array($setting['key'], [
                ...self::LIVE_STAT_KEYS,
                ...self::DISABLED_PAYMENT_KEYS,
            ], true));

        $providedIds = $settings->pluck('id')->filter()->unique()->values();
        $existingById = $providedIds->isNotEmpty()
            ? PlatformSetting::query()->whereIn('id', $providedIds)->get()->keyBy('id')
            : collect();

        foreach ($settings as $index => $setting) {
            $existing = isset($setting['id']) ? $existingById->get($setting['id']) : null;

            if (isset($setting['id']) && (! $existing || $existing->key !== $setting['key'])) {
                throw ValidationException::withMessages([
                    "settings.{$index}.key" => 'الإعداد غير موجود أو تغيّر أثناء الحفظ. حدّث الصفحة وحاول مجدداً.',
                ]);
            }

            if (! PlatformSettingRegistry::isKnown($setting['key']) && ! $existing) {
                throw ValidationException::withMessages([
                    "settings.{$index}.key" => 'مفتاح الإعداد غير مسموح.',
                ]);
            }

            $error = PlatformSettingRegistry::isKnown($setting['key'])
                ? PlatformSettingRegistry::validate($setting['key'], $setting['value'], $setting['type'])
                : $this->validateLegacySetting($setting, $existing);

            if ($error !== null) {
                throw ValidationException::withMessages([
                    "settings.{$index}.value" => $error,
                ]);
            }
        }

        $timestamp = now();
        $existingRows = $settings
            ->filter(fn (array $setting): bool => isset($setting['id']))
            ->map(fn (array $setting): array => [
                'id' => $setting['id'],
                'key' => $setting['key'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'updated_at' => $timestamp,
            ])
            ->values()
            ->all();

        $newRows = $settings
            ->reject(fn (array $setting): bool => isset($setting['id']))
            ->map(fn (array $setting): array => [
                'key' => $setting['key'],
                'value' => $setting['value'],
                'type' => $setting['type'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->values()
            ->all();

        $changes = $settings->map(function (array $setting) use ($existingById): array {
            $oldValue = isset($setting['id']) ? $existingById->get($setting['id'])?->value : null;

            return [
                'key' => $setting['key'],
                'old_value_hash' => AuditLogger::hashValue($oldValue),
                'new_value_hash' => AuditLogger::hashValue($setting['value']),
            ];
        })->values()->all();

        DB::transaction(function () use ($existingRows, $newRows): void {
            if ($existingRows !== []) {
                DB::table('platform_settings')->upsert(
                    $existingRows,
                    ['id'],
                    ['key', 'value', 'type', 'updated_at'],
                );
            }

            if ($newRows !== []) {
                DB::table('platform_settings')->upsert(
                    $newRows,
                    ['key'],
                    ['value', 'type', 'updated_at'],
                );
            }
        });

        Cache::forget('platform_settings');
        AuditLogger::record('settings.updated', null, ['changes' => $changes]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'تم حفظ الإعدادات بنجاح.']);
        }

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    public function sitePages(): Response
    {
        return Inertia::render('Admin/SitePages', [
            'dbSettings' => PlatformSetting::query()
                ->whereNotIn('key', self::DISABLED_PAYMENT_KEYS)
                ->whereIn('key', array_values(array_filter(
                    PlatformSettingRegistry::keys(),
                    fn (string $key): bool => PlatformSettingRegistry::isSitePageKey($key),
                )))
                ->pluck('value', 'key')
                ->toArray(),
        ]);
    }

    public function updateSitePages(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array', 'max:100'],
            'settings.*' => ['nullable', 'string', 'max:100000'],
        ]);

        $incoming = collect($validated['settings']);
        foreach ($incoming as $key => $value) {
            if (! is_string($key) || ! PlatformSettingRegistry::isSitePageKey($key)) {
                throw ValidationException::withMessages([
                    'settings' => 'يوجد مفتاح محتوى غير مسموح.',
                ]);
            }

            $error = PlatformSettingRegistry::validate(
                $key,
                $value,
                PlatformSettingRegistry::expectedType($key),
            );

            if ($error !== null) {
                throw ValidationException::withMessages([
                    "settings.{$key}" => $error,
                ]);
            }
        }

        $timestamp = now();
        $existingValues = PlatformSetting::query()
            ->whereIn('key', $incoming->keys()->all())
            ->pluck('value', 'key');

        $rows = $incoming->map(function (mixed $value, string $key) use ($timestamp): array {
                $dbValue = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    : ($value === null ? '' : (string) $value);

                return [
                    'key' => $key,
                    'value' => $dbValue,
                    'type' => PlatformSettingRegistry::expectedType($key),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })
            ->values()
            ->all();

        DB::table('platform_settings')->upsert(
            $rows,
            ['key'],
            ['value', 'type', 'updated_at'],
        );

        Cache::forget('platform_settings');
        AuditLogger::record('site_pages.updated', null, [
            'changes' => $incoming->map(fn (mixed $value, string $key): array => [
                'key' => $key,
                'old_value_hash' => AuditLogger::hashValue($existingValues->get($key)),
                'new_value_hash' => AuditLogger::hashValue(is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                    : ($value === null ? '' : (string) $value)),
            ])->values()->all(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'تم حفظ التغييرات بنجاح.']);
        }

        return back()->with('success', 'تم حفظ التغييرات بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $setting = PlatformSetting::findOrFail($id);
        abort_unless(PlatformSettingRegistry::isKnown($setting->key), 404);

        $setting->delete();
        AuditLogger::record('settings.deleted', $setting, [
            'key' => $setting->key,
            'old_value_hash' => AuditLogger::hashValue($setting->value),
        ]);

        return back()->with('success', 'تم حذف الإعداد بنجاح.');
    }

    /** @param array{id?: int|null, key: string, type: string, value: string|null} $setting */
    private function validateLegacySetting(array $setting, ?PlatformSetting $existing): ?string
    {
        if (! $existing || $existing->type !== $setting['type']) {
            return 'الإعداد القديم لا يمكن تغيير نوعه أو إنشاء مفتاح جديد.';
        }

        if (! is_string($setting['value']) || mb_strlen($setting['value']) > 10_000) {
            return 'قيمة الإعداد طويلة أو غير صالحة.';
        }

        return strip_tags($setting['value']) === $setting['value']
            ? null
            : 'HTML غير مسموح داخل الإعدادات.';
    }
}

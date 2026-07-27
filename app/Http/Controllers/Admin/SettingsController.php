<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Settings', [
            'dbSettings' => PlatformSetting::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.id' => ['nullable', 'integer'],
            'settings.*.key' => ['required', 'string', 'max:255', 'distinct'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.type' => ['required', 'string', 'in:string,integer,boolean'],
        ]);

        $settings = collect($validated['settings']);

        foreach ($settings as $index => $setting) {
            if (
                $setting['key'] === 'site_theme'
                && ! in_array($setting['value'], ['royal', 'ocean', 'emerald', 'violet'], true)
            ) {
                throw ValidationException::withMessages([
                    "settings.{$index}.value" => 'الثيم المختار غير متاح.',
                ]);
            }
        }

        $providedIds = $settings->pluck('id')->filter()->unique()->values();

        if ($providedIds->isNotEmpty()) {
            $existingIds = DB::table('platform_settings')
                ->whereIn('id', $providedIds)
                ->pluck('id');

            if ($providedIds->diff($existingIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'settings' => 'أحد الإعدادات لم يعد موجوداً. حدّث الصفحة وحاول مجدداً.',
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

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    public function sitePages(): Response
    {
        return Inertia::render('Admin/SitePages', [
            'dbSettings' => PlatformSetting::all()->pluck('value', 'key')->toArray(),
        ]);
    }

    public function updateSitePages(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $timestamp = now();
        $rows = collect($validated['settings'])
            ->map(function (mixed $value, string $key) use ($timestamp): array {
                $dbValue = is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : ($value === null ? '' : (string) $value);

                return [
                    'key' => $key,
                    'value' => $dbValue,
                    'type' => 'string',
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

        return back()->with('success', 'تم حفظ التغييرات بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        PlatformSetting::findOrFail($id)->delete();

        return back()->with('success', 'تم حذف الإعداد بنجاح.');
    }
}

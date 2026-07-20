<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        // Get all settings from DB to allow editing
        $dbSettings = PlatformSetting::all();

        return Inertia::render('Admin/Settings', [
            'dbSettings' => $dbSettings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.id' => ['nullable', 'integer', 'exists:platform_settings,id'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable', 'string'],
            'settings.*.type' => ['required', 'string', 'in:string,integer,boolean'],
        ]);

        foreach ($validated['settings'] as $index => $settingData) {
            if ($settingData['key'] === 'site_theme' && ! in_array($settingData['value'], ['royal', 'ocean', 'emerald', 'violet'], true)) {
                throw ValidationException::withMessages([
                    "settings.{$index}.value" => 'الثيم المختار غير متاح.',
                ]);
            }
        }

        foreach ($validated['settings'] as $settingData) {
            if (isset($settingData['id'])) {
                // Update existing
                PlatformSetting::where('id', $settingData['id'])->update([
                    'key' => $settingData['key'],
                    'value' => $settingData['value'],
                    'type' => $settingData['type'],
                ]);
            } else {
                // Create new
                PlatformSetting::updateOrCreate(
                    ['key' => $settingData['key']],
                    [
                        'value' => $settingData['value'],
                        'type' => $settingData['type'],
                    ]
                );
            }
        }

        // Touch cache
        \Illuminate\Support\Facades\Cache::forget('platform_settings');

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }

    public function sitePages(): Response
    {
        // Get all settings mapped as key-value pairs
        $dbSettings = PlatformSetting::all()->pluck('value', 'key')->toArray();

        return Inertia::render('Admin/SitePages', [
            'dbSettings' => $dbSettings,
        ]);
    }

    public function updateSitePages(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            $dbValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value === null ? '' : (string)$value);

            PlatformSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $dbValue,
                    'type' => 'string'
                ]
            );
        }

        \Illuminate\Support\Facades\Cache::forget('platform_settings');

        return back()->with('success', 'تم حفظ التغييرات بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $setting = PlatformSetting::findOrFail($id);
        
        // Prevent deleting core settings if necessary, but for now allow all
        $setting->delete();

        return back()->with('success', 'تم حذف الإعداد بنجاح.');
    }
}

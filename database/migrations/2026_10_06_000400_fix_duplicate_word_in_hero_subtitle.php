<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')
            ->where('value', 'like', '%التعليمية التعليمية%')
            ->get(['id', 'value'])
            ->each(function (object $setting): void {
                $cleaned = preg_replace('/التعليمية\s+التعليمية/u', 'التعليمية', (string) $setting->value);

                DB::table('platform_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => $cleaned,
                        'updated_at' => now(),
                    ]);
            });

        // Specifically ensure home_hero_subtitle is clean
        DB::table('platform_settings')
            ->where('key', 'home_hero_subtitle')
            ->where('value', 'like', '%التعليمية التعليمية%')
            ->update([
                'value' => 'بوابة المجد التعليمية الأولى في قطر',
                'updated_at' => now(),
            ]);

        Cache::forget('platform_settings');
        Cache::forget('admin.site_pages_settings');
    }

    public function down(): void
    {
        // No down migration needed for typo fix
    }
};

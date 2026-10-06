<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            'منصة التفوق' => 'بوابة المجد التعليمية',
            'تطبيقات التفوق' => 'تطبيقات بوابة المجد',
            'التفوق على يوتيوب' => 'بوابة المجد على يوتيوب',
            'لماذا التفوق خيارك الأول' => 'لماذا بوابة المجد خيارك الأول',
            'ركائز المنصة ومحاور التفوق الدراسي' => 'ركائز المنصة ومحاور التميز الدراسي',
        ];

        DB::table('platform_settings')
            ->select(['id', 'key', 'value'])
            ->orderBy('id')
            ->get()
            ->each(function (object $setting) use ($replacements): void {
                $value = (string) $setting->value;

                if ($setting->key === 'platform_name' && trim($value) === 'التفوق') {
                    $value = 'بوابة المجد التعليمية';
                }

                $updatedValue = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    $value,
                );

                if ($updatedValue === (string) $setting->value) {
                    return;
                }

                DB::table('platform_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => $updatedValue,
                        'updated_at' => now(),
                    ]);
            });

        Cache::forget('platform_settings');
    }

    public function down(): void
    {
        $replacements = [
            'بوابة المجد التعليمية' => 'منصة التفوق',
            'تطبيقات بوابة المجد' => 'تطبيقات التفوق',
            'بوابة المجد على يوتيوب' => 'التفوق على يوتيوب',
            'لماذا بوابة المجد خيارك الأول' => 'لماذا التفوق خيارك الأول',
            'ركائز المنصة ومحاور التميز الدراسي' => 'ركائز المنصة ومحاور التفوق الدراسي',
        ];

        DB::table('platform_settings')
            ->select(['id', 'value'])
            ->orderBy('id')
            ->get()
            ->each(function (object $setting) use ($replacements): void {
                $value = str_replace(
                    array_keys($replacements),
                    array_values($replacements),
                    (string) $setting->value,
                );

                DB::table('platform_settings')
                    ->where('id', $setting->id)
                    ->update([
                        'value' => $value,
                        'updated_at' => now(),
                    ]);
            });

        Cache::forget('platform_settings');
    }
};

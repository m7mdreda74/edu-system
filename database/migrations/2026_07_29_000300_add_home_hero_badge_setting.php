<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')->insertOrIgnore([
            'key' => 'home_hero_badge',
            'value' => 'منصة التعليم الأولى في قطر',
            'type' => 'string',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('platform_settings');
    }

    public function down(): void
    {
        DB::table('platform_settings')->where('key', 'home_hero_badge')->delete();
    }
};

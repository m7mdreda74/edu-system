<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Replace only the known demo values; administrator settings are preserved.
        DB::table('platform_settings')->where('key', 'contact_phone')
            ->where('value', '+974 4444 8888')
            ->update(['value' => '+974 7015 9941', 'updated_at' => now()]);

        DB::table('platform_settings')->where('key', 'whatsapp_url')
            ->whereIn('value', ['https://wa.me/97455556666', 'https://wa.me/97455555555'])
            ->update(['value' => 'https://wa.me/97470159941', 'updated_at' => now()]);

        Cache::forget('platform_settings');
    }

    public function down(): void
    {
        // Do not restore demo contact details or overwrite later changes.
    }
};

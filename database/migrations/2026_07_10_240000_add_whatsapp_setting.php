<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')->insertOrIgnore([
            ['key' => 'whatsapp_url', 'value' => 'https://wa.me/97455556666', 'type' => 'string', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('platform_settings')->where('key', 'whatsapp_url')->delete();
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('platform_settings')->where('key', 'manual_payment_methods')->first();
        if ($setting && (str_contains((string) $setting->value, '01001234567') || str_contains((string) $setting->value, 'Vodafone Cash'))) {
            DB::table('platform_settings')->where('key', 'manual_payment_methods')->update([
                'value' => '[]',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Deliberately do not restore demo financial account data.
    }
};

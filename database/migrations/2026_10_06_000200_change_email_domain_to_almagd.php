<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceDomain('altafawwuq.com', 'almagd.com');
    }

    public function down(): void
    {
        $this->replaceDomain('almagd.com', 'altafawwuq.com');
    }

    private function replaceDomain(string $from, string $to): void
    {
        DB::table('platform_settings')
            ->select(['id', 'value'])
            ->orderBy('id')
            ->get()
            ->each(function (object $setting) use ($from, $to): void {
                $value = str_replace("@{$from}", "@{$to}", (string) $setting->value);

                if ($value === (string) $setting->value) {
                    return;
                }

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

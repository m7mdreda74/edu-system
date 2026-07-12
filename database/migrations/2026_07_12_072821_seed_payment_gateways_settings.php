<?php

use Illuminate\Database\Migrations\Migration;
use App\Domain\Settings\Models\PlatformSetting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            [
                'key'   => 'active_gateway',
                'value' => env('PAYMENT_GATEWAY', 'fatora'),
                'type'  => 'string',
            ],
            [
                'key'   => 'tap_publishable_key',
                'value' => '',
                'type'  => 'string',
            ],
            [
                'key'   => 'tap_secret_key',
                'value' => env('TAP_SECRET_KEY', ''),
                'type'  => 'string',
            ],
            [
                'key'   => 'fatora_api_key',
                'value' => env('FATORA_API_KEY', 'E4B73FEE-F492-4607-A38D-852B0EBC91C9'),
                'type'  => 'string',
            ],
        ];

        foreach ($settings as $s) {
            PlatformSetting::updateOrCreate(
                ['key' => $s['key']],
                [
                    'value' => $s['value'],
                    'type'  => $s['type'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        PlatformSetting::whereIn('key', [
            'active_gateway',
            'tap_publishable_key',
            'tap_secret_key',
            'fatora_api_key',
        ])->delete();
    }
};

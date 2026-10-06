<?php

declare(strict_types=1);

use Database\Seeders\AlMajdFreshSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Wipes old/demo platform data and re-seeds cleanly with Al-Majd platform data.
     */
    public function up(): void
    {
        $seeder = app(AlMajdFreshSeeder::class);
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data wipe & seed migration
    }
};

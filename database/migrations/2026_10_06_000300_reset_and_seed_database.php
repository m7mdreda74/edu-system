<?php

declare(strict_types=1);

use Database\Seeders\AccountsSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Wipes old/demo platform data and re-seeds cleanly with Al-Majd platform data.
     */
    public function up(): void
    {
        DatabaseSeeder::$forceAllow = true;
        AccountsSeeder::$forceAllow = true;

        $seeder = app(DatabaseSeeder::class);
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

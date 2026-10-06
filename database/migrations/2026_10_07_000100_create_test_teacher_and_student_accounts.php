<?php

declare(strict_types=1);

use Database\Seeders\TestAccountsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Seeds full teacher and enrolled student test accounts with assignments, schedules, and materials.
     */
    public function up(): void
    {
        $seeder = app(TestAccountsSeeder::class);
        $seeder->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive test accounts migration
    }
};

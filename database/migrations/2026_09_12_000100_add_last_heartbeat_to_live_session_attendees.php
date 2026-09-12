<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_session_attendees', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_session_attendees', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });
    }
};

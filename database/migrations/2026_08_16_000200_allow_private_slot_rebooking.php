<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_bookings', function (Blueprint $table): void {
            // Drop the FK first so MySQL allows dropping the unique index it depends on
            $table->dropForeign(['private_session_slot_id']);
            $table->dropUnique(['private_session_slot_id']);
            $table->index(['private_session_slot_id', 'status']);
            // Re-add the FK (now on a plain index, allowing multiple bookings per slot)
            $table->foreign('private_session_slot_id')
                  ->references('id')->on('private_session_slots')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('session_bookings', function (Blueprint $table): void {
            $table->dropForeign(['private_session_slot_id']);
            $table->dropIndex(['private_session_slot_id', 'status']);
            $table->unique('private_session_slot_id');
            $table->foreign('private_session_slot_id')
                  ->references('id')->on('private_session_slots')
                  ->cascadeOnDelete();
        });
    }
};

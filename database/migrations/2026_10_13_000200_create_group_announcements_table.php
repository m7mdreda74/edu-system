<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teaching_group_id')->constrained('teaching_groups')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index(['teaching_group_id', 'published_at']);
            $table->index(['teacher_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_announcements');
    }
};

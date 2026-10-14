<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_outcomes')) {
            return;
        }

        Schema::create('learning_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teaching_assignment_id')->constrained('teaching_assignments')->cascadeOnDelete();
            $table->foreignId('curriculum_unit_id')->constrained('curriculum_units')->cascadeOnDelete();
            $table->foreignId('group_material_id')->nullable()->constrained('group_materials')->nullOnDelete();
            $table->foreignId('quiz_id')->nullable()->constrained('quizzes')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order')->default(1);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(
                ['curriculum_unit_id', 'is_published', 'order'],
                'learning_outcomes_unit_published_order_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_outcomes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique()->index();
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedBigInteger('price')->default(0);           // stored in smallest currency unit (halala/cent)
            $table->unsignedBigInteger('discount_price')->nullable();   // stored in smallest currency unit
            $table->boolean('is_published')->default(false)->index();
            $table->string('grade_level', 20)->nullable()->index();    // grade_10, grade_11, grade_12, all
            $table->unsignedInteger('total_duration')->default(0);     // total duration in seconds
            $table->string('level', 20)->default('beginner');          // beginner, intermediate, advanced
            $table->unsignedSmallInteger('total_lessons')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'grade_level']);
            $table->index(['teacher_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

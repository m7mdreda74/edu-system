<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly pricing lives on what the student actually books.
 *
 * Groups carry their own monthly price (a Sunday 4pm group can differ from a
 * Tuesday 7pm one). Private sessions are priced per teaching assignment —
 * i.e. per teacher/subject/grade combination — because slots are individually
 * scheduled but sold as one monthly plan.
 *
 * All amounts are stored in the smallest currency unit (dirham of QAR),
 * matching how `payments.amount` has always been stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_groups', function (Blueprint $table): void {
            $table->unsignedBigInteger('monthly_price')->default(0)->after('capacity');
            $table->string('currency', 3)->default('QAR')->after('monthly_price');
        });

        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('private_monthly_price')->default(0)->after('grade_level_id');
            $table->string('currency', 3)->default('QAR')->after('private_monthly_price');
            $table->boolean('accepts_private')->default(true)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->dropColumn(['private_monthly_price', 'currency', 'accepts_private']);
        });

        Schema::table('teaching_groups', function (Blueprint $table): void {
            $table->dropColumn(['monthly_price', 'currency']);
        });
    }
};

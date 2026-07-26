<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Qatari school year runs in two semesters, and teaching is organised
 * around them — a group is offered for a term, and its material belongs to that
 * term's syllabus.
 *
 * Dates for 2025/2026 are the ministry's published calendar. The 2026/2027 year
 * was approved at the same time but only its start date has been published in
 * detail, so the rest is marked provisional for an admin to confirm.
 *
 * @see https://edu.gov.qa/ar/Content/AnnualCalendar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('year_label', 20);          // "2025/2026"
            $table->unsignedTinyInteger('term_number'); // 1 or 2
            $table->string('name');                     // "الفصل الدراسي الأول"
            $table->date('starts_on');
            $table->date('ends_on');
            // Provisional dates are the ministry's outline rather than its
            // published calendar; the admin confirms them when it lands.
            $table->boolean('is_provisional')->default(false);
            $table->timestamps();

            $table->unique(['year_label', 'term_number']);
            $table->index(['starts_on', 'ends_on']);
        });

        // A group runs for a term; leaving it null means "ongoing, not tied to
        // one semester", which is how private tuition tends to work.
        Schema::table('teaching_groups', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')->nullable()->after('teaching_assignment_id')
                ->constrained('academic_terms')->nullOnDelete();
        });

        Schema::table('group_materials', function (Blueprint $table): void {
            $table->foreignId('academic_term_id')->nullable()->after('teaching_group_id')
                ->constrained('academic_terms')->nullOnDelete();
        });

        $this->seedTerms();
    }

    public function down(): void
    {
        Schema::table('group_materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_term_id');
        });

        Schema::table('teaching_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_term_id');
        });

        Schema::dropIfExists('academic_terms');
    }

    private function seedTerms(): void
    {
        $terms = [
            // Published calendar.
            ['2025/2026', 1, 'الفصل الدراسي الأول', '2025-08-31', '2025-12-28', false],
            ['2025/2026', 2, 'الفصل الدراسي الثاني', '2026-01-12', '2026-06-23', false],

            // Approved for the same three-year cycle; only the start of the
            // first semester has been published precisely so far.
            ['2026/2027', 1, 'الفصل الدراسي الأول', '2026-08-30', '2026-12-17', true],
            ['2026/2027', 2, 'الفصل الدراسي الثاني', '2027-01-10', '2027-06-10', true],
        ];

        foreach ($terms as [$year, $number, $name, $start, $end, $provisional]) {
            DB::table('academic_terms')->insert([
                'year_label'     => $year,
                'term_number'    => $number,
                'name'           => $name,
                'starts_on'      => $start,
                'ends_on'        => $end,
                'is_provisional' => $provisional,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }
    }
};

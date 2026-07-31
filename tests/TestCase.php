<?php

namespace Tests;

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\CurriculumUnit;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Live\Models\LiveSession;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Base test case for feature and unit tests.
 *
 * Public properties are declared here so that Intelephense resolves $this
 * inside Pest closures. Each test file must also call uses(TestCase::class)
 * at the top of the file for IDE support.
 */
abstract class TestCase extends BaseTestCase
{
    // ─── Common Pest beforeEach shared-state properties ──────────────────────
    // Declared public so that IDE static analysis (Intelephense, PHPStan)
    // can resolve $this->admin etc. inside every it() / beforeEach() closure.

    public User               $admin;
    public User               $teacher;
    public User               $student;
    public User               $parent;
    public TeachingAssignment $assignment;
    public TeachingGroup      $group;
    public AcademicTerm       $term;
    public Subscription       $subscription;
    public LiveSession        $session;
    public CurriculumUnit     $unit;
    public GradeLevel         $grade;
    public Subject            $subject;
    public Subject            $maths;

    // ─────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests create users with application roles directly. Keep the
        // role catalog present after RefreshDatabase resets the test schema.
        if (Schema::hasTable('roles')) {
            foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }
        }
    }
}

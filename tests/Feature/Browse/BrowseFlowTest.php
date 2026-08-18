<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Scheduling\Models\PrivateSessionSlot;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class BrowseTestCase extends TestCase
{
    public GradeLevel $grade;
    public Subject $subject;
    public User $teacher;
    public TeachingAssignment $assignment;
    public TeachingGroup $group;
}

// Binds $this inside every Pest closure in this file to BrowseTestCase.
uses(BrowseTestCase::class, RefreshDatabase::class);

// ─── Public browse flow: grade → subject → teachers → profile ────────────────

beforeEach(function () {
    /** @var BrowseTestCase $this */
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    // The grade_levels migration ships grade_10/11/12, so reuse rather than insert.
    $this->grade = GradeLevel::where('key', 'grade_12_science')->firstOrFail();
    $this->subject = Subject::where('name', 'الرياضيات')->firstOrFail();

    $this->teacher = User::factory()->create([
        'name' => 'أ. أحمد',
        'is_active' => true,
        'headline' => 'معلم رياضيات',
        'intro_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'years_experience' => 10,
    ]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subject->id,
        'grade_level_id' => $this->grade->id,
        'is_active' => true,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
        'monthly_price' => 45_000,
        'capacity' => 10,
    ]);
});

it('shows every stage of the Qatari system on the home page', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(function ($page) {
    /** @var BrowseTestCase $this */
            $page->component('Public/Home');

            $stages = collect($page->toArray()['props']['grades'])->pluck('stage')->unique();

            expect($stages)->toContain('primary', 'preparatory', 'secondary');
        });
});

it('provides a full grade directory for the home page preview link', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('grades.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Public/Grades');

            $stages = collect($page->toArray()['props']['grades'])->pluck('stage')->unique();

            expect($stages)->toContain('primary', 'preparatory', 'secondary');
        });
});

it('does not return statistics to the home page', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('stats'));
});

it('renders hero copy from platform settings so the admin can change it', function () {
    /** @var BrowseTestCase $this */
    PlatformSetting::updateOrCreate(
        ['key' => 'home_hero_badge'],
        ['value' => 'شارتنا المحدثة', 'type' => 'string'],
    );

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('settings.home_hero_badge', 'شارتنا المحدثة'));
});

it('provides a dedicated teacher directory from the public navigation', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('teachers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Teachers')
            ->has('teachers', 1)
            ->where('teachers.0.id', $this->teacher->id)
            ->where('teachers.0.subjects.0', $this->subject->name));
});

it('splits grades eleven and twelve into the three Qatari tracks', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(function ($page) {
    /** @var BrowseTestCase $this */
            $tracked = collect($page->toArray()['props']['grades'])
                ->whereNotNull('track')
                ->pluck('key');

            expect($tracked)->toContain(
                'grade_11_science',
                'grade_11_arts',
                'grade_11_technology',
                'grade_12_science',
                'grade_12_arts',
                'grade_12_technology',
            );
        });
});

it('lists the whole curriculum for a grade, teachers or not', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('grades.show', ['key' => 'grade_12_science']))
        ->assertOk()
        ->assertInertia(function ($page) {
    /** @var BrowseTestCase $this */
            $subjects = collect($page->toArray()['props']['subjects']);

            // The science track carries physics whether or not it is staffed.
            expect($subjects->pluck('name'))->toContain('الفيزياء')
                // And the test's own subject, which has a teacher.
                ->toContain('الرياضيات');

            expect($subjects->firstWhere('name', 'الرياضيات')['teachers_count'])->toBe(1);
        });
});

it('marks curriculum subjects with no teacher as unstaffed', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('grades.show', ['key' => 'grade_12_arts']))
        ->assertOk()
        ->assertInertia(function ($page) {
    /** @var BrowseTestCase $this */
            $subjects = collect($page->toArray()['props']['subjects']);

            expect($subjects)->not->toBeEmpty()
                ->and($subjects->every(fn ($s) => $s['teachers_count'] === 0))->toBeTrue();
        });
});

it('offers the sibling track when viewing a tracked grade', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('grades.show', ['key' => 'grade_12_science']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('grade.track', 'science')
            ->where('grade.track_label', 'المسار العلمي')
            // Two siblings now: arts and technology.
            ->has('siblingTracks', 2));
});

it('lists the teachers who teach a subject with their intro video', function () {
    /** @var BrowseTestCase $this */
    $this->get(route('subjects.teachers', ['gradeKey' => 'grade_12_science', 'subject' => $this->subject->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/SubjectTeachers')
            ->has('teachers', 1)
            ->where('teachers.0.id', $this->teacher->id)
            ->where('teachers.0.intro_video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->where('teachers.0.cheapest_monthly', 45_000)
            ->where('teachers.0.has_free_seats', true));
});

it('loads teacher ratings in one query regardless of teacher count', function () {
    /** @var BrowseTestCase $this */
    User::factory()->count(7)->create(['is_active' => true])->each(function (User $teacher): void {
        $teacher->assignRole('teacher');
        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $this->subject->id,
            'grade_level_id' => $this->grade->id,
            'is_active' => true,
        ]);
    });

    $reviewQueries = [];
    DB::listen(function ($query) use (&$reviewQueries): void {
        if (str_contains($query->sql, 'reviews')) {
            $reviewQueries[] = $query->sql;
        }
    });

    $this->get(route('subjects.teachers', [
        'gradeKey' => $this->grade->key,
        'subject' => $this->subject->id,
    ]))->assertOk();

    expect($reviewQueries)->toHaveCount(1);
});

it('shows a teacher profile with their groups and prices', function () {
    /** @var BrowseTestCase $this */
    $this->teacher->update(['profile_cover' => '/storage/teacher-covers/demo.webp']);

    $this->get(route('teachers.show', $this->teacher->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/TeacherProfile')
            ->where('teacher.name', 'أ. أحمد')
            ->where('teacher.profile_cover', '/storage/teacher-covers/demo.webp')
            ->has('assignments', 1)
            ->has('assignments.0.groups', 1)
            ->where('assignments.0.groups.0.monthly_price', 45_000)
            ->where('assignments.0.groups.0.seats_left', 10));
});

it('keeps teacher profile tabs scoped to each grade assignment', function () {
    /** @var BrowseTestCase $this */
    $otherGrade = GradeLevel::where('key', 'grade_11_science')->firstOrFail();
    $otherAssignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => $this->subject->id,
        'grade_level_id' => $otherGrade->id,
        'private_monthly_price' => 120_000,
        'is_active' => true,
    ]);

    TeachingGroup::factory()->create([
        'teaching_assignment_id' => $otherAssignment->id,
        'monthly_price' => 70_000,
        'capacity' => 8,
    ]);

    $otherFreeSlot = PrivateSessionSlot::create([
        'teaching_assignment_id' => $otherAssignment->id,
        'starts_at' => now()->addDays(2)->setTime(16, 0),
        'ends_at' => now()->addDays(2)->setTime(17, 0),
        'timezone' => 'Asia/Qatar',
        'is_free_intro' => true,
        'status' => 'available',
    ]);

    $this->get(route('teachers.show', $this->teacher->id))
        ->assertOk()
        ->assertInertia(function ($page) use ($otherFreeSlot, $otherGrade): void {
            $assignments = collect($page->toArray()['props']['assignments']);
            $current = $assignments->firstWhere('grade.key', $this->grade->key);
            $other = $assignments->firstWhere('grade.key', $otherGrade->key);

            expect($current['private_monthly_price'])->toBe(90_000)
                ->and($current['groups'][0]['monthly_price'])->toBe(45_000)
                ->and($other['private_monthly_price'])->toBe(120_000)
                ->and($other['groups'][0]['monthly_price'])->toBe(70_000)
                ->and(collect($other['free_intro_slots'])->pluck('id'))->toContain($otherFreeSlot->id);
        });
});

it('does not show inactive teachers', function () {
    /** @var BrowseTestCase $this */
    $this->teacher->update(['is_active' => false]);

    $this->get(route('teachers.show', $this->teacher->id))->assertNotFound();
});

it('has no route named after courses any more', function () {
    /** @var BrowseTestCase $this */
    $names = collect(app('router')->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter();

    expect($names->filter(fn ($name) => str_contains($name, 'course')))->toBeEmpty();
});

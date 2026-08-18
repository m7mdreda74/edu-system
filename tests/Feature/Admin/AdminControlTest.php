<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Scheduling\Models\TeachingGroupSchedule;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class AdminControlTestCase extends TestCase
{
    public User $admin;
    public User $teacher;
    public User $student;
    public TeachingAssignment $assignment;
    public TeachingGroup $group;
}

// Binds $this inside every Pest closure in this file to Tests\TestCase.
// Required for Intelephense to resolve $this->admin / actingAs() / get() etc.
uses(AdminControlTestCase::class, RefreshDatabase::class);

// ─── Everything the admin must be able to reach and change ───────────────────

beforeEach(function () {
    /** @var AdminControlTestCase $this */
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['is_active' => true]);
    $this->teacher->assignRole('teacher');

    $this->student = User::factory()->create();
    $this->student->assignRole('student');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::where('name', 'الرياضيات')->value('id'),
        'grade_level_id' => GradeLevel::where('key', 'grade_12_science')->value('id'),
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);
});

it('reaches every admin screen', function () {
    /** @var AdminControlTestCase $this */
    $screens = [
        'admin.dashboard', 'admin.users', 'admin.subjects', 'admin.grade-levels',
        'admin.academic-terms', 'admin.teaching-groups', 'admin.subscriptions',
        'admin.session-apologies', 'admin.reports', 'admin.reviews', 'admin.payments', 'admin.payouts', 'admin.coupons',
        'admin.settings', 'admin.site-pages',
    ];

    foreach ($screens as $screen) {
        $this->actingAs($this->admin)->get(route($screen))->assertOk();
    }
});

it('prints the complete admin report datasets without pagination', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->get(route('admin.reports', ['print' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('printMode', true)
            ->where('sessions.meta.current_page', 1)
            ->where('sessions.meta.total', 0)
            ->where('attendance.meta.current_page', 1)
            ->where('attendance.meta.total', 0)
            ->has('teachers', 1)
            ->has('groups', 1));
});

it('treats stale active subscriptions as expired in admin screens', function () {
    /** @var AdminControlTestCase $this */
    $stale = Subscription::factory()->active()->create([
        'student_id' => $this->student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
        'period_end' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.subscriptions', ['status' => 'expired']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subscriptions.total', 1)
            ->where('subscriptions.data.0.id', $stale->id)
            ->where('subscriptions.data.0.status', Subscription::STATUS_EXPIRED)
            ->where('stats.active', 0)
            ->where('stats.expired', 1));
});

it('keeps every admin screen away from non-admins', function () {
    /** @var AdminControlTestCase $this */
    foreach ([$this->teacher, $this->student] as $user) {
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.reviews'))->assertForbidden();
    }
});

it('lets the admin assign a new password without exposing the old one', function () {
    /** @var AdminControlTestCase $this */
    $oldHash = $this->teacher->password;

    $this->actingAs($this->admin)
        ->patch(route('admin.users.password', ['id' => $this->teacher->id]), [
            'password' => 'teacher-new-password',
            'password_confirmation' => 'teacher-new-password',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertNotSame($oldHash, $this->teacher->refresh()->password);
    $this->assertTrue(Hash::check('teacher-new-password', $this->teacher->password));
});

// ─── Review moderation — a rating is invisible until it is approved ──────────

it('publishes a review only once the admin approves it', function () {
    /** @var AdminControlTestCase $this */
    $review = TeacherReview::create([
        'user_id' => $this->student->id,
        'teacher_id' => $this->teacher->id,
        'rating' => 5,
        'comment' => 'شرح ممتاز',
        'is_approved' => false,
    ]);

    // Not on the teacher's public profile yet.
    $this->get(route('teachers.show', $this->teacher->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('reviews', 0));

    $this->actingAs($this->admin)
        ->patch(route('admin.reviews.approve', ['id' => $review->id]))
        ->assertRedirect();

    $this->get(route('teachers.show', $this->teacher->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('reviews', 1));
});

it('can hide an approved review again', function () {
    /** @var AdminControlTestCase $this */
    $review = TeacherReview::create([
        'user_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'rating' => 4, 'is_approved' => true,
    ]);

    $this->actingAs($this->admin)->patch(route('admin.reviews.reject', ['id' => $review->id]));

    expect($review->fresh()->is_approved)->toBeFalse();
});

it('clears the whole moderation backlog in one go', function () {
    /** @var AdminControlTestCase $this */
    foreach (range(1, 3) as $i) {
        $student = User::factory()->create();
        $student->assignRole('student');

        TeacherReview::create([
            'user_id' => $student->id, 'teacher_id' => $this->teacher->id,
            'rating' => 5, 'is_approved' => false,
        ]);
    }

    $this->actingAs($this->admin)->post(route('admin.reviews.approve-all'));

    expect(TeacherReview::where('is_approved', false)->count())->toBe(0);
});

// ─── Academic terms ─────────────────────────────────────────────────────────

it('adds an academic term', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)->post(route('admin.academic-terms.store'), [
        'year_label' => '2027/2028',
        'term_number' => 1,
        'name' => 'الفصل الدراسي الأول',
        'starts_on' => '2027-08-29',
        'ends_on' => '2027-12-16',
    ])->assertRedirect();

    expect(AcademicTerm::where('year_label', '2027/2028')->exists())->toBeTrue();
});

it('rejects a term that ends before it starts', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)->post(route('admin.academic-terms.store'), [
        'year_label' => '2027/2028',
        'term_number' => 2,
        'name' => 'الفصل الدراسي الثاني',
        'starts_on' => '2028-01-10',
        'ends_on' => '2027-12-01',
    ])->assertSessionHasErrors('ends_on');
});

it('refuses to delete a term that groups are attached to', function () {
    /** @var AdminControlTestCase $this */
    $term = AcademicTerm::first();
    $this->group->update(['academic_term_id' => $term->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.academic-terms.destroy', ['id' => $term->id]))
        ->assertSessionHas('error');

    expect(AcademicTerm::find($term->id))->not->toBeNull();
});

// ─── Teaching group oversight ───────────────────────────────────────────────

it('takes a group offline without touching its teacher', function () {
    /** @var AdminControlTestCase $this */
    expect($this->group->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('admin.teaching-groups.toggle', ['id' => $this->group->id]))
        ->assertRedirect();

    expect($this->group->fresh()->is_active)->toBeFalse()
        ->and($this->teacher->fresh()->is_active)->toBeTrue();
});

it('gives group and private pricing to the admin only', function () {
    /** @var AdminControlTestCase $this */
    $payload = [
        'name' => $this->group->name,
        'capacity' => $this->group->capacity,
        'monthly_price_qar' => 725.50,
        'academic_term_id' => $this->group->academic_term_id,
        'is_active' => true,
    ];

    $this->actingAs($this->teacher)
        ->put(route('admin.teaching-groups.update', $this->group->id), $payload)
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->put(route('admin.teaching-groups.update', $this->group->id), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->patch(route('admin.teaching-assignments.update', $this->assignment->id), [
            'accepts_private' => true,
            'private_monthly_price_qar' => 950,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->group->fresh()->monthly_price)->toBe(72_550)
        ->and($this->assignment->fresh()->private_monthly_price)->toBe(95_000)
        ->and($this->assignment->fresh()->accepts_private)->toBeTrue()
        ->and(app('router')->has('teacher.teaching-schedule.groups.store'))->toBeFalse()
        ->and(app('router')->has('teacher.teaching-schedule.private-slots.store'))->toBeFalse()
        ->and(app('router')->has('teacher.payouts'))->toBeFalse();
});

it('requires private pricing to stay above every group price', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->patch(route('admin.teaching-assignments.update', $this->assignment->id), [
            'accepts_private' => true,
            'private_monthly_price_qar' => 400,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('private_monthly_price_qar');

    $this->actingAs($this->admin)
        ->put(route('admin.teaching-groups.update', $this->group->id), [
            'name' => $this->group->name,
            'capacity' => $this->group->capacity,
            'monthly_price_qar' => 900,
            'academic_term_id' => $this->group->academic_term_id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('monthly_price_qar');
});

it('keeps pricing and capacity with admin while the teacher owns schedules', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->post(route('admin.teaching-groups.store'), [
            'teaching_assignment_id' => $this->assignment->id,
            'academic_term_id' => AcademicTerm::first()->id,
            'name' => 'مجموعة إدارية بلا موعد',
            'capacity' => 12,
            'monthly_price_qar' => 250,
            'timezone' => 'Asia/Qatar',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $group = TeachingGroup::where('name', 'مجموعة إدارية بلا موعد')->firstOrFail();

    expect($group->schedules()->count())->toBe(0)
        ->and($group->capacity)->toBe(12)
        ->and($group->monthly_price)->toBe(25_000)
        ->and(app('router')->has('admin.private-slots.store'))->toBeFalse()
        ->and(app('router')->has('admin.private-slots.destroy'))->toBeFalse();

    $payload = [
        'day_of_week' => 2,
        'start_time' => '17:00',
        'end_time' => '18:30',
    ];

    $this->actingAs($this->admin)
        ->post(route('teacher.teaching-schedule.groups.schedules.store', $group->id), $payload)
        ->assertForbidden();

    expect(app('router')->has('teacher.teaching-schedule.groups.capacity'))->toBeFalse();

    $this->actingAs($this->admin)
        ->put(route('admin.teaching-groups.update', $group->id), [
            'name' => $group->name,
            'capacity' => 18,
            'monthly_price_qar' => 250,
            'academic_term_id' => $group->academic_term_id,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->teacher)
        ->post(route('teacher.teaching-schedule.groups.schedules.store', $group->id), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule = TeachingGroupSchedule::where('teaching_group_id', $group->id)->firstOrFail();

    expect($schedule->day_of_week)->toBe(2)
        ->and($group->fresh()->capacity)->toBe(18)
        ->and(substr((string) $schedule->start_time, 0, 5))->toBe('17:00')
        ->and($schedule->duration_minutes)->toBe(90);
});

it('defaults a newly created group to five students when capacity is omitted', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->post(route('admin.teaching-groups.store'), [
            'teaching_assignment_id' => $this->assignment->id,
            'name' => 'مجموعة بالسعة الافتراضية',
            'monthly_price_qar' => 250,
            'timezone' => 'Asia/Qatar',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(TeachingGroup::where('name', 'مجموعة بالسعة الافتراضية')->value('capacity'))->toBe(5);
});

it('keeps the teacher dashboard academic only', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Teacher/Dashboard')
            ->has('stats.assignments')
            ->has('stats.lessons')
            ->missing('stats.total_revenue')
            ->missing('recentSubscriptions'));
});

it('shows who is inside a group', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->get(route('admin.teaching-groups.show', ['id' => $this->group->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/TeachingGroupShow')
            ->where('group.id', $this->group->id)
            ->has('subscriptions'));
});

// ─── Live dashboard figures ─────────────────────────────────────────────────

it('serves dashboard figures as json for polling', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->getJson(route('admin.dashboard.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'stats' => [
                'students', 'teachers', 'groups', 'revenue_total', 'mrr',
                'needs_action' => ['payment_receipts', 'pending_reviews', 'pending_payouts'],
            ],
            'fetchedAt',
        ]);
});

it('loads every live dashboard figure in one database round trip', function () {
    /** @var AdminControlTestCase $this */
    $statsQueries = [];
    DB::listen(function ($query) use (&$statsQueries): void {
        if (str_contains($query->sql, 'groups_count')) {
            $statsQueries[] = $query->sql;
        }
    });

    $this->actingAs($this->admin)
        ->getJson(route('admin.dashboard.stats'))
        ->assertOk();

    expect($statsQueries)->toHaveCount(1);
});

it('keeps zero-value months in the six-month revenue chart', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('revenueChart', fn ($chart) => count($chart) === 6
                && collect($chart)->every(fn ($month) => $month['amount'] === 0 && $month['payments'] === 0)));
});

it('counts a pending review in the action queue', function () {
    /** @var AdminControlTestCase $this */
    TeacherReview::create([
        'user_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'rating' => 5, 'is_approved' => false,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.stats'));

    expect($response->json('stats.needs_action.pending_reviews'))->toBe(1);
});

// ─── Teacher photos belong to the platform, not the teacher ─────────────────

it('refuses a teacher uploading their own photo', function () {
    /** @var AdminControlTestCase $this */
    Storage::fake('public');

    $this->actingAs($this->teacher)
        ->patch(route('profile.update'), [
            'name' => $this->teacher->name,
            'email' => $this->teacher->email,
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($this->teacher->fresh()->avatar)->toBeNull();
});

it('still lets a student set their own photo', function () {
    /** @var AdminControlTestCase $this */
    Storage::fake('public');

    $this->actingAs($this->student)
        ->patch(route('profile.update'), [
            'name' => $this->student->name,
            'email' => $this->student->email,
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
        ])
        ->assertSessionHasNoErrors();

    expect($this->student->fresh()->avatar)->not->toBeNull();
});

it('lets a teacher edit the rest of their profile', function () {
    /** @var AdminControlTestCase $this */
    $this->actingAs($this->teacher)
        ->patch(route('profile.update'), [
            'name' => $this->teacher->name,
            'email' => $this->teacher->email,
            'headline' => 'معلم رياضيات',
            'intro_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertSessionHasNoErrors();

    expect($this->teacher->fresh()->headline)->toBe('معلم رياضيات');
});

it('lets an admin set and clear a teacher photo', function () {
    /** @var AdminControlTestCase $this */
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.users.avatar', ['id' => $this->teacher->id]), [
            'avatar' => UploadedFile::fake()->image('teacher.jpg', 400, 400),
        ])
        ->assertRedirect();

    expect($this->teacher->fresh()->avatar)->not->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.avatar.delete', ['id' => $this->teacher->id]))
        ->assertRedirect();

    expect($this->teacher->fresh()->avatar)->toBeNull();
});

it('manages photos for teachers only', function () {
    /** @var AdminControlTestCase $this */
    Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.users.avatar', ['id' => $this->student->id]), [
            'avatar' => UploadedFile::fake()->image('x.jpg'),
        ])
        ->assertStatus(422);
});

it('lets an admin set and clear a subject image', function () {
    /** @var AdminControlTestCase $this */
    Storage::fake('public');

    $subject = $this->assignment->subject;
    $gradeIds = $subject->gradeLevels()->pluck('grade_levels.id')->all();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.update', ['id' => $subject->id]), [
            '_method' => 'put',
            'name' => $subject->name,
            'name_en' => $subject->name_en,
            'icon' => $subject->icon,
            'is_active' => true,
            'grade_level_ids' => $gradeIds,
            'image' => UploadedFile::fake()->image('math.jpg', 400, 400),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $path = $subject->fresh()->image;
    expect($path)->not->toBeNull();
    expect(Storage::disk('public')->exists(substr($path, strlen('/storage/'))))->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.subjects.update', ['id' => $subject->id]), [
            '_method' => 'put',
            'name' => $subject->name,
            'name_en' => $subject->name_en,
            'icon' => $subject->icon,
            'is_active' => true,
            'grade_level_ids' => $gradeIds,
            'remove_image' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($subject->fresh()->image)->toBeNull();
});

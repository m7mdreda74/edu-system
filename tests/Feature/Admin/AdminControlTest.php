<?php

declare(strict_types=1);

use App\Domain\Academic\Models\AcademicTerm;
use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\TeacherReview;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\User\Models\User;
use Spatie\Permission\Models\Role;

// ─── Everything the admin must be able to reach and change ───────────────────

beforeEach(function () {
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
        'teacher_id'     => $this->teacher->id,
        'subject_id'     => Subject::where('name', 'الرياضيات')->value('id'),
        'grade_level_id' => GradeLevel::where('key', 'grade_12_science')->value('id'),
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);
});

it('reaches every admin screen', function () {
    $screens = [
        'admin.dashboard', 'admin.users', 'admin.subjects', 'admin.grade-levels',
        'admin.academic-terms', 'admin.teaching-groups', 'admin.subscriptions',
        'admin.reviews', 'admin.payments', 'admin.payouts', 'admin.coupons',
        'admin.settings', 'admin.site-pages',
    ];

    foreach ($screens as $screen) {
        $this->actingAs($this->admin)->get(route($screen))->assertOk();
    }
});

it('keeps every admin screen away from non-admins', function () {
    foreach ([$this->teacher, $this->student] as $user) {
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.reviews'))->assertForbidden();
    }
});

// ─── Review moderation — a rating is invisible until it is approved ──────────

it('publishes a review only once the admin approves it', function () {
    $review = TeacherReview::create([
        'user_id'     => $this->student->id,
        'teacher_id'  => $this->teacher->id,
        'rating'      => 5,
        'comment'     => 'شرح ممتاز',
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
    $review = TeacherReview::create([
        'user_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'rating'  => 4, 'is_approved' => true,
    ]);

    $this->actingAs($this->admin)->patch(route('admin.reviews.reject', ['id' => $review->id]));

    expect($review->fresh()->is_approved)->toBeFalse();
});

it('clears the whole moderation backlog in one go', function () {
    foreach (range(1, 3) as $i) {
        $student = User::factory()->create();
        $student->assignRole('student');

        TeacherReview::create([
            'user_id' => $student->id, 'teacher_id' => $this->teacher->id,
            'rating'  => 5, 'is_approved' => false,
        ]);
    }

    $this->actingAs($this->admin)->post(route('admin.reviews.approve-all'));

    expect(TeacherReview::where('is_approved', false)->count())->toBe(0);
});

// ─── Academic terms ─────────────────────────────────────────────────────────

it('adds an academic term', function () {
    $this->actingAs($this->admin)->post(route('admin.academic-terms.store'), [
        'year_label'  => '2027/2028',
        'term_number' => 1,
        'name'        => 'الفصل الدراسي الأول',
        'starts_on'   => '2027-08-29',
        'ends_on'     => '2027-12-16',
    ])->assertRedirect();

    expect(AcademicTerm::where('year_label', '2027/2028')->exists())->toBeTrue();
});

it('rejects a term that ends before it starts', function () {
    $this->actingAs($this->admin)->post(route('admin.academic-terms.store'), [
        'year_label'  => '2027/2028',
        'term_number' => 2,
        'name'        => 'الفصل الدراسي الثاني',
        'starts_on'   => '2028-01-10',
        'ends_on'     => '2027-12-01',
    ])->assertSessionHasErrors('ends_on');
});

it('refuses to delete a term that groups are attached to', function () {
    $term = AcademicTerm::first();
    $this->group->update(['academic_term_id' => $term->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.academic-terms.destroy', ['id' => $term->id]))
        ->assertSessionHas('error');

    expect(AcademicTerm::find($term->id))->not->toBeNull();
});

// ─── Teaching group oversight ───────────────────────────────────────────────

it('takes a group offline without touching its teacher', function () {
    expect($this->group->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('admin.teaching-groups.toggle', ['id' => $this->group->id]))
        ->assertRedirect();

    expect($this->group->fresh()->is_active)->toBeFalse()
        ->and($this->teacher->fresh()->is_active)->toBeTrue();
});

it('shows who is inside a group', function () {
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

it('counts a pending review in the action queue', function () {
    TeacherReview::create([
        'user_id' => $this->student->id, 'teacher_id' => $this->teacher->id,
        'rating'  => 5, 'is_approved' => false,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('admin.dashboard.stats'));

    expect($response->json('stats.needs_action.pending_reviews'))->toBe(1);
});

// ─── Teacher photos belong to the platform, not the teacher ─────────────────

it('refuses a teacher uploading their own photo', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($this->teacher)
        ->patch(route('profile.update'), [
            'name'   => $this->teacher->name,
            'email'  => $this->teacher->email,
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('me.jpg'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($this->teacher->fresh()->avatar)->toBeNull();
});

it('still lets a student set their own photo', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($this->student)
        ->patch(route('profile.update'), [
            'name'   => $this->student->name,
            'email'  => $this->student->email,
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('me.jpg', 200, 200),
        ])
        ->assertSessionHasNoErrors();

    expect($this->student->fresh()->avatar)->not->toBeNull();
});

it('lets a teacher edit the rest of their profile', function () {
    $this->actingAs($this->teacher)
        ->patch(route('profile.update'), [
            'name'            => $this->teacher->name,
            'email'           => $this->teacher->email,
            'headline'        => 'معلم رياضيات',
            'intro_video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertSessionHasNoErrors();

    expect($this->teacher->fresh()->headline)->toBe('معلم رياضيات');
});

it('lets an admin set and clear a teacher photo', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.users.avatar', ['id' => $this->teacher->id]), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('teacher.jpg', 400, 400),
        ])
        ->assertRedirect();

    expect($this->teacher->fresh()->avatar)->not->toBeNull();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.avatar.delete', ['id' => $this->teacher->id]))
        ->assertRedirect();

    expect($this->teacher->fresh()->avatar)->toBeNull();
});

it('manages photos for teachers only', function () {
    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($this->admin)
        ->post(route('admin.users.avatar', ['id' => $this->student->id]), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('x.jpg'),
        ])
        ->assertStatus(422);
});

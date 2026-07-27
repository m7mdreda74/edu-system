<?php

declare(strict_types=1);

use App\Domain\Academic\Models\GradeLevel;
use App\Domain\Academic\Models\Subject;
use App\Domain\Learning\Models\LiveSession;
use App\Domain\Learning\Models\LiveSessionApology;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Models\TeacherPayout;
use App\Domain\Scheduling\Models\TeachingAssignment;
use App\Domain\Scheduling\Models\TeachingGroup;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'teacher', 'student', 'parent'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->teacher = User::factory()->create(['email_verified_at' => now()]);
    $this->teacher->assignRole('teacher');

    $this->assignment = TeachingAssignment::factory()->create([
        'teacher_id' => $this->teacher->id,
        'subject_id' => Subject::query()->firstOrFail()->id,
        'grade_level_id' => GradeLevel::query()->firstOrFail()->id,
    ]);

    $this->group = TeachingGroup::factory()->create([
        'teaching_assignment_id' => $this->assignment->id,
    ]);

    $this->session = LiveSession::create([
        'teacher_id' => $this->teacher->id,
        'teaching_group_id' => $this->group->id,
        'title' => 'شرح الوحدة الأولى',
        'scheduled_at' => now()->addDay(),
        'status' => LiveSession::STATUS_SCHEDULED,
    ]);
});

it('lets a teacher apologize with a reason instead of deleting a scheduled class', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.live-sessions.apologize', $this->session->id), [
            'reason' => 'تعرضت لظرف صحي طارئ ولن أستطيع تقديم الحصة.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->session->fresh()->status)->toBe(LiveSession::STATUS_CANCELLED)
        ->and($this->session->fresh()->apology->reason)->toContain('ظرف صحي')
        ->and($this->session->fresh()->apology->status)->toBe(LiveSessionApology::STATUS_PENDING)
        ->and(app('router')->has('teacher.live-sessions.destroy'))->toBeFalse();
});

it('prevents another teacher from apologizing for the class', function () {
    $other = User::factory()->create(['email_verified_at' => now()]);
    $other->assignRole('teacher');

    $this->actingAs($other)
        ->post(route('teacher.live-sessions.apologize', $this->session->id), [
            'reason' => 'محاولة اعتذار غير مصرح بها للحصة الحالية.',
        ])
        ->assertForbidden();
});

it('lets the teacher close a pending apology with a makeup class', function () {
    $this->actingAs($this->teacher)->post(route('teacher.live-sessions.apologize', $this->session->id), [
        'reason' => 'حدث عطل مفاجئ في الاتصال قبل بدء الحصة.',
    ]);

    $apology = LiveSessionApology::firstOrFail();
    $makeupAt = now()->addDays(2)->setSecond(0);

    $this->actingAs($this->teacher)
        ->post(route('teacher.session-apologies.makeup', $apology->id), [
            'scheduled_at' => $makeupAt->toDateTimeString(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $apology->refresh();

    expect($apology->status)->toBe(LiveSessionApology::STATUS_MAKEUP_SCHEDULED)
        ->and($apology->deduction_amount)->toBe(0)
        ->and($apology->makeupSession)->not->toBeNull()
        ->and($apology->makeupSession->teaching_group_id)->toBe($this->group->id)
        ->and($apology->makeupSession->title)->toContain('حصة تعويضية');

    $this->actingAs($this->admin)
        ->post(route('admin.session-apologies.deduct', $apology->id), [
            'amount_qar' => 100,
        ])
        ->assertSessionHasErrors('amount_qar');
});

it('allows only the admin to record a deduction', function () {
    $this->actingAs($this->teacher)->post(route('teacher.live-sessions.apologize', $this->session->id), [
        'reason' => 'لن أتمكن من الحضور بسبب ظرف طارئ خارج الإرادة.',
    ]);
    $apology = LiveSessionApology::firstOrFail();

    $this->actingAs($this->teacher)
        ->post(route('admin.session-apologies.deduct', $apology->id), ['amount_qar' => 125.50])
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->post(route('admin.session-apologies.deduct', $apology->id), [
            'amount_qar' => 125.50,
            'admin_note' => 'خصم حصة لم يتم تعويضها.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($apology->fresh()->status)->toBe(LiveSessionApology::STATUS_DEDUCTED)
        ->and($apology->fresh()->deduction_amount)->toBe(12_550)
        ->and($apology->fresh()->resolved_by)->toBe($this->admin->id);
});

it('applies the deduction exactly once to the next payout', function () {
    $this->actingAs($this->teacher)->post(route('teacher.live-sessions.apologize', $this->session->id), [
        'reason' => 'تعذر تقديم الحصة ولم يتم الاتفاق على موعد بديل.',
    ]);
    $apology = LiveSessionApology::firstOrFail();
    $this->actingAs($this->admin)->post(route('admin.session-apologies.deduct', $apology->id), [
        'amount_qar' => 100,
    ]);

    $student = User::factory()->create();
    $student->assignRole('student');
    $subscription = Subscription::factory()->active()->create([
        'student_id' => $student->id,
        'teaching_assignment_id' => $this->assignment->id,
        'teaching_group_id' => $this->group->id,
    ]);
    Payment::factory()->paid()->create([
        'user_id' => $student->id,
        'subscription_id' => $subscription->id,
        'amount' => 50_000,
        'original_amount' => 50_000,
        'teacher_earnings' => 40_000,
        'platform_commission_amount' => 10_000,
        'commission_percent' => 20,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.payouts.store'), [
            'teacher_id' => $this->teacher->id,
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->addDay()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $payout = TeacherPayout::firstOrFail();

    expect($payout->teacher_earnings)->toBe(40_000)
        ->and($payout->deductions_amount)->toBe(10_000)
        ->and($payout->amount)->toBe(30_000)
        ->and($apology->fresh()->teacher_payout_id)->toBe($payout->id);
});

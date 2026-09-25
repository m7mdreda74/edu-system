<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Communication\Notifications\ManualPaymentSubmittedNotification;
use App\Domain\Payment\Models\Coupon;
use App\Domain\Payment\Models\Payment;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\User\Models\ParentStudentLink;
use App\Domain\User\Models\User;
use App\Services\CurriculumBlobUpload;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Pays for one month of a subscription by uploading a Vodafone Cash receipt
 * for an admin to verify.
 *
 * A parent may pay on behalf of a linked student; every entry point checks that
 * link before letting the payment through.
 */
class CheckoutController extends Controller
{
    public function show(int $subscriptionId): Response
    {
        $subscription = $this->authorizeSubscription($subscriptionId);

        if ($subscription->isActive()) {
            return Inertia::render('Checkout/AlreadySubscribed', [
                'subscription' => $this->presentSubscription($subscription),
            ]);
        }

        return Inertia::render('Checkout/Index', [
            'subscription' => $this->presentSubscription($subscription),
            'vodafoneCashNumber' => $subscription->assignment?->gradeLevel?->vodafone_cash_number,
            'receiptUpload' => [
                'enabled' => app(CurriculumBlobUpload::class)->enabled(),
                'serverless' => (bool) config('services.vercel_blob.serverless', false),
                'authorize_url' => route('checkout.receipt.authorize', $subscription->id),
                'handle_url' => (string) config('services.vercel_blob.handle_url', '/api/blob-upload'),
            ],
        ]);
    }

    public function authorizeReceiptUpload(
        Request $request,
        int $subscriptionId,
        CurriculumBlobUpload $blobUploads,
    ): JsonResponse {
        $subscription = $this->authorizeSubscription($subscriptionId);

        abort_if($subscription->isActive(), 422, 'هذا الاشتراك مفعّل بالفعل.');
        abort_unless($blobUploads->enabled(), 503, 'رفع الإيصالات غير متاح حاليًا.');

        $data = $request->validate([
            'extension' => ['required', 'string', 'in:jpg,jpeg,png,webp,pdf'],
            'content_type' => ['required', 'string', 'in:image/jpeg,image/png,image/webp,application/pdf'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.CurriculumBlobUpload::MAX_RECEIPT_BYTES],
        ]);

        return response()->json($blobUploads->issueReceiptAuthorization(
            (int) $subscription->student_id,
            (int) $subscription->id,
            (string) $data['extension'],
            (string) $data['content_type'],
            (int) $data['file_size'],
        ));
    }

    public function process(Request $request, int $subscriptionId): SymfonyResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));

        if ($idempotencyKey === '') {
            $idempotencyKey = trim((string) $request->input('idempotency_key'));
        }

        $request->merge([
            'sender_phone' => PhoneNumber::normalize($request->input('sender_phone')),
            'idempotency_key' => $idempotencyKey,
        ]);

        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'coupon_code' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payment_method' => ['required', 'string', 'in:vodafone_cash'],
            'sender_phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+20|0020|0)1\d{9}$/'],
            'receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:8192',
                'required_without:receipt_blob_url',
            ],
            'receipt_blob_url' => ['nullable', 'url:https', 'required_without:receipt'],
            'receipt_blob_pathname' => ['nullable', 'string', 'max:950', 'required_with:receipt_blob_url'],
            'receipt_blob_sha256' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i', 'required_with:receipt_blob_url'],
        ], [
            'sender_phone.regex' => 'أدخل رقم الهاتف الذي حوّلت منه بصيغة 01012345678.',
        ]);

        $subscription = $this->authorizeSubscription($subscriptionId);

        return $this->processVodafoneCashPayment($request, $validated, $subscription, app(CurriculumBlobUpload::class));
    }

    public function checkCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'subscription_id' => ['required', 'integer', 'min:1', 'exists:subscriptions,id'],
        ]);

        /** @var Coupon|null $coupon */
        $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))->first();

        if (! $coupon || ! $coupon->isUsable()) {
            return response()->json(['error' => 'كود الخصم غير صحيح أو منتهي الصلاحية.'], 422);
        }

        $subscription = $this->authorizeSubscription((int) $validated['subscription_id']);

        return response()->json([
            'discount_percent' => $coupon->discount_percent,
            'discounted_price' => $coupon->applyDiscount($subscription->monthly_price),
        ]);
    }

    // ─── Internals ────────────────────────────────────────────────

    /**
     * The subscription must belong to the signed-in student, or to a student
     * this parent is linked to.
     */
    private function authorizeSubscription(int $subscriptionId): Subscription
    {
        /** @var User $user */
        $user = Auth::user();

        $allowedStudentIds = $user->isParent()
            ? ParentStudentLink::query()
                ->where('parent_user_id', $user->id)
                ->whereNotNull('verified_at')
                ->select('student_user_id')
            : null;

        $subscription = Subscription::with([
            'student:id,name',
            'assignment.subject:id,name,icon',
            'assignment.teacher:id,name,avatar,commission_percent',
            'assignment.gradeLevel:id,key,name,vodafone_cash_number',
            'group.schedules',
        ])->findOrFail($subscriptionId);

        $isOwnedByUser = (int) $subscription->student_id === (int) $user->id;
        $isOwnedByVerifiedStudent = $allowedStudentIds !== null
            && $allowedStudentIds->where('student_user_id', $subscription->student_id)->exists();

        abort_unless($isOwnedByUser || $isOwnedByVerifiedStudent, 403);

        return $subscription;
    }

    /** Vodafone Cash transfer: store the receipt and queue it for admin review. */
    private function processVodafoneCashPayment(
        Request $request,
        array $validated,
        Subscription $subscription,
        CurriculumBlobUpload $blobUploads,
    ): SymfonyResponse {
        $receiptFile = $request->file('receipt');
        $receiptBlobUrl = $validated['receipt_blob_url'] ?? null;
        $receiptBlobPathname = $validated['receipt_blob_pathname'] ?? null;
        $receiptMime = $receiptFile?->getMimeType();
        $receiptHash = $receiptFile?->getRealPath()
            ? hash_file('sha256', $receiptFile->getRealPath())
            : ($validated['receipt_blob_sha256'] ?? false);

        if ($receiptFile && (! is_string($receiptMime)
            || ! in_array($receiptMime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true))) {
            return $this->fail($request, 'نوع ملف الإيصال غير مسموح أو تعذّر فحصه.');
        }

        if (! $receiptFile) {
            try {
                $receiptBlobUrl = $blobUploads->validateCompletedReceipt(
                    (string) $receiptBlobUrl,
                    (string) $receiptBlobPathname,
                    (int) $subscription->student_id,
                    (int) $subscription->id,
                );
            } catch (Throwable) {
                return $this->fail($request, 'تعذّر التحقق من إيصال التحويل المرفوع.');
            }
        }

        if ($receiptFile
            && (bool) config('services.vercel_blob.serverless', false)
            && ! $blobUploads->enabled()) {
            return $this->fail($request, 'رفع الإيصالات غير مهيأ على الخادم حاليًا.');
        }

        if (! is_string($receiptHash)) {
            return $this->fail($request, 'تعذّر فحص إيصال التحويل.');
        }

        $storedReceiptPath = null;

        try {
            if ($subscription->isActive()) {
                throw new LogicException('هذا الاشتراك مفعّل بالفعل.');
            }

            $payment = DB::transaction(function () use ($validated, $subscription, $receiptHash, $receiptFile, $receiptBlobUrl, &$storedReceiptPath): Payment {
                /** @var Subscription $lockedSubscription */
                $lockedSubscription = Subscription::query()
                    ->lockForUpdate()
                    ->findOrFail($subscription->id);

                $existingPayment = Payment::query()
                    ->lockForUpdate()
                    ->where('idempotency_key', $validated['idempotency_key'])
                    ->first();

                if ($existingPayment) {
                    if (
                        (int) $existingPayment->user_id !== (int) $lockedSubscription->student_id
                        || (int) $existingPayment->subscription_id !== (int) $lockedSubscription->id
                    ) {
                        throw new LogicException('معرّف العملية مستخدم مع طلب دفع مختلف. ابدأ المحاولة من صفحة الدفع الحالية.');
                    }

                    if ($existingPayment->status === Payment::STATUS_FAILED) {
                        throw new LogicException('تم رفض طلب الدفع المرتبط بهذه المحاولة. ابدأ طلب دفع جديدًا.');
                    }

                    return $existingPayment;
                }

                $lockedSubscription->loadMissing([
                    'assignment.gradeLevel:id,vodafone_cash_number',
                    'assignment.teacher:id,commission_percent',
                ]);
                $recipientPhone = $lockedSubscription->assignment?->gradeLevel?->vodafone_cash_number;

                if (! is_string($recipientPhone) || ! preg_match('/^(?:\+20|0020|0)1\d{9}$/', $recipientPhone)) {
                    throw new LogicException('لم يتم ضبط رقم فودافون كاش لهذه المرحلة الدراسية بعد. تواصل مع إدارة المنصة.');
                }

                if ($lockedSubscription->isActive()) {
                    throw new LogicException('هذا الاشتراك مفعّل بالفعل.');
                }

                if ($lockedSubscription->monthly_price <= 0) {
                    throw new LogicException('هذا الاشتراك مجاني ولا يحتاج إلى دفع.');
                }

                $hasPendingReceipt = Payment::query()
                    ->where('user_id', $lockedSubscription->student_id)
                    ->where('subscription_id', $lockedSubscription->id)
                    ->where('status', Payment::STATUS_PENDING_VERIFICATION)
                    ->exists();

                if ($hasPendingReceipt) {
                    throw new LogicException('يوجد إيصال تحويل قيد المراجعة لهذا الاشتراك بالفعل.');
                }

                // A rejected receipt may be uploaded again after the student
                // fixes the issue. Keep the duplicate guard for receipts that
                // are still under review or have already been accepted.
                if (Payment::query()
                    ->where('receipt_sha256', $receiptHash)
                    ->where('status', '!=', Payment::STATUS_FAILED)
                    ->exists()) {
                    throw new LogicException('تم رفع هذا الإيصال من قبل ولا يمكن استخدامه مرة أخرى.');
                }

                $originalAmount = (int) $lockedSubscription->monthly_price;
                $finalAmount = $originalAmount;
                $coupon = null;

                if (! empty($validated['coupon_code'])) {
                    /** @var Coupon|null $coupon */
                    $coupon = Coupon::where('code', strtoupper(trim($validated['coupon_code'])))
                        ->lockForUpdate()
                        ->first();

                    if (! $coupon || ! $coupon->isUsable()) {
                        throw new LogicException('كود الخصم غير صحيح أو منتهي الصلاحية.');
                    }

                    $finalAmount = $coupon->applyDiscount($originalAmount);
                }

                $teacher = $lockedSubscription->assignment?->teacher;
                $defaultCommission = (int) (PlatformSetting::where('key', 'commission_percent')->value('value') ?? 20);
                $commissionPercent = max(0, min(100, (int) ($teacher?->commission_percent ?? $defaultCommission)));
                $storedReceiptPath = $receiptFile
                    ? $receiptFile->store('receipts', 'local')
                    : $receiptBlobUrl;

                try {
                    return Payment::create([
                        'user_id' => $lockedSubscription->student_id,
                        'subscription_id' => $lockedSubscription->id,
                        'teacher_id' => $teacher?->id,
                        'coupon_id' => $coupon?->id,
                        'amount' => $finalAmount,
                        'original_amount' => $originalAmount,
                        'commission_percent' => $commissionPercent,
                        'currency' => $lockedSubscription->currency ?? 'QAR',
                        'gateway' => Payment::GATEWAY_VODAFONE_CASH,
                        'gateway_ref' => 'Vodafone Cash: '.$recipientPhone,
                        'sender_phone' => $validated['sender_phone'],
                        'status' => Payment::STATUS_PENDING_VERIFICATION,
                        'receipt_path' => $storedReceiptPath,
                        'receipt_sha256' => $receiptHash,
                        'idempotency_key' => $validated['idempotency_key'],
                    ]);
                } catch (Throwable $e) {
                    if ($storedReceiptPath) {
                        $this->deleteLocalReceipt($storedReceiptPath);
                        $storedReceiptPath = null;
                    }

                    throw $e;
                }
            });

            $payment->load(['user', 'subscription']);

            if ($payment->wasRecentlyCreated) {
                foreach (User::role('admin')->get() as $admin) {
                    $admin->notify(new ManualPaymentSubmittedNotification($payment));
                }
            }

            $message = $payment->wasRecentlyCreated
                ? 'تم رفع الإيصال بنجاح. سيتم مراجعته وتفعيل الاشتراك خلال لحظات.'
                : 'تم استلام طلب التحويل مسبقًا، وهو قيد المراجعة بالفعل.';

            if ($request->wantsJson() || $request->ajax()) {
                // The Vue checkout redirects after this JSON response. Flash the
                // confirmation first so the destination page can show it.
                $request->session()->flash('success', $message);

                return response()->json([
                    'success' => true,
                    'redirect_url' => route('student.my-classes'),
                    'message' => $message,
                ]);
            }

            return redirect()->route('student.my-classes')->with('success', $message);
        } catch (LogicException $e) {
            if ($storedReceiptPath) {
                $this->deleteLocalReceipt($storedReceiptPath);
            }

            return $this->fail($request, $e->getMessage());
        } catch (Throwable $e) {
            if ($storedReceiptPath) {
                $this->deleteLocalReceipt($storedReceiptPath);
            }

            throw $e;
        }
    }

    private function deleteLocalReceipt(string $path): void
    {
        if (! str_starts_with($path, 'https://')) {
            Storage::disk('local')->delete($path);
        }
    }

    private function fail(Request $request, string $message): SymfonyResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['error' => $message], 422);
        }

        return back()->with('error', $message);
    }

    /** @return array<string, mixed> */
    private function presentSubscription(Subscription $subscription): array
    {
        $group = $subscription->group;

        return [
            'id' => $subscription->id,
            'type' => $subscription->type,
            'status' => $subscription->effectiveStatus(),
            'label' => $subscription->label(),
            'monthly_price' => $subscription->monthly_price,
            'currency' => $subscription->currency,
            'period_start' => $subscription->period_start?->toDateString(),
            'period_end' => $subscription->period_end?->toDateString(),
            'student' => $subscription->student?->only(['id', 'name']),
            'subject' => $subscription->assignment?->subject?->only(['id', 'name', 'icon']),
            'grade' => $subscription->assignment?->gradeLevel?->only(['key', 'name']),
            'teacher' => $subscription->assignment?->teacher?->only(['id', 'name', 'avatar']),
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'schedules' => $group->schedules->map(fn ($s) => [
                    'day' => (int) $s->day_of_week,
                    'start' => substr((string) $s->start_time, 0, 5),
                    'end' => substr((string) $s->end_time, 0, 5),
                ])->values(),
            ] : null,
        ];
    }
}

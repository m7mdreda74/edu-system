<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Coupon;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $coupons = Coupon::with('creator:id,name,email')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $coupons->getCollection()->each(function (Coupon $coupon): void {
            $coupon->setAttribute('is_expired', $coupon->isExpired());
            $coupon->setAttribute('is_usable', $coupon->isUsable());
        });

        return Inertia::render('Admin/Coupons', [
            'coupons' => $coupons,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code'],
            'discount_percent' => ['required', 'integer', 'between:1,100'],
            'expires_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['used_count'] = 0;
        $validated['is_active'] = true;
        $validated['created_by'] = auth()->id();

        $coupon = Coupon::create($validated);

        AuditLogger::record('admin.coupon.created', $coupon, [
            'code' => $coupon->code,
            'discount_percent' => $coupon->discount_percent,
            'admin_name' => auth()->user()?->name,
        ]);

        return back()->with('success', 'تم إنشاء الكوبون بنجاح.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => ! $coupon->is_active]);

        AuditLogger::record('admin.coupon.toggled', $coupon, [
            'code' => $coupon->code,
            'is_active' => $coupon->is_active,
            'admin_name' => auth()->user()?->name,
        ]);

        return back()->with('success', 'تم تعديل حالة الكوبون.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $code = $coupon->code;
        $coupon->delete();

        AuditLogger::record('admin.coupon.deleted', null, [
            'code' => $code,
            'admin_name' => auth()->user()?->name,
        ]);

        return back()->with('success', 'تم حذف الكوبون بنجاح.');
    }
}

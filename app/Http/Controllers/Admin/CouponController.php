<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $coupons = Coupon::latest()
            ->get();

        return Inertia::render('Admin/Coupons', [
            'coupons' => $coupons,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'discount_percent' => ['required', 'integer', 'between:1,100'],
            'expires_at'       => ['nullable', 'date'],
            'usage_limit'      => ['nullable', 'integer', 'min:1'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['used_count'] = 0;
        $validated['is_active'] = true;

        Coupon::create($validated);

        return back()->with('success', 'تم إنشاء الكوبون بنجاح.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update(['is_active' => !$coupon->is_active]);

        return back()->with('success', 'تم تعديل حالة الكوبون.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return back()->with('success', 'تم حذف الكوبون بنجاح.');
    }
}

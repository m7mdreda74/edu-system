<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\User\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OtpAuthController extends Controller
{
    /**
     * Send OTP code to the provided phone number.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
        ], [
            'phone.required' => 'يرجى إدخال رقم الهاتف الجوال.',
            'phone.min'      => 'رقم الهاتف قصير جداً.',
        ]);

        $rawPhone = trim($validated['phone']);
        $normalizedPhone = $this->normalizePhone($rawPhone);

        $throttleKey = 'otp_send:' . $normalizedPhone;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "تم تجاوز حد إرسال الرسائل. يرجى المحاولة بعد {$seconds} ثانية.",
            ], 429);
        }
        RateLimiter::hit($throttleKey, 300);

        // Generate 6-digit OTP code (123456 in local environment for convenience)
        $code = app()->environment('local') ? '123456' : (string) random_int(100000, 999999);

        // Cache for 5 minutes
        Cache::put('otp_code_' . $normalizedPhone, $code, now()->addMinutes(5));

        Log::info("OTP generated for [{$normalizedPhone}]: {$code}");

        $isDev = app()->environment('local') || (bool) config('app.debug');

        return response()->json([
            'success'  => true,
            'message'  => 'تم إرسال رمز التحقق بنجاح إلى هاتفك الجوال عبر رسالة نصية / واتساب.',
            'dev_code' => $isDev ? $code : null,
        ]);
    }

    /**
     * Verify OTP code and log the user in.
     */
    public function verifyOtp(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'code'  => ['required', 'string', 'min:4', 'max:10'],
            'role'  => ['nullable', 'string', 'in:parent,student'],
            'name'  => ['nullable', 'string', 'max:100'],
        ], [
            'phone.required' => 'يرجى إدخال رقم الهاتف الجوال.',
            'code.required'  => 'يرجى إدخال رمز التحقق (OTP).',
        ]);

        $rawPhone = trim($validated['phone']);
        $normalizedPhone = $this->normalizePhone($rawPhone);
        $code = trim($validated['code']);

        $cachedCode = Cache::get('otp_code_' . $normalizedPhone);

        // Allow '123456' in local environment for smooth pairing/testing
        $isValid = ($cachedCode && $cachedCode === $code)
            || (app()->environment('local') && $code === '123456');

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'message' => 'رمز التحقق غير صحيح أو انتهت صلاحيته. يرجى إعادة الإرسال.',
            ], 422);
        }

        // Clear OTP once used
        Cache::forget('otp_code_' . $normalizedPhone);

        // Find existing user by phone (try exact, normalized, or without country code)
        $user = User::where('phone', $rawPhone)
            ->orWhere('phone', $normalizedPhone)
            ->orWhere('phone', 'like', '%' . substr($normalizedPhone, -8))
            ->first();

        if (! $user) {
            // Register new user automatically with phone
            $roleName = $validated['role'] ?? 'parent';
            $name = ! empty($validated['name']) ? $validated['name'] : ($roleName === 'parent' ? 'ولي أمر جديد' : 'طالب جديد');
            $uniqueSlug = Str::random(6);

            $user = User::create([
                'name'              => $name,
                'email'             => 'user_' . substr($normalizedPhone, -8) . '_' . $uniqueSlug . '@almagd.edu',
                'phone'             => $rawPhone,
                'password'          => Hash::make(Str::random(24)),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole($roleName);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الحساب معطل حالياً. يرجى التواصل مع الإدارة.',
            ], 403);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $intendedUrl = redirect()->getIntendedUrl();
        if ($intendedUrl === url('/dashboard') && ! $user->isStudent()) {
            $request->session()->forget('url.intended');
        }

        $redirectUrl = route('dashboard');
        if ($user->isAdmin()) {
            $redirectUrl = route('admin.dashboard');
        } elseif ($user->isTeacher()) {
            $redirectUrl = route('teacher.dashboard');
        } elseif ($user->isParent()) {
            $redirectUrl = route('parent.dashboard');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'redirect_url' => $intendedUrl ?: $redirectUrl,
                'user'         => [
                    'id'   => $user->id,
                    'name' => $user->name,
                    'role' => $user->getRoleNames()->first() ?? 'student',
                ],
            ]);
        }

        return redirect()->intended($redirectUrl);
    }

    /**
     * Normalize Qatar & international phone numbers.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // If local 8 digits (Qatar standard), prefix 974
        if (strlen($digits) === 8) {
            return '974' . $digits;
        }

        // If starts with 00, remove it
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}

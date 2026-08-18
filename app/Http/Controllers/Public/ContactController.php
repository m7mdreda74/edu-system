<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Settings\Models\PlatformSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactMessageMail;
use App\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request, TurnstileVerifier $turnstile): RedirectResponse
    {
        $validated = $request->validated();

        if (! $turnstile->isConfigured() || ! $turnstile->verify($validated['captcha_token'], $request->ip())) {
            throw ValidationException::withMessages([
                'captcha_token' => 'تعذر إتمام التحقق الأمني. أعد المحاولة ثم أرسل الرسالة.',
            ]);
        }

        try {
            Mail::to($this->supportEmail())->send(new ContactMessageMail([
                'name' => $validated['name'],
                'email' => $validated['email'] ?? '',
                'phone' => $validated['phone'],
                'message' => $validated['message'],
            ]));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'تعذر إرسال الرسالة حاليًا. حاول مرة أخرى بعد قليل.');
        }

        return back()->with('success', 'شكرًا لتواصلك معنا! تم إرسال رسالتك بنجاح وسيقوم فريق الدعم بالرد عليك قريبًا.');
    }

    private function supportEmail(): string
    {
        $configuredEmail = PlatformSetting::query()
            ->where('key', 'contact_email')
            ->value('value');

        if (is_string($configuredEmail) && filter_var($configuredEmail, FILTER_VALIDATE_EMAIL)) {
            return $configuredEmail;
        }

        $fallbackEmail = (string) config('mail.from.address', 'noreply@altafawwuq.com');

        return filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)
            ? $fallbackEmail
            : 'noreply@altafawwuq.com';
    }
}

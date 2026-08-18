<?php

declare(strict_types=1);

use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'services.turnstile.enabled' => true,
        'services.turnstile.site_key' => 'test-site-key',
        'services.turnstile.secret_key' => 'test-secret-key',
        'services.turnstile.siteverify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ]);

    Mail::fake();
});

function contactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'محمد أحمد',
        'email' => 'visitor@example.com',
        'phone' => '+974 5555 6666',
        'message' => 'أحتاج إلى معرفة مواعيد الحصص المتاحة.',
        'captcha_token' => 'valid-token',
    ], $overrides);
}

it('validates the contact form and sends the message after Turnstile verification', function (): void {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
        ]),
    ]);

    $response = $this->from(route('contact'))->post(route('contact.store'), contactPayload());

    $response
        ->assertRedirect(route('contact'))
        ->assertSessionHas('success');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['response'] === 'valid-token';
    });

    Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
        return $mail->contact['name'] === 'محمد أحمد'
            && $mail->contact['phone'] === '+97455556666'
            && $mail->contact['message'] === 'أحتاج إلى معرفة مواعيد الحصص المتاحة.';
    });
});

it('rejects a name longer than 100 characters', function (): void {
    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload(['name' => str_repeat('أ', 101)]))
        ->assertSessionHasErrors('name');

    Mail::assertNothingSent();
});

it('rejects a message longer than 5000 characters', function (): void {
    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload(['message' => str_repeat('كلمة ', 1001)]))
        ->assertSessionHasErrors('message');

    Mail::assertNothingSent();
});

it('requires at least two words in the message', function (): void {
    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload(['message' => 'استفسار']))
        ->assertSessionHasErrors('message');

    Mail::assertNothingSent();
});

it('rejects an invalid phone number', function (): void {
    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload(['phone' => '123']))
        ->assertSessionHasErrors('phone');

    Mail::assertNothingSent();
});

it('rejects a failed Turnstile verification', function (): void {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    $this->from(route('contact'))
        ->post(route('contact.store'), contactPayload())
        ->assertSessionHasErrors('captcha_token');

    Mail::assertNothingSent();
});

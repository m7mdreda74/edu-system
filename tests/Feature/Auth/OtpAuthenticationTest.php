<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_an_otp_to_a_valid_phone_number(): void
    {
        $response = $this->postJson(route('otp.send'), [
            'phone' => '55556666',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue(Cache::has('otp_code_97455556666'));
    }

    public function test_verifies_otp_and_logs_in_an_existing_user(): void
    {
        $user = User::factory()->create([
            'phone'     => '97455556666',
            'is_active' => true,
        ]);

        Cache::put('otp_code_97455556666', '123456', now()->addMinutes(5));

        $response = $this->postJson(route('otp.verify'), [
            'phone' => '55556666',
            'code'  => '123456',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_rejects_an_incorrect_otp_code(): void
    {
        Cache::put('otp_code_97455556666', '123456', now()->addMinutes(5));

        $response = $this->postJson(route('otp.verify'), [
            'phone' => '55556666',
            'code'  => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertGuest();
    }
}

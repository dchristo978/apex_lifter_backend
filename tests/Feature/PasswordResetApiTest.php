<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_code_to_a_known_address(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPasswordCode::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_forgot_password_is_silent_for_unknown_address(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk();

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@example.com']);
    }

    public function test_reset_password_with_valid_code_changes_password_and_returns_token(): void
    {
        $user = User::factory()->create();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'new-password',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        // Code is single-use.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        // Issued token actually works.
        $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/auth/me')->assertOk();
    }

    public function test_reset_password_rejects_wrong_code(): void
    {
        $user = User::factory()->create();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '000000',
            'password' => 'new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reset_password_rejects_expired_code(): void
    {
        $user = User::factory()->create();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('123456'),
            'created_at' => now()->subMinutes(61),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'new-password',
        ])->assertStatus(422);
    }
}

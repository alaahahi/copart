<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class JsonLoginTest extends TestCase
{
    use DatabaseTransactions;

    public function test_json_login_returns_redirect_and_authenticates_session()
    {
        $user = User::factory()->create(['password' => bcrypt('password-123')]);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password-123',
            'remember' => true,
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'redirect' => '/dashboard']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_json_login_returns_422_on_bad_credentials()
    {
        $user = User::factory()->create(['password' => bcrypt('password-123')]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertGuest();
    }
}

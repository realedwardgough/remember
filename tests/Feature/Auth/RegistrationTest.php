<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this
            ->get('/register')
            ->assertNotFound();
    }

    public function test_new_users_can_register_with_an_invite(): void
    {
        $token = $this->createInviteToken('invited-member');

        $response = $this->post(route('register.store'), [
            'name' => 'Member',
            'email' => 'invited-member@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $token,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));

        $this->assertDatabaseHas('users', [
            'name' => 'Member',
            'username' => 'invited-member',
            'email' => 'invited-member@example.com',
        ]);
    }

    public function test_new_users_cannot_register_without_an_invite(): void
    {
        $this
            ->post(route('register.store'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('invitation_token');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
    }

    private function createInviteToken(string $username): string
    {
        $token = 'test-invite-token-'.$username;

        RegistrationInvite::query()->create([
            'username' => $username,
            'token_hash' => RegistrationInvite::hashToken($token),
        ]);

        return $token;
    }
}

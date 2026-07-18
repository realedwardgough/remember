<?php

namespace Tests\Feature\Auth;

use App\Models\RegistrationInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_a_registration_invite_link(): void
    {
        $exitCode = Artisan::call('users:create', [
            'username' => 'Invited-Member',
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Registration invite created.', $output);
        $this->assertStringContainsString('/register/invite/', $output);
        $this->assertDatabaseHas('registration_invites', [
            'username' => 'invited-member',
            'accepted_by' => null,
            'accepted_at' => null,
        ]);

        preg_match('#/register/invite/([A-Za-z0-9]+)#', $output, $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertDatabaseHas('registration_invites', [
            'token_hash' => RegistrationInvite::hashToken($matches[1]),
        ]);
    }

    public function test_command_rejects_a_username_that_already_exists(): void
    {
        User::factory()->create([
            'username' => 'invited-member',
        ]);

        $exitCode = Artisan::call('users:create', [
            'username' => 'invited-member',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseMissing('registration_invites', [
            'username' => 'invited-member',
        ]);
    }

    public function test_invite_registration_screen_can_be_rendered(): void
    {
        $token = $this->createInviteToken('new-member');

        $response = $this->get(route('register.invite', ['token' => $token]));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('invite.username', 'new-member')
            ->where('invite.token', $token),
        );
    }

    public function test_used_invite_registration_screen_cannot_be_rendered(): void
    {
        $token = $this->createInviteToken('used-member', [
            'accepted_by' => User::factory()->create()->id,
            'accepted_at' => now(),
        ]);

        $response = $this->get(route('register.invite', ['token' => $token]));

        $response->assertNotFound();
    }

    public function test_used_invite_cannot_create_another_user(): void
    {
        $token = $this->createInviteToken('grandad', [
            'accepted_by' => User::factory()->create()->id,
            'accepted_at' => now(),
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Grandad',
            'email' => 'grandad@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $token,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('invitation_token');
        $this->assertDatabaseMissing('users', [
            'email' => 'grandad@example.com',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createInviteToken(string $username, array $attributes = []): string
    {
        $token = 'test-invite-token-'.$username;

        RegistrationInvite::query()->create([
            'username' => $username,
            'token_hash' => RegistrationInvite::hashToken($token),
            ...$attributes,
        ]);

        return $token;
    }
}

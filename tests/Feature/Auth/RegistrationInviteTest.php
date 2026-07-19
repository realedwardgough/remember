<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use App\Models\RegistrationInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationInviteTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function command_creates_a_registration_invite_link(): void
    {
        $token = str_repeat('a', 64);
        Str::createRandomStringsUsing(static fn (): string => $token);

        $this->artisan('users:create', ['username' => 'Invited-Member'])
            ->expectsOutput('Registration invite created.')
            ->expectsOutput(URL::route('register.invite', ['token' => $token]))
            ->assertSuccessful();
        $this->assertDatabaseHas('registration_invites', [
            'username' => 'invited-member',
            'accepted_by' => null,
            'accepted_at' => null,
        ]);

        $this->assertDatabaseHas('registration_invites', [
            'token_hash' => RegistrationInvite::hashToken($token),
        ]);
    }

    #[Test]
    public function command_rejects_a_username_that_already_exists(): void
    {
        User::factory()->create([
            'username' => 'invited-member',
        ]);

        $this->artisan('users:create', ['username' => 'invited-member'])
            ->assertFailed();
        $this->assertDatabaseMissing('registration_invites', [
            'username' => 'invited-member',
        ]);
    }

    #[Test]
    public function invite_registration_screen_can_be_rendered(): void
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

    #[Test]
    public function used_invite_registration_screen_cannot_be_rendered(): void
    {
        $token = $this->createInviteToken('used-member', [
            'accepted_by' => User::factory()->create()->id,
            'accepted_at' => now(),
        ]);

        $response = $this->get(route('register.invite', ['token' => $token]));

        $response->assertNotFound();
    }

    #[Test]
    public function used_invite_cannot_create_another_user(): void
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

<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function password_can_be_updated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    #[Test]
    public function current_password_is_required_to_update_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password', null, 'updatePassword');

        $this->assertTrue(Hash::check('current-password', $user->refresh()->password));
    }

    #[Test]
    public function password_confirmation_is_required(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-password-123',
                'password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('password', null, 'updatePassword');

        $this->assertTrue(Hash::check('current-password', $user->refresh()->password));
    }
}

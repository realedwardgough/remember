<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Profile'));
    }

    #[Test]
    public function profile_information_can_be_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
        ]);

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-profile-information.update'), [
                'name' => 'Alex James Morgan',
                'email' => 'alex.morgan@example.com',
            ])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Alex James Morgan', $user->name);
        $this->assertSame('alex.morgan@example.com', $user->email);
    }

    #[Test]
    public function profile_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'alex@example.com']);

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-profile-information.update'), [
                'name' => 'Alex Morgan',
                'email' => 'taken@example.com',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('email', null, 'updateProfileInformation');

        $this->assertSame('alex@example.com', $user->refresh()->email);
    }

    #[Test]
    public function guests_cannot_view_the_profile_page(): void
    {
        $this
            ->get(route('profile.edit'))
            ->assertRedirect(route('login'));
    }
}

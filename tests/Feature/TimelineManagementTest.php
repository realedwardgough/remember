<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\UserRole;
use App\Models\Comment;
use App\Models\Post;
use App\Models\RegistrationInvite;
use App\Models\Timeline;
use App\Models\User;
use App\Notifications\RegistrationInviteNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimelineManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Timeline::factory()->create(['name' => 'Remember']);
    }

    #[Test]
    public function upgraded_installations_without_a_timeline_record_are_initialized(): void
    {
        Timeline::query()->delete();

        $this->actingAs($this->admin())->get(route('timeline.manage'))->assertOk();

        $timeline = Timeline::query()->sole();
        $this->assertSame(config('app.name'), $timeline->name);
        $this->assertSame(Timeline::PRIMARY_IDENTIFIER, $timeline->identifier);
    }

    #[Test]
    public function admins_can_open_timeline_management_from_the_profile_page(): void
    {
        $admin = $this->admin();
        User::factory()->create()->assignRole(UserRole::USER->value);

        $this->actingAs($admin)->get(route('profile.edit'))->assertInertia(
            fn (Assert $page) => $page->where('canManageTimeline', true),
        );

        $this->actingAs($admin)->get(route('timeline.manage'))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Timeline/Manage')->where('managedTimeline.name', 'Remember')->has('users', 2),
        );
    }

    #[Test]
    public function regular_users_cannot_access_or_mutate_timeline_management(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);
        $target = User::factory()->create();

        $this->actingAs($user)->get(route('timeline.manage'))->assertForbidden();
        $this->actingAs($user)->put(route('timeline.update'), ['name' => 'Changed'])->assertForbidden();
        $this->actingAs($user)->post(route('timeline.invitations.store'), ['username' => 'new-member'])->assertForbidden();
        $this->actingAs($user)->put(route('timeline.users.role.update', $target), ['role' => 'admin'])->assertForbidden();
        $this->actingAs($user)->delete(route('timeline.users.destroy', $target))->assertForbidden();
    }

    #[Test]
    public function admins_can_update_timeline_details(): void
    {
        $this->actingAs($this->admin())->put(route('timeline.update'), [
            'name' => 'The Morgan Timeline',
            'description' => 'Stories shared across generations.',
        ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'Timeline details updated.');

        $timeline = Timeline::query()->sole();
        $this->assertSame('The Morgan Timeline', $timeline->name);
        $this->assertSame('Stories shared across generations.', $timeline->description);
    }

    #[Test]
    public function admins_can_create_an_invitation_from_the_management_page(): void
    {
        $this->actingAs($this->admin())->post(route('timeline.invitations.store'), [
            'username' => 'new-family-member',
        ])
            ->assertRedirect()
            ->assertSessionHas('inviteUrl')
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'Invite link created.');

        $this->assertDatabaseHas('registration_invites', ['username' => 'new-family-member', 'accepted_at' => null]);
    }

    #[Test]
    public function pending_invitations_include_a_reusable_url(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('timeline.invitations.store'), ['username' => 'copy-me']);

        $this->actingAs($admin)->get(route('timeline.manage'))->assertInertia(
            fn (Assert $page) => $page
                ->where('pendingInvites.0.username', 'copy-me')
                ->where('pendingInvites.0.url', fn (string $url): bool => str_contains($url, '/register/invite/')),
        );
    }

    #[Test]
    public function admins_can_generate_a_replacement_url_for_legacy_pending_invitations(): void
    {
        $admin = $this->admin();
        $invite = RegistrationInvite::query()->create([
            'username' => 'legacy-invite',
            'token_hash' => RegistrationInvite::hashToken('unrecoverable-token'),
        ]);

        $this->actingAs($admin)
            ->put(route('timeline.invitations.update', $invite))
            ->assertRedirect()
            ->assertSessionHas('inviteUrl');

        $this->assertNotNull($invite->refresh()->token);
        $this->assertNotSame(RegistrationInvite::hashToken('unrecoverable-token'), $invite->token_hash);
    }


    #[Test]
    public function remember_configuration_is_shared_with_every_inertia_page(): void
    {
        $admin = $this->admin();
        config()->set('remember.email_notifications', false);

        $this->actingAs($admin)->get(route('timeline.manage'))->assertInertia(
            fn (Assert $page) => $page->where('remember.emailNotificationsEnabled', false),
        );

        config()->set('remember.email_notifications', true);

        $this->actingAs($admin)->get(route('profile.edit'))->assertInertia(
            fn (Assert $page) => $page->where('remember.emailNotificationsEnabled', true),
        );
    }

    #[Test]
    public function admins_can_email_a_pending_registration_invitation(): void
    {
        config()->set('remember.email_notifications', true);
        Notification::fake();
        $invite = RegistrationInvite::query()->create([
            'username' => 'email-invite',
            'token_hash' => RegistrationInvite::hashToken('email-token'),
            'token' => 'email-token',
        ]);

        $this->actingAs($this->admin())
            ->post(route('timeline.invitations.notify', $invite), ['email' => 'invitee@example.com'])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'Email notification sent.');

        Notification::assertSentOnDemand(
            RegistrationInviteNotification::class,
            function (RegistrationInviteNotification $notification, array $channels, object $notifiable): bool {
                $message = $notification->toMail($notifiable);

                return $notifiable->routes['mail'] === 'invitee@example.com'
                    && $channels === ['mail']
                    && $message->actionUrl === route('register.invite', ['token' => 'email-token']);
            },
        );
    }

    #[Test]
    public function invitation_emails_are_unavailable_when_disabled(): void
    {
        config()->set('remember.email_notifications', false);
        Notification::fake();
        $invite = RegistrationInvite::query()->create([
            'username' => 'disabled-email',
            'token_hash' => RegistrationInvite::hashToken('disabled-token'),
            'token' => 'disabled-token',
        ]);

        $this->actingAs($this->admin())
            ->post(route('timeline.invitations.notify', $invite), ['email' => 'invitee@example.com'])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    #[Test]
    public function invitation_notification_requires_a_valid_email_address(): void
    {
        config()->set('remember.email_notifications', true);
        Notification::fake();
        $invite = RegistrationInvite::query()->create([
            'username' => 'invalid-email',
            'token_hash' => RegistrationInvite::hashToken('invalid-token'),
            'token' => 'invalid-token',
        ]);

        $this->actingAs($this->admin())
            ->post(route('timeline.invitations.notify', $invite), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    #[Test]
    public function admins_can_assign_roles_to_users(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);

        $this->actingAs($admin)
            ->put(route('timeline.users.role.update', $user), ['role' => 'admin'])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'User role updated.');

        $this->assertTrue($user->refresh()->hasRole(UserRole::ADMIN->value));
    }

    #[Test]
    public function the_final_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('timeline.users.role.update', $admin), ['role' => 'user'])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->refresh()->hasRole(UserRole::ADMIN->value));
    }

    #[Test]
    public function admins_can_remove_members_without_removing_their_timeline_content(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole(UserRole::USER->value);
        $post = Post::factory()->for($user, 'author')->memory()->create();
        $comment = Comment::factory()->for($user, 'author')->for($post)->create();

        $this->actingAs($admin)
            ->delete(route('timeline.users.destroy', $user))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'Account removed.');

        $this->assertModelMissing($user);
        $this->assertNull($post->refresh()->author_id);
        $this->assertNull($comment->refresh()->author_id);
    }

    #[Test]
    public function admins_cannot_remove_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('timeline.users.destroy', $admin))->assertSessionHasErrors('user');
        $this->assertModelExists($admin);
    }

    #[Test]
    public function role_command_can_bootstrap_an_admin_on_an_existing_site(): void
    {
        $user = User::factory()->create(['username' => 'existing-user']);

        $this->artisan('users:role', ['user' => 'existing-user'])
            ->expectsOutput('Assigned the admin role to existing-user.')
            ->assertSuccessful();

        $this->assertTrue($user->refresh()->hasRole(UserRole::ADMIN->value));
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        return $admin;
    }
}

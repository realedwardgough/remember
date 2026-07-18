<?php

namespace Tests\Feature;

use App\Models\Timeline;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_is_available_for_a_fresh_installation(): void
    {
        $this->get(route('setup.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Setup')
                ->where('timeline.name', 'Remember')
                ->where('timeline.description', null));
    }

    public function test_setup_creates_the_timeline_and_first_user(): void
    {
        $this->seed(RoleSeeder::class);

        $response = $this->post(route('setup.store'), $this->validSetupData());

        $timeline = Timeline::query()->sole();
        $user = User::query()->sole();

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('The Morgan Family', $timeline->name);
        $this->assertSame('Our stories, milestones and memories.', $timeline->description);
        $this->assertNotNull($timeline->setup_completed_at);
        $this->assertSame('Alex Morgan', $user->name);
        $this->assertSame('alex', $user->username);
        $this->assertSame('alex@example.com', $user->email);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('user'));
    }

    public function test_setup_validates_timeline_and_account_details(): void
    {
        $this->post(route('setup.store'), [
            'timeline_name' => '',
            'timeline_description' => str_repeat('a', 281),
            'name' => '',
            'username' => 'Not Valid',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors([
            'timeline_name',
            'timeline_description',
            'name',
            'username',
            'email',
            'password',
        ]);

        $this->assertDatabaseEmpty('timelines');
        $this->assertDatabaseEmpty('users');
        $this->assertGuest();
    }

    public function test_setup_routes_are_not_available_after_setup_is_complete(): void
    {
        Timeline::factory()->create();

        $this->get(route('setup.show'))->assertNotFound();
        $this->post(route('setup.store'), $this->validSetupData())->assertNotFound();
        $this->assertDatabaseEmpty('users');
    }

    public function test_existing_installations_cannot_access_setup(): void
    {
        User::factory()->create();

        $this->get(route('setup.show'))->assertNotFound();
        $this->post(route('setup.store'), $this->validSetupData())->assertNotFound();
        $this->assertDatabaseEmpty('timelines');
    }

    /** @return array<string, string> */
    private function validSetupData(): array
    {
        return [
            'timeline_name' => 'The Morgan Family',
            'timeline_description' => 'Our stories, milestones and memories.',
            'name' => 'Alex Morgan',
            'username' => 'alex',
            'email' => 'alex@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }
}

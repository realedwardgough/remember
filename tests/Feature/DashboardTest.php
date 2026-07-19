<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('home'));
        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_users_can_visit_the_timeline(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $manifest = json_decode(
            file_get_contents(public_path('site.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $response = $this->get(route('home'));
        $response
            ->assertOk()
            ->assertSee('name="theme-color" content="#e06573"', false)
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('rel="manifest" href="/site.webmanifest"', false);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('#fff9e8', $manifest['background_color']);
        $this->assertSame('#e06573', $manifest['theme_color']);
    }
}

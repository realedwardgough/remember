<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\AssignUserRole;
use App\Actions\CreateRegistrationInvite;
use App\Actions\UpdateTimeline;
use App\DTOs\RegistrationInviteResultDTO;
use App\DTOs\TimelineData;
use App\Enum\UserRole;
use App\Models\Timeline;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimelineManagementActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    #[Test]
    public function update_timeline_returns_the_updated_timeline(): void
    {
        $timeline = Timeline::factory()->create();

        $updated = app(UpdateTimeline::class)->handle($timeline, new TimelineData('Family Stories', 'Our shared history.'));

        $this->assertInstanceOf(Timeline::class, $updated);
        $this->assertSame('Family Stories', $updated->name);
    }

    #[Test]
    public function create_invitation_returns_a_typed_result(): void
    {
        $result = app(CreateRegistrationInvite::class)->handle('Family-Member');

        $this->assertInstanceOf(RegistrationInviteResultDTO::class, $result);
        $this->assertSame('family-member', $result->invite->username);
        $this->assertStringContainsString('/register/invite/', $result->url);
    }

    #[Test]
    public function invitation_tokens_are_encrypted_at_rest(): void
    {
        $result = app(CreateRegistrationInvite::class)->handle('encrypted-invite');
        $storedToken = DB::table('registration_invites')->where('id', $result->invite->id)->value('token');

        $this->assertSame($result->token, $result->invite->refresh()->token);
        $this->assertNotSame($result->token, $storedToken);
    }

    #[Test]
    public function assign_role_returns_the_user_with_the_new_role(): void
    {
        $user = User::factory()->create();

        $updated = app(AssignUserRole::class)->handle($user, UserRole::ADMIN);

        $this->assertInstanceOf(User::class, $updated);
        $this->assertTrue($updated->hasRole(UserRole::ADMIN->value));
    }
}

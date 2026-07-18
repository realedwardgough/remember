<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\UserRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_the_application_roles_idempotently(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseCount('roles', 2);
        $this->assertEqualsCanonicalizing(
            array_column(UserRole::cases(), 'value'),
            Role::query()->pluck('name')->all(),
        );
        $this->assertDatabaseEmpty((new User)->getTable());
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\DTOs\SetupData;
use App\Enum\UserRole;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTimeline
{
    public function handle(SetupData $data): User
    {
        return DB::transaction(function () use ($data): User {
            abort_if(Timeline::query()->exists() || User::query()->exists(), 404);

            Timeline::query()->create([
                'name' => $data->timelineName,
                'description' => $data->timelineDescription,
                'setup_completed_at' => now(),
            ]);

            $user = User::query()->create([
                'name' => $data->name,
                'username' => $data->username,
                'email' => $data->email,
                'password' => $data->password,
            ]);
            $user->assignRole(UserRole::ADMIN->value);

            return $user;
        }, attempts: 3);
    }
}

<?php

namespace App\Actions\Setup;

use App\Models\Timeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTimeline
{
    /**
     * @param  array{timeline_name: string, timeline_description?: ?string, name: string, username: string, email: string, password: string}  $input
     */
    public function execute(array $input): User
    {
        return DB::transaction(function () use ($input): User {
            abort_if(Timeline::query()->exists() || User::query()->exists(), 404);

            Timeline::query()->create([
                'name' => $input['timeline_name'],
                'description' => $input['timeline_description'] ?? null,
                'setup_completed_at' => now(),
            ]);

            return User::query()->create([
                'name' => $input['name'],
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
        }, attempts: 3);
    }
}

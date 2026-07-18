<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RegistrationInvite;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\text;

#[Description('Create a single-use registration invite link for a preset username')]
#[Signature('users:create {username? : The username to reserve for the invited user}')]
class CreateRegistrationInvite extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $username = $this->argument(key: 'username')) {
            $username = text(label: 'What is the username?', required: true);
        }

        $username = Str::lower((string) $username);

        $validator = Validator::make(['username' => $username], [
            'username' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                Rule::unique(User::class, 'username'),
                Rule::unique(RegistrationInvite::class, 'username'),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $token = Str::random(64);

        RegistrationInvite::query()->create([
            'username' => $username,
            'token_hash' => RegistrationInvite::hashToken($token),
        ]);

        $this->info('Registration invite created.');
        $this->line(URL::route('register.invite', ['token' => $token]));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\CreateRegistrationInvite as CreateRegistrationInviteAction;
use App\Models\RegistrationInvite;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\text;

#[Description('Create a single-use registration invite link for a preset username')]
#[Signature('users:create {username? : The username to reserve for the invited user}')]
class CreateRegistrationInvite extends Command
{
    public function __construct(private readonly CreateRegistrationInviteAction $createInvite)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $username = Str::lower((string) ($this->argument('username') ?: text(label: 'What is the username?', required: true)));
        $validator = Validator::make(['username' => $username], [
            'username' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique(User::class, 'username'), Rule::unique(RegistrationInvite::class, 'username')->whereNull('accepted_at')],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $result = $this->createInvite->handle($username);
        $this->info('Registration invite created.');
        $this->line($result->url);

        return self::SUCCESS;
    }
}

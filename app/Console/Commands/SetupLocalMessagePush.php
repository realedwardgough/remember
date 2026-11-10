<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class SetupLocalMessagePush extends Command
{
    protected $signature = 'messages:setup-local-push';
    protected $description = 'Generate stable VAPID keys in the local .env without displaying secrets';

    public function handle(): int
    {
        if (! app()->environment('local') || app()->configurationIsCached()) {
            $this->error('Run only in the local environment with configuration cache cleared.');
            return self::FAILURE;
        }
        $subject = config('remember.push.subject') ?: config('app.url');
        if (! is_string($subject) || (! (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL)) && ! (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL) && parse_url($subject, PHP_URL_HOST) !== 'localhost'))) {
            $this->error('Set APP_URL to the local HTTPS app address before configuring push.');
            return self::FAILURE;
        }
        $path = app()->environmentFilePath();
        $content = file_get_contents($path);
        if ($content === false || preg_match('/^VAPID_(?:PUBLIC|PRIVATE)_KEY=[^\r\n]+/m', $content)) {
            $this->error('Existing keys are preserved. Do not rotate keys while devices are subscribed.');
            return self::FAILURE;
        }
        $keys = VAPID::createVapidKeys();
        foreach (['MESSAGE_PUSH_ENABLED' => 'true', 'VAPID_SUBJECT' => $subject, 'VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $line = $name.'='.$value;
            $content = preg_match('/^'.$name.'=/m', $content)
                ? preg_replace_callback('/^'.$name.'=[^\r\n]*/m', fn () => $line, $content)
                : rtrim($content)."\n".$line."\n";
        }
        $temporary = tempnam(dirname($path), '.env.push-');
        try {
            chmod($temporary, 0600);
            if (file_put_contents($temporary, $content, LOCK_EX) === false || ! rename($temporary, $path)) {
                $this->error('Could not write the local environment file.');
                return self::FAILURE;
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
        $this->info('Local Web Push configured. Keys were saved privately and were not printed. Start a database queue worker and the scheduler.');
        return self::SUCCESS;
    }
}

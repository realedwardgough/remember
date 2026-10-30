<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use Minishlink\WebPush\VAPID;
use Throwable;

class PushSettings
{
    public function available(): bool
    {
        if (! config('remember.push.enabled') || ! in_array(config('queue.connections.'.config('queue.default').'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
            return false;
        }
        try {
            $subject = config('remember.push.subject', '');
            if (! is_string($subject) || (! (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL))
                && ! (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL) && parse_url($subject, PHP_URL_HOST) !== 'localhost'))) {
                return false;
            }
            VAPID::validate($this->credentials());
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function credentials(): array
    {
        return [
            'subject' => config('remember.push.subject', ''),
            'publicKey' => config('remember.push.public_key', ''),
            'privateKey' => config('remember.push.private_key', ''),
        ];
    }

    public function publicSettings(): array
    {
        return ['available' => $this->available(), 'public_key' => $this->available() ? config('remember.push.public_key') : null];
    }
}

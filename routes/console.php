<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('messages:cleanup-images')->everyMinute()->withoutOverlapping(10);

Schedule::command('messages:retry-push')->everyMinute()->withoutOverlapping(10);

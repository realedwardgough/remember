<?php

declare(strict_types=1);

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $database, $barrier, $workerId, $operation, $actorId, $targetId, $payload] = $argv;
config()->set('database.connections.messaging_concurrency', [
    ...config('database.connections.sqlite'),
    'database' => $database,
    'url' => null,
    'busy_timeout' => 10000,
    'transaction_mode' => 'IMMEDIATE',
]);
DB::setDefaultConnection('messaging_concurrency');
file_put_contents($barrier.'.'.$workerId, 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Timed out waiting for concurrent test workers.');
    }
    usleep(1000);
}

$actor = User::findOrFail((int) $actorId);
if ($operation === 'start') {
    $id = app(StartConversation::class)->handle($actor, (int) $targetId)->id;
} elseif ($operation === 'send') {
    $id = app(SendMessage::class)->handle($actor, Conversation::findOrFail((int) $targetId), 'Concurrent message', $payload)->id;
} else {
    app(MarkConversationRead::class)->handle($actor, Conversation::findOrFail((int) $targetId), $payload);
    $id = (int) $targetId;
}
echo $id;

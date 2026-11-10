<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMembership;
use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\ReadReceiptToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MessagingConcurrencyTest extends TestCase
{
    #[Test]
    public function concurrent_starts_sends_and_read_receipts_preserve_invariants(): void
    {
        $directory = storage_path('framework/testing/messaging-'.Str::uuid());
        mkdir($directory, 0777, true);
        $database = $directory.'/database.sqlite';
        touch($database);
        $previousConnection = DB::getDefaultConnection();
        config()->set('database.connections.messaging_concurrency', [
            ...config('database.connections.sqlite'),
            'database' => $database,
            'url' => null,
            'busy_timeout' => 10000,
            'transaction_mode' => 'IMMEDIATE',
        ]);
        DB::setDefaultConnection('messaging_concurrency');

        try {
            Artisan::call('migrate', ['--database' => 'messaging_concurrency', '--force' => true]);
            $first = User::factory()->create();
            $second = User::factory()->create();
            $ids = $this->race($database, $directory.'/start', [
                ['start', $first->id, $second->id, ''],
                ['start', $second->id, $first->id, ''],
            ]);
            $this->assertSame($ids[0], $ids[1]);
            $this->assertSame(1, Conversation::count());
            $this->assertSame(2, ConversationMembership::count());
            $conversation = Conversation::query()->sole();

            $requestId = (string) Str::uuid();
            $ids = $this->race($database, $directory.'/send', [
                ['send', $first->id, $conversation->id, $requestId],
                ['send', $first->id, $conversation->id, $requestId],
            ]);
            $this->assertSame($ids[0], $ids[1]);
            $this->assertSame(1, Message::count());
            $oldMessage = Message::query()->sole();
            $newMessage = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_participant_id' => $oldMessage->sender_participant_id,
                'body' => 'Second message',
                'client_request_id' => (string) Str::uuid(),
            ]);
            $tokens = app(ReadReceiptToken::class);
            $this->race($database, $directory.'/read', [
                ['read', $second->id, $conversation->id, $tokens->issue($second, $conversation, $newMessage->id)],
                ['read', $second->id, $conversation->id, $tokens->issue($second, $conversation, $oldMessage->id)],
            ]);
            $this->assertSame($newMessage->id, ConversationMembership::query()
                ->where('participant_id', $conversation->participantFor($second)->id)->value('last_read_message_id'));
        } finally {
            DB::purge('messaging_concurrency');
            DB::setDefaultConnection($previousConnection);
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function race(string $database, string $barrier, array $operations): array
    {
        $workers = [];
        try {
            foreach ($operations as $index => $operation) {
                $worker = new Process([
                    PHP_BINARY, base_path('tests/Fixtures/messaging-worker.php'),
                    $database, $barrier, (string) $index,
                    ...array_map(strval(...), $operation),
                ], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key')]);
                $worker->setTimeout(25)->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            foreach ($workers as $index => $worker) {
                while (! file_exists($barrier.'.'.$index)) {
                    if (! $worker->isRunning() || microtime(true) > $deadline) {
                        $this->fail('Worker did not become ready: '.$worker->getErrorOutput().$worker->getOutput());
                    }
                    usleep(10000);
                }
            }
            touch($barrier);
            return array_map(function (Process $worker): string {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                return trim($worker->getOutput());
            }, $workers);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }
}

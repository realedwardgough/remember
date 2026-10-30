<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Messaging\MessageImageProcessor;
use App\Services\Messaging\MessageImageSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadMessageImage
{
    public function __construct(private readonly MessageImageSettings $settings, private readonly MessageImageProcessor $processor)
    {
    }

    public function handle(User $user, Conversation $conversation, UploadedFile $file, string $requestId): MessageAttachment
    {
        Gate::forUser($user)->authorize('send', $conversation);
        if (! $this->settings->available()) {
            throw ValidationException::withMessages(['image' => 'Photo sharing is currently unavailable. You can still send a text message.']);
        }

        $requestId = strtolower($requestId);
        $hash = hash_file('sha256', $file->getRealPath());
        $processed = $this->processor->process($file);
        $attachment = DB::transaction(function () use ($user, $conversation, $requestId, $hash, $processed): MessageAttachment {
            User::query()->lockForUpdate()->findOrFail($user->id);
            Gate::forUser($user)->authorize('send', $conversation);
            $participant = $conversation->participantFor($user);
            $existing = MessageAttachment::query()->where('uploaded_by_participant_id', $participant->id)->where('client_request_id', $requestId)->first();
            if ($existing !== null) {
                if ($existing->source_hash !== $hash || $existing->conversation_id !== $conversation->id) {
                    throw ValidationException::withMessages(['image' => 'This upload ID was already used for a different photo.']);
                }
                return $existing;
            }
            if (MessageAttachment::query()->where('uploaded_by_participant_id', $participant->id)->whereNull('message_id')->whereNull('files_removed_at')->where('expires_at', '>', now())->count() >= 20) {
                throw ValidationException::withMessages(['image' => 'You have too many unfinished photo uploads. Please send your existing photos first.']);
            }
            $hours = $this->settings->retentionHours();
            $path = 'messages/'.$conversation->id.'/'.Str::uuid();
            $uploadedAt = now();

            // Persist both intended object names before writing so a failed upload remains cleanable.
            return MessageAttachment::query()->create([
                'conversation_id' => $conversation->id,
                'uploaded_by_participant_id' => $participant->id,
                'client_request_id' => $requestId,
                'source_hash' => $hash,
                'disk' => 'message_gcs',
                'path' => $path.'.webp',
                'thumbnail_path' => $path.'-thumbnail.webp',
                'width' => $processed['width'], 'height' => $processed['height'], 'size' => strlen($processed['full']),
                'retention_hours' => $hours, 'uploaded_at' => $uploadedAt, 'expires_at' => $uploadedAt->addHours($hours),
            ]);
        }, attempts: 3);

        try {
            return DB::transaction(function () use ($attachment, $processed): MessageAttachment {
                $locked = MessageAttachment::query()->lockForUpdate()->findOrFail($attachment->id);
                if ($locked->files_removed_at !== null || $locked->expires_at->isPast()) {
                    throw ValidationException::withMessages(['image' => 'This photo upload has expired. Please choose the photo again.']);
                }
                if ($locked->ready_at !== null) {
                    return $locked;
                }
                // Cleanup takes the same row lock, so it cannot race these writes.
                $disk = Storage::disk('message_gcs');
                $options = ['visibility' => 'private', 'metadata' => ['contentType' => 'image/webp', 'cacheControl' => 'private,no-store,max-age=0']];
                if (! $disk->put($locked->path, $processed['full'], $options) || ! $disk->put($locked->thumbnail_path, $processed['thumbnail'], $options)) {
                    throw new \RuntimeException('Message image storage did not confirm a write.');
                }
                $locked->update(['ready_at' => now()]);

                return $locked;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            // Leave the durable object names for scheduled retry cleanup and safe upload retry.
            throw ValidationException::withMessages(['image' => 'Your photo couldn’t be uploaded. Please try again.']);
        }
    }
}

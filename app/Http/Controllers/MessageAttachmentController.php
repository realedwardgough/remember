<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messaging\UploadMessageImage;
use App\Http\Requests\Messaging\UploadMessageImageRequest;
use App\Http\Resources\Messaging\MessageAttachmentResource;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageAttachmentController extends Controller
{
    public function store(UploadMessageImageRequest $request, Conversation $conversation, UploadMessageImage $upload): JsonResponse
    {
        $attachment = $upload->handle($request->user(), $conversation, $request->file('image'), $request->validated('client_request_id'));

        return (new MessageAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function show(Request $request, MessageAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->conversation);
        if ($attachment->message_id === null) {
            abort_unless($attachment->uploaded_by_participant_id === $attachment->conversation->participantFor($request->user())->id, 404);
        }
        abort_unless($attachment->available() && $attachment->disk === 'message_gcs', 410);
        $path = $request->boolean('thumbnail') ? $attachment->thumbnail_path : $attachment->path;

        return Storage::disk('message_gcs')->response($path, 'photo.webp', [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private,no-store,max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

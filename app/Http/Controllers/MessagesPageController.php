<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MessagesPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Messages', ['conversationId' => null]);
    }

    public function show(Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        return Inertia::render('Messages', ['conversationId' => $conversation->id]);
    }
}

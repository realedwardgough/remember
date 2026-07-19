<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Setup\CreateTimeline;
use App\Http\Requests\StoreSetupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function __construct(private readonly CreateTimeline $createTimeline)
    {
    }
    public function show(): Response
    {
        return Inertia::render(component: 'Setup');
    }

    public function store(StoreSetupRequest $request): RedirectResponse
    {
        $user = $this->createTimeline->handle($request->toDTO());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}

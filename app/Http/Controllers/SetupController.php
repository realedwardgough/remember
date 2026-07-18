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
    public function show(): Response
    {
        return Inertia::render(component: 'Setup');
    }

    public function store(StoreSetupRequest $request, CreateTimeline $createTimeline): RedirectResponse
    {
        $user = $createTimeline->execute($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}

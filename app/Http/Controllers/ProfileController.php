<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\UserRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render(component: 'Profile', props: [
            'canManageTimeline' => $request->user()->hasRole(UserRole::ADMIN->value),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\TeamInvite;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Workspaces\WorkspaceProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Accept a team invitation. Phase 2 supports the common case: a NEW email is
 * invited and creates its account directly inside the inviting workspace. An
 * email that already has an account is a future (multi-workspace) enhancement.
 */
class InviteController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $invite = TeamInvite::where('token', $token)->first();

        if (! $invite || ! $invite->isPending()) {
            return view('invite.invalid');
        }

        if (User::where('email', $invite->email)->exists()) {
            return view('invite.invalid', [
                'reason' => 'This email already has an account. Ask the workspace owner to add you directly.',
            ]);
        }

        return view('invite.show', ['invite' => $invite]);
    }

    public function accept(string $token, Request $request, WorkspaceProvisioner $provisioner): RedirectResponse
    {
        $invite = TeamInvite::where('token', $token)->first();

        if (! $invite || ! $invite->isPending()) {
            return redirect()->route('invite.show', $token);
        }

        if (User::where('email', $invite->email)->exists()) {
            return redirect()->route('invite.show', $token);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $invite->email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $provisioner->attachMember($user, $invite->workspace, $invite->role);

        $invite->forceFill(['accepted_at' => now()])->save();

        Auth::login($user);

        ActivityLogger::log('team.member_joined', $user, "{$user->name} joined the workspace as {$invite->role}");

        return redirect()->route('app.dashboard');
    }
}

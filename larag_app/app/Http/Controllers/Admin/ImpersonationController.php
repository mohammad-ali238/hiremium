<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "login as": sign in as another user to see exactly what they see.
 *
 * Both ends are narrow on purpose. `start` refuses unless the current user is an
 * admin AND the admin-only Filament action left a single-use hand-off in the
 * session, so there is no URL to replay or guess. `stop` only works inside an
 * impersonated session and only hands back to the admin who started it, if that
 * account is still an admin.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin && $admin->is_admin, 403);
        // No nesting: switch back first.
        abort_if(Impersonation::isActive(), 403);

        $targetId = Impersonation::takeTarget();
        abort_unless($targetId !== null, 403);

        $target = User::find($targetId);
        abort_unless($target !== null, 404);
        abort_if($target->is($admin), 403);

        Impersonation::begin($admin, $target);

        // Auth::login() migrates the session id itself (keeping the session data,
        // so the impersonation keys survive), which rules out session fixation.
        Auth::login($target);

        return redirect()->route('app.dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless(Impersonation::isActive(), 403);

        $admin = Impersonation::impersonator();
        abort_unless($admin !== null, 403);

        $returnUrl = Impersonation::returnUrl();

        Impersonation::end();
        Auth::login($admin);

        return redirect()->to($returnUrl);
    }
}

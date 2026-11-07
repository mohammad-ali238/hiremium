<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes spatie role/permission checks to the current user's workspace (spatie
 * "teams" mode). Must run on every authenticated recruiter request so
 * $user->can(...) / @can(...) evaluate within the right workspace.
 */
class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->workspace_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->workspace_id);
        }

        return $next($request);
    }
}

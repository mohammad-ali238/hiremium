<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a user's avatar from the private disk (no public symlink needed).
 * Avatars are low-sensitivity, so any authenticated user may view them.
 */
class AvatarController extends Controller
{
    public function show(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user(), 403);
        abort_if(! $user->avatar_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}

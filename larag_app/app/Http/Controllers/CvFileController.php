<?php

namespace App\Http\Controllers;

use App\Models\ResumeFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a private CV file. Access requires the file to belong to the caller's
 * workspace and a recruiter permission. Streams from whichever disk stored it
 * (local now, S3/R2 later) so nothing is ever web-served directly.
 */
class CvFileController extends Controller
{
    public function show(Request $request, ResumeFile $file): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user && $file->workspace_id === $user->workspace_id, 403);
        abort_unless(
            $user->can('uploads.manage') || $user->can('candidates.view') || $user->can('jobs.view'),
            403
        );

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->storage_key), 404);

        return $disk->download($file->storage_key, $file->original_name);
    }
}

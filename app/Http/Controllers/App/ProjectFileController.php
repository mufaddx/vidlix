<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ProjectFile;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorised access to project media.
 *
 * On an S3-compatible disk the user is redirected to a short-lived signed URL,
 * so the bytes never pass through the app. The local development disk cannot
 * sign, so it streams through this authorised route instead.
 */
class ProjectFileController extends Controller
{
    public function download(Request $request, ProjectFile $file, MediaStorage $media, AuditLogger $audit): RedirectResponse|StreamedResponse
    {
        $project = $file->project;
        abort_unless($project && $project->involves($request->user()), 403);

        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_key), 404);

        $audit->record('project.file_downloaded', $file);

        $signed = $media->temporaryUrl($disk, $file->storage_key);
        if ($signed !== null) {
            return redirect()->away($signed);
        }

        return Storage::disk($disk)->download($file->storage_key, $file->original_name);
    }
}

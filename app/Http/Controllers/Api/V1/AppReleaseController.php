<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * What build the app should be running, and where to get it.
 *
 * Vidlix is not on Play Store, so nothing updates the app on its own. It asks
 * this on launch and offers the new build to whoever is behind. Installing a
 * different APK by hand every time is not a release process.
 */
class AppReleaseController extends Controller
{
    public function android(Request $request): JsonResponse
    {
        $installed = (string) $request->query('version', '0.0.0');
        $latest = (string) config('vidlix.app.android_version');
        $minimum = (string) config('vidlix.app.android_minimum');

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => [
                'latest' => $latest,
                'minimum' => $minimum,
                'installed' => $installed,
                'update_available' => version_compare($installed, $latest, '<'),
                // Required means the installed build can no longer talk to this
                // API correctly, not merely that something newer exists.
                'update_required' => version_compare($installed, $minimum, '<'),
                'download_url' => route('app.download.android'),
                'notes' => config('vidlix.app.android_notes'),
                'size_bytes' => $this->size(),
            ],
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    /** The number is read from the file so it cannot drift from what is served. */
    private function size(): ?int
    {
        $disk = Storage::disk('public');
        $path = 'releases/vidlix-android.apk';

        return $disk->exists($path) ? $disk->size($path) : null;
    }
}

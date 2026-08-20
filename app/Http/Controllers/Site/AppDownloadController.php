<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the Android build.
 *
 * Streamed through the app rather than linked straight at storage so the
 * filename stays stable, the download can be counted, and the file can move
 * disks later without the link in every installed app breaking.
 */
class AppDownloadController extends Controller
{
    private const PATH = 'releases/vidlix-android.apk';

    public function android(Request $request): Response
    {
        $disk = Storage::disk('public');

        abort_unless($disk->exists(self::PATH), 404, 'No Android build has been published yet.');

        $version = config('vidlix.app.android_version');

        return $disk->download(
            self::PATH,
            "vidlix-{$version}.apk",
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }
}

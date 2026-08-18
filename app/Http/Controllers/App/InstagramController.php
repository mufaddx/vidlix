<?php

namespace App\Http\Controllers\App;

use App\Contracts\InstagramProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\CreatorProfile;
use App\Services\Audit\AuditLogger;
use App\Services\Integrations\Instagram\MetaInstagramProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Meta OAuth connect/callback for creator Instagram accounts.
 *
 * Official Graph API only. Nothing here reads a page it was not granted access
 * to, and no metric is produced anywhere but a live Graph response.
 */
class InstagramController extends Controller
{
    public function connect(Request $request, InstagramProviderInterface $instagram): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile, 403);

        $url = $instagram->authorizationUrl($profile);
        if ($url === null) {
            return back()->with('status', __('Instagram is unavailable: Meta app credentials are not configured.'));
        }

        return redirect()->away($url);
    }

    public function callback(Request $request, InstagramProviderInterface $instagram, AuditLogger $audit): RedirectResponse
    {
        if (! $instagram instanceof MetaInstagramProvider) {
            return redirect()->route('app.instagram')
                ->with('status', __('Instagram is unavailable: Meta app credentials are not configured.'));
        }

        if (filled($request->query('error'))) {
            return redirect()->route('app.instagram')
                ->with('status', __('Instagram authorization was declined. Nothing was connected.'));
        }

        // The signed state is the only thing that says which creator this is;
        // a creator_id in the query string would be client-supplied and unsafe.
        $profileId = $instagram->creatorProfileIdFromState((string) $request->query('state', ''));
        $code = (string) $request->query('code', '');
        if ($profileId === null || $code === '') {
            return redirect()->route('app.instagram')
                ->with('status', __('That Instagram callback could not be verified. Please start again.'));
        }

        $profile = CreatorProfile::query()->findOrFail($profileId);
        abort_unless($this->mayActFor($request, $profile), 403);

        $result = $instagram->completeAuthorization($profile, $code);
        $audit->record('instagram.authorization', $profile, $result);

        return redirect()->route('app.instagram')->with('status', $result['detail']);
    }

    public function sync(Request $request, InstagramProviderInterface $instagram, AuditLogger $audit): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;
        abort_unless($profile, 403);

        $result = $instagram->syncPermittedData($profile);
        $audit->record('instagram.sync', $profile, ['status' => $result['status']]);

        return back()->with('status', $result['detail']);
    }

    /** A manager may connect on behalf of a creator they actively represent. */
    private function mayActFor(Request $request, CreatorProfile $profile): bool
    {
        $user = $request->user();
        if ($profile->user_id === $user->id) {
            return true;
        }

        return $user->managedCreators()
            ->where('creator_user_id', $profile->user_id)
            ->where('status', 'active')
            ->exists();
    }
}

<?php

namespace App\Http\Controllers\Managers;

use App\Http\Controllers\Controller;
use App\Models\ManagerInvitation;
use App\Models\User;
use App\Services\Managers\ManagerDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The public side of a manager invitation.
 *
 * Reachable without an account, because the whole point is that the invited
 * person may not have one yet. The emailed token is the only credential, so it
 * is looked up strictly and expires.
 */
class ManagerInvitationController extends Controller
{
    public function show(string $token, ManagerDirectory $directory): View
    {
        $invitation = $directory->findOpenInvitation($token);

        if (! $invitation) {
            return view('managers.invitation-invalid');
        }

        $existingUser = User::query()->where('email', $invitation->email)->first();

        return view('managers.invitation', [
            'invitation' => $invitation,
            'token' => $token,
            'owner' => $invitation->owner,
            // Someone who already has an account signs in instead of picking a
            // new password — we must not let a link reset an existing password.
            'needsAccount' => $existingUser === null,
            'signedInAsInvitee' => Auth::check() && strcasecmp((string) Auth::user()->email, $invitation->email) === 0,
        ]);
    }

    public function activate(Request $request, string $token, ManagerDirectory $directory): RedirectResponse
    {
        $invitation = $directory->findOpenInvitation($token);
        if (! $invitation) {
            return redirect()->route('login')->withErrors(['token' => __('That invitation is no longer valid.')]);
        }

        $existingUser = User::query()->where('email', $invitation->email)->first();

        // Already has an account: they must be signed in as that person. The
        // invitation link is not a way to take over an existing account.
        if ($existingUser) {
            if (! Auth::check() || strcasecmp((string) Auth::user()->email, $invitation->email) !== 0) {
                return redirect()->route('login')->with('status', __('Sign in as :email to accept this invitation.', [
                    'email' => $invitation->email,
                ]));
            }

            $directory->acceptAsExistingUser($invitation, Auth::user());

            return redirect()->route('dashboard')->with('status', $this->acceptedMessage($invitation));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = $directory->activateAsNewUser($invitation, $data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', $this->acceptedMessage($invitation));
    }

    private function acceptedMessage(ManagerInvitation $invitation): string
    {
        if ($invitation->isCompanyProvided()) {
            return __('You now manage this :scope account. Vidlix provided this arrangement.', [
                'scope' => $invitation->scope,
            ]);
        }

        return __('You now manage this :scope account. Use the account switcher to move between accounts.', [
            'scope' => $invitation->scope,
        ]);
    }
}

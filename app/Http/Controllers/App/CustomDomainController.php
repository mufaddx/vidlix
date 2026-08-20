<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Domains\CustomDomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Connecting your own domain to your contact form.
 *
 * As with the form builder, no route takes a domain id. Which record you are
 * acting on comes from your own account and active role, so there is nothing in
 * a request that could point at somebody else's hostname.
 */
class CustomDomainController extends Controller
{
    public function __construct(private CustomDomainService $domains) {}

    public function edit(Request $request): View
    {
        $scope = $this->scope($request);

        return view('app.custom-domain', [
            'domain' => $this->domains->forUser($request->user(), $scope),
            'scope' => $scope,
            'available' => $this->domains->isAvailable(),
            'providerName' => $this->domains->providerName(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $scope = $this->scope($request);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:253'],
        ]);

        $domain = $this->domains->connect($request->user(), $scope, $data['hostname']);

        return back()->with('status', __('Add the DNS record below, then choose Check status. :host goes live only once DNS and the certificate are both in place.', [
            'host' => $domain->hostname,
        ]));
    }

    public function refresh(Request $request): RedirectResponse
    {
        $domain = $this->domains->forUser($request->user(), $this->scope($request));

        abort_unless($domain !== null, 404);

        $this->domains->refresh($domain);

        return back()->with('status', __('Checked just now.'));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $domain = $this->domains->forUser($request->user(), $this->scope($request));

        abort_unless($domain !== null, 404);

        $this->domains->disconnect($domain, $request->user());

        return back()->with('status', __('Disconnected. You can remove the DNS record now.'));
    }

    private function scope(Request $request): string
    {
        $user = $request->user();
        $active = session('active_role');

        if ($active === 'editor' && $user->editorProfile !== null) {
            return 'editor';
        }

        if ($user->creatorProfile !== null) {
            return 'creator';
        }

        if ($user->editorProfile !== null) {
            return 'editor';
        }

        abort(403, __('Add a creator or editor profile before connecting a domain.'));
    }
}

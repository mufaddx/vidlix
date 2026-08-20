<?php

namespace App\Http\Middleware;

use App\Models\CreatorProfile;
use App\Models\CustomDomain;
use App\Models\EditorProfile;
use App\Services\Domains\CustomDomainService;
use App\Services\Domains\Hostname;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a request arriving on somebody's own domain is allowed to reach.
 *
 * The answer is: their contact form, and nothing else. Not the application, not
 * the admin panel, not the API, not another tenant's page. A custom hostname is
 * the one place where an outsider controls part of the routing input, so the
 * rule is a whitelist of two paths rather than a list of things to block.
 *
 * A hostname we do not recognise gets a 404 rather than the main site. Serving
 * the site on an unknown Host is how a stale DNS record belonging to somebody
 * else quietly becomes a page that looks like ours.
 */
class ResolveCustomDomain
{
    /** Set on the request so downstream code knows which tenant to serve. */
    public const ATTRIBUTE = 'vidlix_custom_domain';

    public function __construct(private CustomDomainService $domains) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = Hostname::normalise((string) $request->getHost());

        // One of ours: nothing to do, the ordinary router handles it.
        if ($this->isOurs($host)) {
            return $next($request);
        }

        /*
         | Only an ACTIVE row resolves. A domain still provisioning is not a
         | domain we serve, and treating "known to us" as "ready" is exactly how
         | a page gets published before a certificate exists for it.
         */
        $domain = $this->domains->resolveActive($host);

        if ($domain === null) {
            abort(404);
        }

        $path = trim($request->path(), '/');

        // The whitelist. Anything else on this hostname is refused outright,
        // including paths that would be perfectly ordinary on our own hosts.
        if (! in_array($path, ['', 'contact'], true)) {
            abort(404);
        }

        $username = $this->usernameFor($domain);

        if ($username === null) {
            abort(404);
        }

        $request->attributes->set(self::ATTRIBUTE, $domain);

        // Rewritten rather than redirected: the whole point of bringing your
        // own domain is that visitors stay on it.
        $request->server->set('REQUEST_URI', '/'.$username.'/contact');

        return $next($request->duplicate(
            query: $request->query->all(),
            request: $request->request->all(),
            attributes: $request->attributes->all(),
            cookies: $request->cookies->all(),
            files: $request->files->all(),
            server: $request->server->all(),
        ));
    }

    private function isOurs(string $host): bool
    {
        foreach (Hostname::ourOwnHostnames() as $ours) {
            if ($host === $ours || str_ends_with($host, '.'.$ours)) {
                return true;
            }
        }

        // Local development and tests run on hosts that are not in config.
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }

    /**
     * The handle whose form this hostname serves.
     *
     * Read from the owner's profile rather than stored on the domain row, so a
     * rename cannot leave the hostname pointing at an address that no longer
     * belongs to them.
     */
    private function usernameFor(CustomDomain $domain): ?string
    {
        // user_id is not nullable and the foreign key cascades, so there is
        // always an owner here; only the profile may be missing.
        $username = $domain->owner_scope === 'editor'
            ? EditorProfile::query()->where('user_id', $domain->user_id)->value('username')
            : CreatorProfile::query()->where('user_id', $domain->user_id)->value('username');

        return is_string($username) && $username !== '' ? $username : null;
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\Host;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps each host to its own part of the product.
 *
 * Four domains that all serve everything are four names for one website. The
 * separation only means something if a request on the AutoDM domain cannot
 * reach a creator's inbox, and a request on the landing site cannot reach a
 * dashboard.
 *
 * Requests are **redirected** rather than refused, because the person asking is
 * almost always in the right product and on the wrong address — somebody who
 * bookmarked a dashboard before the split, or followed a link from the landing
 * page. A 404 would be technically defensible and useless to them.
 *
 * The one exception is the admin panel, which is refused rather than redirected:
 * pointing somebody at the staff sign-in because they guessed a URL tells them
 * the staff sign-in is there.
 *
 * The site is described by what it hands away rather than by a list of its own
 * pages, because vidlix.in/{username} means the site serves nearly everything —
 * a whitelist there would have to be "everything except", which is a blocklist
 * wearing the wrong name.
 */
class RouteByHost
{
    /**
     * Paths every host serves.
     *
     * Health checks, webhooks and the OAuth callback are addressed by external
     * systems that were configured with one hostname and must not be moved.
     */
    private const SHARED = [
        'up', 'webhooks/*', 'api/*', 'sanctum/*', 'storage/*',
        'integrations/*', 'livewire/*', '_debugbar/*',
    ];

    /** What the AutoDM host serves, and nothing besides. */
    private const AUTODM_PATHS = [
        '/', 'autodm', 'autodm/*',
        // Sign-in has to work here, or the product cannot be entered.
        'login', 'logout', 'register', 'register/*', 'two-factor',
        'forgot-password', 'forgot-password/*', 'verify-email', 'verify-email/*',
        'instagram', 'instagram/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // One host in development and tests, so nothing may be routed away or
        // most of the application becomes unreachable.
        if (Host::isSingleHostEnvironment()) {
            return $next($request);
        }

        $face = Host::resolve((string) $request->getHost());

        /*
         | A hostname that is none of the four. Development, tests, a health
         | check hitting the server by IP — none of them should have most of
         | the application taken away, and guessing which face they meant would
         | do exactly that.
         */
        if ($face === null) {
            return $next($request);
        }

        $request->attributes->set(Host::ATTRIBUTE, $face);

        $path = trim($request->path(), '/') ?: '/';

        if ($this->matches($path, self::SHARED)) {
            return $next($request);
        }

        // The admin panel lives on its own host and is invisible from the
        // others. Refused, never redirected.
        if (str_starts_with($path, 'admin')) {
            if ($face !== Host::ADMIN) {
                abort(404);
            }

            return $next($request);
        }

        if ($face === Host::ADMIN) {
            // Nothing but the panel is served here, so a staff member who
            // wanders off it is sent back rather than shown the marketplace.
            return redirect(Host::urlFor(Host::ADMIN, 'admin'));
        }

        return match ($face) {
            Host::SITE => $this->fromSite($request, $path, $next),
            Host::AUTODM => $this->fromAutoDm($request, $path, $next),
            default => $this->fromApp($request, $path, $next),
        };
    }

    /**
     * The landing site.
     *
     * Signing in is not something the marketing site does — it hands the person
     * to the workspace, which is where their account actually lives.
     */
    private function fromSite(Request $request, string $path, Closure $next): Response
    {
        if ($this->isWorkspacePath($path)) {
            return redirect(Host::urlFor(Host::APP, $path));
        }

        if ($this->isAutoDmPath($path)) {
            return redirect(Host::urlFor(Host::AUTODM, $path));
        }

        return $next($request);
    }

    /**
     * The AutoDM host.
     *
     * Its own landing page at the root, its own dashboard, and nothing from the
     * marketplace — somebody here came for Instagram automation, not for an
     * editor's invoice.
     */
    private function fromAutoDm(Request $request, string $path, Closure $next): Response
    {
        if ($path === '/') {
            // The AutoDM product page, served in place of the marketplace one.
            $request->server->set('REQUEST_URI', '/autodm');

            return $next($request->duplicate(
                query: $request->query->all(),
                request: $request->request->all(),
                attributes: $request->attributes->all(),
                cookies: $request->cookies->all(),
                files: $request->files->all(),
                server: $request->server->all(),
            ));
        }

        if ($this->matches($path, self::AUTODM_PATHS)) {
            return $next($request);
        }

        // Anything else is either the marketplace or the public site. Both live
        // elsewhere, and the person is sent there rather than refused.
        return redirect(Host::urlFor(
            $this->isWorkspacePath($path) ? Host::APP : Host::SITE,
            $path,
        ));
    }

    /**
     * The workspace.
     *
     * Public marketing pages are sent back to the site so a shared link reads
     * as vidlix.in, and AutoDM is sent to its own host.
     */
    private function fromApp(Request $request, string $path, Closure $next): Response
    {
        if ($this->isAutoDmPath($path)) {
            return redirect(Host::urlFor(Host::AUTODM, $path));
        }

        if ($this->isPublicOnlyPath($path)) {
            return redirect(Host::urlFor(Host::SITE, $path));
        }

        // The root of the workspace is the dashboard, or the sign-in that leads
        // to it — never a marketing page.
        if ($path === '/') {
            return redirect(auth()->check() ? '/dashboard' : '/login');
        }

        return $next($request);
    }

    private function isAutoDmPath(string $path): bool
    {
        // The product page itself stays on the main site, where it is being
        // advertised; only the dashboard belongs to the AutoDM host.
        return $path === 'autodm/dashboard' || str_starts_with($path, 'autodm/');
    }

    private function isWorkspacePath(string $path): bool
    {
        foreach ([
            'dashboard', 'inbox', 'chat', 'projects', 'applications', 'campaigns',
            'negotiations', 'proposals', 'portfolio', 'invoices', 'earnings',
            'disputes', 'support', 'settings', 'notifications', 'roles', 'editor',
            'brand', 'discover', 'contact-form', 'custom-domain', 'creator',
            'app/', 'withdrawals', 'project-files', 'workspace',
            /*
             | Signing in belongs to the workspace, not to the marketing site.
             | The AutoDM host keeps its own copy of these — its whitelist is
             | consulted before this list is — because a product nobody can
             | sign in to from its own address is not a product.
             */
            'login', 'logout', 'register', 'two-factor', 'forgot-password',
            'verify-email',
        ] as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, rtrim($prefix, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    /** Marketing and public-profile pages, which belong to the main site. */
    private function isPublicOnlyPath(string $path): bool
    {
        return in_array($path, ['creators', 'editors', 'brands', 'campaigns', 'pricing', 'blog'], true)
            || str_starts_with($path, 'p/')
            || str_starts_with($path, 'blog/')
            || str_starts_with($path, 'brands/');
    }

    /** @param list<string> $patterns */
    private function matches(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                continue;
            }

            if ($path === $pattern || fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}

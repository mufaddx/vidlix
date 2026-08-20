<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\Username;
use App\Services\Forms\ContactFormBuilder;
use App\Services\Forms\PublicInquiries;
use App\Services\Identity\UsernameRegistry;
use App\Services\Taxonomy\CategoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * vidlix.in/{username} — one address for every public profile.
 *
 * The role is deliberately absent from the URL. A creator who also edits should
 * not have to explain which of two links is "the real one", and a link that
 * outlives the role it named is worse than no link at all.
 *
 * This controller is bound to a catch-all registered after every fixed route,
 * so anything the router already understands never reaches it. The reserved
 * list is the second line of that defence, for paths that will exist later.
 */
class PublicProfileController extends Controller
{
    public function __construct(
        private UsernameRegistry $registry,
        private ContactFormBuilder $forms,
    ) {}

    public function show(string $username, CategoryService $categories): View|RedirectResponse
    {
        $canonical = $this->registry->normalise($username);

        // vidlix.in/Asif and vidlix.in/asif are the same person, but only one of
        // them should be the address search engines and shares record.
        if ($canonical !== $username) {
            return redirect()->route('profile.show', $canonical, 301);
        }

        $profile = $this->registry->resolveProfile($canonical);

        if ($profile === null) {
            return $this->redirectToNewHandle($canonical);
        }

        if ($profile instanceof CreatorProfile) {
            return $this->creator($profile);
        }

        if ($profile instanceof EditorProfile) {
            return $this->editor($profile, $categories);
        }

        abort(404);
    }

    /** The contact form on its own, for people who were sent straight to it. */
    public function contact(string $username, CategoryService $categories): View|RedirectResponse
    {
        $canonical = $this->registry->normalise($username);

        if ($canonical !== $username) {
            return redirect()->route('profile.contact', $canonical, 301);
        }

        $profile = $this->registry->resolveProfile($canonical);

        if ($profile === null) {
            abort(404);
        }

        $this->assertReachable($profile);
        abort_unless($this->acceptsInquiries($profile), 404);

        return view('public.contact', [
            'profile' => $profile,
            'kind' => $profile instanceof CreatorProfile ? 'creator' : 'editor',
            'form' => $this->formSchema($profile),
            'honeypot' => config('vidlix.public_form_honeypot'),
        ]);
    }

    public function submit(string $username, Request $request, PublicInquiries $inquiries): RedirectResponse
    {
        $canonical = $this->registry->normalise($username);
        $profile = $this->registry->resolveProfile($canonical);

        if ($profile === null) {
            abort(404);
        }

        $this->assertReachable($profile);
        abort_unless($this->acceptsInquiries($profile), 404);

        /*
         | The owner is resolved from the URL, never from the request body. A
         | form that carries its own owner id is a form anyone can retarget at
         | somebody else's inbox.
         */
        $inquiries->submit($profile, $this->scopeOf($profile), $request->all(), $request->ip());

        return back()->with('inquiry_sent', true)
            ->with('status', __('Your message was sent. :name will reply from Vidlix.', [
                'name' => $profile->display_name,
            ]));
    }

    private function creator(CreatorProfile $creator): View
    {
        $this->assertReachable($creator);

        $creator->load(['publicPage.contactForm.versions', 'socialLinks.platform']);

        return view('public.creator', [
            'creator' => $creator,
            'page' => $creator->publicPage->published_payload,
            'form' => $this->formSchema($creator),
            'links' => $creator->socialLinks->where('is_visible', true),
            'honeypot' => config('vidlix.public_form_honeypot'),
        ]);
    }

    private function editor(EditorProfile $editor, CategoryService $categories): View
    {
        $this->assertReachable($editor);

        return view('public.editor', [
            'editor' => $editor,
            'categories' => $categories->forProfile($editor),
            'form' => $this->formSchema($editor),
            'honeypot' => config('vidlix.public_form_honeypot'),
        ]);
    }

    /**
     * A profile that exists but should not be shown.
     *
     * The response is the same 404 an unknown name gets, on purpose: telling a
     * stranger "this account exists but is hidden" is still telling them the
     * account exists.
     */
    private function assertReachable(Model $profile): void
    {
        if ($profile instanceof CreatorProfile) {
            abort_unless($profile->isPublished(), 404);

            return;
        }

        if ($profile instanceof EditorProfile) {
            abort_unless($profile->application_status === 'approved', 404);
            abort_unless($profile->visibility === 'public', 404);

            return;
        }

        abort(404);
    }

    private function scopeOf(Model $profile): string
    {
        return $profile instanceof CreatorProfile ? 'creator' : 'editor';
    }

    /**
     * Whether this profile is taking messages at all.
     *
     * Both halves have to agree: the owner's form must be on and published, and
     * an editor must not have switched enquiries off on their profile.
     */
    private function acceptsInquiries(Model $profile): bool
    {
        $owner = $profile->user;

        if ($owner === null) {
            return false;
        }

        if ($profile instanceof EditorProfile && ! $profile->accepts_inquiries) {
            return false;
        }

        $form = $this->forms->formFor($owner, $this->scopeOf($profile));

        return $this->forms->publishedVersion($form) !== null;
    }

    /** @return array<string, mixed> */
    private function formSchema(Model $profile): array
    {
        $owner = $profile->user;

        if ($owner === null) {
            return [];
        }

        $form = $this->forms->formFor($owner, $this->scopeOf($profile));

        return $this->forms->publishedVersion($form)?->schema_json ?? [];
    }

    /**
     * Somebody changed their handle and this is the old one.
     *
     * Redirecting beats a 404: the old address is printed on things nobody can
     * recall. It only fires when the name is genuinely retired and its holder
     * has a live handle now — otherwise it is an ordinary unknown name.
     */
    private function redirectToNewHandle(string $username): RedirectResponse
    {
        $previous = $this->registry->previousHolder($username);

        if ($previous !== null) {
            $current = Username::query()
                ->where('user_id', $previous->user_id)
                ->where('profile_type', $previous->profile_type)
                ->where('status', Username::ACTIVE)
                ->first();

            if ($current !== null) {
                return redirect()->route('profile.show', $current->username, 301);
            }
        }

        abort(404);
    }
}

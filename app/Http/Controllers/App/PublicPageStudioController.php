<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SocialPlatform;
use App\Services\Audit\AuditLogger;
use App\Services\Social\SocialUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPageStudioController extends Controller
{
    public function edit(Request $request): View
    {
        $profile = $request->user()->creatorProfile()->with(['publicPage.contactForm.versions', 'socialLinks.platform'])->firstOrFail();
        $platforms = SocialPlatform::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('app.public-page', compact('profile', 'platforms'));
    }

    public function saveDraft(Request $request, AuditLogger $audit): RedirectResponse
    {
        $profile = $request->user()->creatorProfile()->with('publicPage')->firstOrFail();
        $data = $request->validate([
            'hero_title' => ['required', 'string', 'max:160'],
            'hero_subtitle' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cta_text' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $page = $profile->publicPage;
        $draft = $page->draft_payload;
        $draft['hero_title'] = $data['hero_title'];
        $draft['hero_subtitle'] = $data['hero_subtitle'] ?? '';
        $draft['description'] = $data['description'] ?? '';
        $draft['cta_text'] = $data['cta_text'];
        $page->update(['draft_payload' => $draft]);
        $profile->update(['bio' => $data['bio'] ?? $profile->bio]);
        $audit->record('public_page.draft_saved', $page);

        return back()->with('status', __('Draft saved. Nothing is public until you publish.'));
    }

    public function publish(Request $request, AuditLogger $audit): RedirectResponse
    {
        $profile = $request->user()->creatorProfile()->with(['publicPage.contactForm'])->firstOrFail();
        $page = $profile->publicPage;
        $page->update([
            'published_payload' => $page->draft_payload,
            'published_at' => now(),
            'status' => 'published',
        ]);
        $profile->update([
            'visibility' => 'public',
            'profile_completion' => 80,
            'onboarding_step' => 'complete',
        ]);
        $audit->record('public_page.published', $page);

        return back()->with('status', __('Public page published.'));
    }

    public function addSocial(Request $request, SocialUrlResolver $resolver, AuditLogger $audit): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;
        $data = $request->validate([
            'social_platform_id' => ['required', 'exists:social_platforms,id'],
            'input_mode' => ['required', 'in:username,full_url'],
            'input_value' => ['required', 'string', 'max:255'],
        ]);
        $platform = SocialPlatform::query()->findOrFail($data['social_platform_id']);
        $url = $resolver->resolve($platform, $data['input_mode'], $data['input_value']);

        $profile->socialLinks()->create([
            'social_platform_id' => $platform->id,
            'input_mode' => $data['input_mode'],
            'input_value' => $data['input_value'],
            'resolved_url' => $url,
            'is_visible' => true,
            'sort_order' => $profile->socialLinks()->count(),
        ]);
        $audit->record('social_link.created', $profile);

        return back()->with('status', __('Social link added.'));
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CreatorProfile;
use App\Services\Creator\PublicInquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatorPublicController extends Controller
{
    public function show(string $username): View
    {
        $creator = CreatorProfile::query()
            ->where('username', $username)
            ->with(['publicPage.contactForm.versions', 'socialLinks.platform'])
            ->firstOrFail();

        abort_unless($creator->isPublished(), 404);

        $page = $creator->publicPage->published_payload;
        $form = $creator->publicPage->contactForm?->publishedVersion()?->schema_json ?? [];
        $links = $creator->socialLinks->where('is_visible', true);

        return view('public.creator', compact('creator', 'page', 'form', 'links'));
    }

    public function inquire(string $username, Request $request, PublicInquiryService $inquiries): RedirectResponse
    {
        $creator = CreatorProfile::query()->where('username', $username)->with('publicPage.contactForm.versions')->firstOrFail();
        abort_unless($creator->isPublished(), 404);

        $inquiries->submit($creator, $request->all(), $request->ip());

        return back()->with('inquiry_sent', true);
    }
}

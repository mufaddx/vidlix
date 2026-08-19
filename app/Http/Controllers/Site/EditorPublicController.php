<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\EditorProfile;
use App\Services\Creator\PublicEnquiryService;
use App\Services\Taxonomy\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * An editor's public page — the link they put in a bio.
 *
 * Same shape as the creator page: anyone can enquire without an account, and
 * the enquiry lands in the editor's Vidlix inbox rather than their personal
 * email.
 */
class EditorPublicController extends Controller
{
    public function show(string $username, CategoryService $categories): View
    {
        $editor = EditorProfile::query()
            ->where('username', $username)
            ->where('application_status', 'approved')
            ->firstOrFail();

        return view('public.editor', [
            'editor' => $editor,
            'categories' => $categories->forProfile($editor),
            'honeypot' => config('vidlix.public_form_honeypot'),
        ]);
    }

    public function enquire(Request $request, string $username, PublicEnquiryService $enquiries): RedirectResponse
    {
        $editor = EditorProfile::query()
            ->where('username', $username)
            ->where('application_status', 'approved')
            ->firstOrFail();

        abort_unless($editor->accepts_inquiries, 404);

        $enquiries->submit($editor, 'editor', $request->all(), $request->ip());

        return back()->with('status', __('Your enquiry was sent. :name will reply from Vidlix.', [
            'name' => $editor->display_name,
        ]));
    }
}

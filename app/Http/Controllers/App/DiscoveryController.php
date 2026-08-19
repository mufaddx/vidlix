<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\CreatorProfile;
use App\Notifications\GenericNotice;
use App\Services\Marketplace\CreatorDiscovery;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Brands finding creators, and opening a conversation with one.
 *
 * Connecting starts an internal thread — the conversation stays inside Vidlix
 * rather than leaking either side's email address.
 */
class DiscoveryController extends Controller
{
    public function index(Request $request, CreatorDiscovery $discovery): View
    {
        abort_unless($request->user()->brandProfile()->exists(), 403);

        $filters = $request->validate([
            'categories' => ['array'],
            'categories.*' => ['integer'],
            'min_followers' => ['nullable', 'integer', 'min:0'],
            'max_followers' => ['nullable', 'integer', 'min:0'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $creators = $discovery->search($filters);

        return view('app.discover', [
            'creators' => $creators,
            'categoryMap' => $discovery->categoriesFor($creators->items()),
            'categories' => $discovery->filterableCategories(),
            'selected' => $filters['categories'] ?? [],
            'filters' => $filters,
        ]);
    }

    public function connect(Request $request, CreatorProfile $creator, MarketplaceEngine $engine): RedirectResponse
    {
        $brand = $request->user()->brandProfile()->first();
        abort_unless($brand, 403);
        abort_unless($creator->visibility === 'public', 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = $engine->startInternalChat(
            $request->user(),
            $creator->user,
            $data['subject'],
        );
        $engine->postInternalMessage($conversation, $request->user(), $data['message']);

        $creator->user->notify(new GenericNotice('brand_connected', [
            'conversation_uuid' => $conversation->conversation_uuid,
            'brand' => $brand->company_name,
        ]));

        return redirect()->route('app.chat.show', $conversation->conversation_uuid)
            ->with('status', __('Conversation started with :name.', ['name' => $creator->display_name]));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CategoryAssignment;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Services\Marketplace\CreatorDiscovery;
use App\Services\Taxonomy\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The three audiences the panel is organised around.
 *
 * Each one is a self-contained section: opening it shows only that audience's
 * tools, so somebody working through creator verification is not looking at
 * brand campaigns at the same time.
 */
class AdminAudienceController extends Controller
{
    public function influencers(Request $request): View
    {
        $creators = CreatorProfile::query()
            ->with('user:id,name,email')
            ->when($request->query('q'), fn ($query, $term) => $query->where(function ($q) use ($term) {
                $q->where('display_name', 'like', '%'.$term.'%')->orWhere('username', 'like', '%'.$term.'%');
            }))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.influencers', [
            'creators' => $creators,
            'categoryMap' => app(CreatorDiscovery::class)->categoriesFor($creators->items()),
            'q' => $request->query('q'),
        ]);
    }

    public function brands(Request $request): View
    {
        return view('admin.brands', [
            'brands' => BrandProfile::query()
                ->with('user:id,name,email')
                ->when($request->query('q'), fn ($query, $term) => $query->where('company_name', 'like', '%'.$term.'%'))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'q' => $request->query('q'),
        ]);
    }

    public function brandCampaigns(): View
    {
        return view('admin.brand-campaigns', [
            'campaigns' => Campaign::query()->with('brand.user:id,name')->latest()->paginate(30),
        ]);
    }

    public function editors(Request $request): View
    {
        $editors = EditorProfile::query()
            ->with('user:id,name,email')
            ->when($request->query('q'), fn ($query, $term) => $query->where(function ($q) use ($term) {
                $q->where('display_name', 'like', '%'.$term.'%')->orWhere('username', 'like', '%'.$term.'%');
            }))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.editors', [
            'editors' => $editors,
            'categoryMap' => $this->categoriesFor($editors->items()),
            'q' => $request->query('q'),
        ]);
    }

    /** Category review, per audience. Proposals wait here for approval. */
    public function categories(Request $request, string $type): View
    {
        abort_unless(in_array($type, Category::TYPES, true), 404);

        return view('admin.categories', [
            'type' => $type,
            'pending' => Category::query()->ofType($type)->where('status', 'pending')->with('assignments')->latest()->get(),
            'approved' => Category::query()->ofType($type)->approved()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function decideCategory(Request $request, Category $category, CategoryService $categories): RedirectResponse
    {
        $decision = $request->validate(['decision' => ['required', 'in:approve,reject']])['decision'];

        if ($decision === 'approve') {
            $categories->approve($category);

            return back()->with('status', __('":name" is now in the public list.', ['name' => $category->name]));
        }

        // Rejecting leaves it attached to whoever proposed it — removing it
        // would silently strip a category from their profile.
        $category->update(['status' => 'rejected']);

        return back()->with('status', __('":name" was rejected and stays out of the public list.', ['name' => $category->name]));
    }

    /** @return array<int, array<int, string>> */
    private function categoriesFor(iterable $profiles): array
    {
        $ids = collect($profiles)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $rows = CategoryAssignment::query()
            ->where('categorizable_type', EditorProfile::class)
            ->whereIn('categorizable_id', $ids)
            ->with('category:id,name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->categorizable_id][] = $row->category?->name ?? '';
        }

        return $map;
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BrandProfile;
use App\Models\Campaign;
use App\Models\CmsPage;
use App\Models\CommissionRule;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\Faq;
use App\Models\FeaturedCreator;
use App\Models\HomepageSection;
use App\Models\Testimonial;
use App\Services\AutoDm\Capabilities;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $sections = HomepageSection::query()->where('is_visible', true)->orderBy('sort_order')->get()->keyBy('key');
        $faqs = Faq::query()->where('is_published', true)->orderBy('sort_order')->get();
        $testimonials = Testimonial::query()->where('is_published', true)->orderBy('sort_order')->get();
        $featured = FeaturedCreator::query()
            ->where('is_active', true)
            ->whereHas('creatorProfile', fn ($q) => $q->where('visibility', 'public'))
            ->with('creatorProfile')
            ->orderByDesc('priority')
            ->get();
        $topCreators = CreatorProfile::query()->where('visibility', 'public')->limit(6)->get();
        $topEditors = EditorProfile::query()->where('application_status', 'approved')->limit(6)->get();
        $topBrands = BrandProfile::query()->where('verification_status', 'verified')->limit(6)->get();
        $openCampaigns = Campaign::query()->where('status', 'published')->latest()->limit(4)->get();
        $commission = CommissionRule::query()->where('is_active', true)->where('slug', 'default')->value('bps');

        // Counted, not claimed. A landing page that invents its numbers is the
        // same lie as a dashboard that invents a balance, so these are real
        // rows and a small site is allowed to look small.
        $counts = [
            'creators' => CreatorProfile::query()->where('visibility', 'public')->count(),
            'editors' => EditorProfile::query()->where('application_status', 'approved')->count(),
            'brands' => BrandProfile::query()->where('verification_status', 'verified')->count(),
            'campaigns' => Campaign::query()->where('status', 'published')->count(),
        ];

        return view('public.home', compact(
            'sections', 'faqs', 'testimonials', 'featured',
            'topCreators', 'topEditors', 'topBrands', 'openCampaigns', 'counts'
        ));
    }

    public function page(string $slug): View
    {
        $page = CmsPage::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('public.page', compact('page'));
    }

    /**
     * The AutoDM product page.
     *
     * Its job is to be honest about limits as much as to sell: somebody who
     * reads this and then finds out from an empty log that Instagram bounds
     * private replies to a window has been misled, whatever the page said about
     * everything else.
     */
    public function autodm(): View
    {
        return view('public.autodm', [
            'windowHours' => Capabilities::PRIVATE_REPLY_WINDOW_HOURS,
        ]);
    }

    public function pricing(): View
    {
        // The one number that is actually charged. Read from the rule the
        // ledger uses, so the page cannot quote a rate the platform does not
        // apply.
        $commission = CommissionRule::query()->where('is_active', true)->where('slug', 'default')->value('bps');

        return view('public.pricing', ['commissionBps' => $commission]);
    }

    public function creators(): View
    {
        $creators = CreatorProfile::query()
            ->where('visibility', 'public')
            ->when(request('q'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('display_name', 'like', '%'.$term.'%')
                        ->orWhere('username', 'like', '%'.$term.'%')
                        ->orWhere('bio', 'like', '%'.$term.'%');
                });
            })
            ->orderBy('display_name')
            ->paginate(12)
            ->withQueryString();

        return view('public.creators', compact('creators'));
    }

    public function editors(): View
    {
        $editors = EditorProfile::query()->where('application_status', 'approved')->orderBy('display_name')->paginate(12);

        return view('public.directory', [
            'title' => __('Editors'),
            'empty' => __('No approved editors yet.'),
            'items' => $editors,
            'href' => fn ($e) => route('profile.show', $e->username),
            'name' => fn ($e) => $e->display_name,
            'meta' => fn ($e) => '@'.$e->username,
        ]);
    }

    public function editorShow(string $username): View
    {
        $editor = EditorProfile::query()->where('username', $username)->where('application_status', 'approved')->firstOrFail();

        return view('public.editor', compact('editor'));
    }

    public function brands(): View
    {
        $brands = BrandProfile::query()->where('verification_status', 'verified')->orderBy('company_name')->paginate(12);

        return view('public.directory', [
            'title' => __('Brands'),
            'empty' => __('No verified brands yet.'),
            'items' => $brands,
            'href' => fn ($b) => route('brands.public', $b->slug),
            'name' => fn ($b) => $b->company_name,
            'meta' => fn ($b) => $b->industry,
        ]);
    }

    public function brandShow(string $slug): View
    {
        $brand = BrandProfile::query()->where('slug', $slug)->where('verification_status', 'verified')->firstOrFail();

        return view('public.brand', compact('brand'));
    }

    public function campaigns(): View
    {
        $campaigns = Campaign::query()->where('status', 'published')->latest()->paginate(12);

        return view('public.campaigns', compact('campaigns'));
    }

    public function blog(): View
    {
        $posts = BlogPost::query()->where('status', 'published')->latest('published_at')->paginate(10);

        return view('public.blog', compact('posts'));
    }

    public function post(string $slug): View
    {
        $post = BlogPost::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('public.post', compact('post'));
    }
}

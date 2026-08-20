@extends('layouts.public')

@section('title', ($sections['hero']->title ?? config('app.name')).' — marketplace')
@section('meta_description', $sections['hero']->subtitle ?? 'Discover creators, hire editors, run brand campaigns, and get paid properly.')

@section('content')
<section class="hero-stage">
    <div class="wrap hero">
        <div>
            <p class="kicker">{{ __('Creator × Editor × Brand') }}</p>
            <h1>{{ $sections['hero']->title ?? __('The production desk for creators, brands and editors') }}</h1>
            <p class="lede">{{ $sections['hero']->subtitle ?? __('Discover talent, agree terms in the open, invoice properly, and get paid on time.') }}</p>
            <form class="search" action="{{ route('creators.index') }}" method="get" role="search">
                <label class="hp" for="q">{{ __('Search creators') }}</label>
                <input id="q" name="q" type="search" placeholder="{{ __('Search creators by name, niche, or handle') }}" value="{{ request('q') }}">
                <button class="btn" type="submit">{{ __('Search') }}</button>
            </form>
            <p class="hero-actions">
                <a class="btn" href="{{ route('register') }}">{{ __('List your profile') }}</a>
                <a class="btn secondary" href="{{ route('campaigns.index') }}">{{ __('Browse campaigns') }}</a>
            </p>
            <div class="role-strip">
                <span>{{ __('Creators') }}</span>
                <span>{{ __('Editors') }}</span>
                <span>{{ __('Brands') }}</span>
            </div>
        </div>
        <aside class="hero-card">
            <p class="kicker">{{ __('Protected collaboration') }}</p>
            <p>{{ __('One loop. Real state.') }}</p>
            <ol>
                <li>{{ __('Discover verified talent') }}</li>
                <li>{{ __('Propose and counter in versioned offers') }}</li>
                <li>{{ __('Agree, invoice, pay securely') }}</li>
                <li>{{ __('Deliver, revise, settle, review') }}</li>
            </ol>
        </aside>
    </div>
    <div class="wrap trust">
        <article>
            <p class="stat">4</p>
            <p class="muted">{{ __('Roles, one identity') }}</p>
        </article>
        <article>
            <p class="stat">{{ __('Ledger') }}</p>
            <p class="muted">{{ __('No invented wallet balances') }}</p>
        </article>
        <article>
            <p class="stat">{{ __('Meta') }}</p>
            <p class="muted">{{ __('Official Instagram APIs only') }}</p>
        </article>
        <article>
            <p class="stat">{{ __('Audit') }}</p>
            <p class="muted">{{ __('Who, what, when, which deal') }}</p>
        </article>
    </div>
</section>

<section class="wrap section stats-band">
    {{-- Counted from the database, never claimed. A small site is allowed to
         look small; inventing a number here would be the same lie as inventing
         a wallet balance. --}}
    <div class="stats-row">
        <div class="stat-cell">
            <span class="stat-figure">{{ number_format($counts['creators']) }}</span>
            <span class="stat-label">{{ trans_choice('published creator|published creators', $counts['creators']) }}</span>
        </div>
        <div class="stat-cell">
            <span class="stat-figure">{{ number_format($counts['editors']) }}</span>
            <span class="stat-label">{{ trans_choice('approved editor|approved editors', $counts['editors']) }}</span>
        </div>
        <div class="stat-cell">
            <span class="stat-figure">{{ number_format($counts['brands']) }}</span>
            <span class="stat-label">{{ trans_choice('verified brand|verified brands', $counts['brands']) }}</span>
        </div>
        <div class="stat-cell">
            <span class="stat-figure">{{ number_format($counts['campaigns']) }}</span>
            <span class="stat-label">{{ trans_choice('open campaign|open campaigns', $counts['campaigns']) }}</span>
        </div>
    </div>
</section>

<section class="wrap section" id="money">
    <div class="section-head">
        <div>
            <p class="kicker">{{ __('Trust') }}</p>
            <h2>{{ __('How the money works') }}</h2>
        </div>
    </div>
    <div class="grid">
        <article class="card">
            <h3>{{ __('Nothing is marked paid until the bank confirms it') }}</h3>
            <p class="muted">{{ __('A payment is confirmed by the bank or card network itself, never by us. Nobody at Vidlix can mark money as received.') }}</p>
        </article>
        <article class="card">
            <h3>{{ __('The ledger is the record') }}</h3>
            <p class="muted">{{ __('Every rupee lands in an append-only ledger. Balances you see are added up from it, never stored and edited.') }}</p>
        </article>
        <article class="card">
            <h3>{{ __('Invoices for both sides') }}</h3>
            <p class="muted">{{ __('Each settled piece of work produces a real invoice PDF for the payer and the payee, with GST fields where they apply.') }}</p>
        </article>
    </div>
</section>

<section class="wrap section" id="how-it-works">
    <div class="section-head">
        <div>
            <p class="kicker">{{ __('Product') }}</p>
            <h2>{{ __('How it works') }}</h2>
        </div>
        <a href="{{ route('pages.show', 'how-it-works') }}">{{ __('Read the full flow') }}</a>
    </div>
    <div class="grid">
        <article class="card"><h3>{{ __('Creators') }}</h3><p class="muted">{{ __('A public media kit, optional Instagram connection, and an enquiry form anyone can use.') }}</p></article>
        <article class="card"><h3>{{ __('Editors') }}</h3><p class="muted">{{ __('Apply, get verified, then run structured projects with files and revision limits.') }}</p></article>
        <article class="card"><h3>{{ __('Brands') }}</h3><p class="muted">{{ __('Verify the company, publish campaigns, compare applicants, then negotiate.') }}</p></article>
        <article class="card"><h3>{{ __('Instagram AutoDM') }}</h3><p class="muted">{{ __('Turn comments into conversations with keyword automations, using official Instagram APIs only.') }}</p></article>
        <article class="card"><h3>{{ __('Instagram intelligence') }}</h3><p class="muted">{{ __('Official Instagram data only, with your permission. Numbers are never invented.') }}</p></article>
        <article class="card"><h3>{{ __('Protected payments') }}</h3><p class="muted">{{ __('Money is held safely until the work is accepted, then released.') }}</p></article>
    </div>
</section>

<section class="wrap section">
    <div class="section-head">
        <div>
            <p class="kicker">{{ __('Directory') }}</p>
            <h2>{{ __('Top creators') }}</h2>
        </div>
        <a href="{{ route('creators.index') }}">{{ __('All creators') }}</a>
    </div>
    <div class="grid">
        @forelse($featured->isNotEmpty() ? $featured : $topCreators as $item)
            @php $creator = $item->creatorProfile ?? $item; @endphp
            <a class="card" href="{{ route('profile.show', $creator->username) }}">
                <span class="avatar" aria-hidden="true">{{ strtoupper(substr($creator->display_name, 0, 1)) }}</span>
                <strong>{{ $creator->display_name }}</strong>
                <p class="muted">{{ '@'.$creator->username }}</p>
                <p>{{ \Illuminate\Support\Str::limit($creator->bio, 110) }}</p>
            </a>
        @empty
            <p class="muted">{{ __('No published creators yet.') }}</p>
        @endforelse
    </div>
</section>

<section class="wrap section">
    <div class="section-head">
        <div>
            <h2>{{ __('Top editors') }}</h2>
            <p class="muted">{{ __('Verified editing talent.') }}</p>
        </div>
        <a href="{{ route('editors.index') }}">{{ __('All editors') }}</a>
    </div>
    <div class="grid">
        @forelse($topEditors as $editor)
            <a class="card" href="{{ route('profile.show', $editor->username) }}">
                <span class="avatar" aria-hidden="true">{{ strtoupper(substr($editor->display_name, 0, 1)) }}</span>
                <strong>{{ $editor->display_name }}</strong>
                <p class="muted">{{ implode(' · ', $editor->specializations ?? []) }}</p>
            </a>
        @empty
            <p class="muted">{{ __('No approved editors yet.') }}</p>
        @endforelse
    </div>
</section>

<section class="wrap section">
    <div class="section-head">
        <div>
            <h2>{{ __('Top brands') }}</h2>
        </div>
        <a href="{{ route('brands.index') }}">{{ __('All brands') }}</a>
    </div>
    <div class="grid">
        @forelse($topBrands as $brand)
            <a class="card" href="{{ route('brands.public', $brand->slug) }}">
                <strong>{{ $brand->company_name }}</strong>
                <p class="muted">{{ $brand->industry }}</p>
            </a>
        @empty
            <p class="muted">{{ __('No verified brands yet.') }}</p>
        @endforelse
    </div>
</section>

<section class="wrap section">
    <div class="section-head">
        <div>
            <h2>{{ __('Featured campaigns') }}</h2>
        </div>
        <a href="{{ route('campaigns.index') }}">{{ __('All campaigns') }}</a>
    </div>
    <div class="grid">
        @forelse($openCampaigns as $campaign)
            <article class="card">
                <p class="kicker">{{ $campaign->platform }}</p>
                <h3>{{ $campaign->name }}</h3>
                <p class="muted">{{ \Illuminate\Support\Str::limit($campaign->brief, 140) }}</p>
                <a href="{{ route('campaigns.index') }}">{{ __('View brief') }}</a>
            </article>
        @empty
            <p class="muted">{{ __('No published campaigns.') }}</p>
        @endforelse
    </div>
</section>

<section class="wrap section">
    <div class="section-head">
        <div>
            <h2>{{ __('Instagram AutoDM') }}</h2>
            <p class="muted">{{ __('Turn comments on your posts and reels into conversations, automatically.') }}</p>
        </div>
        <a href="{{ config('vidlix.domains.autodm') }}">{{ __('See AutoDM') }}</a>
    </div>
    <div class="grid">
        <article class="card">
            <h3>{{ __('Keyword triggers') }}</h3>
            <p class="muted">{{ __('Pick a post, choose the words to watch for, and write the reply once. Every matching comment gets it.') }}</p>
        </article>
        <article class="card">
            <h3>{{ __('Official APIs only') }}</h3>
            <p class="muted">{{ __('Connected through Instagram itself. We never ask for your password, and nothing is scraped or automated behind its back.') }}</p>
        </article>
        <article class="card">
            <h3>{{ __('Honest about limits') }}</h3>
            <p class="muted">{{ __('Instagram allows a reply to someone who commented, within a limited window. We show you exactly what will and will not be sent before you turn it on.') }}</p>
        </article>
    </div>
</section>

<section class="wrap section">
    <h2>{{ __('What teams say') }}</h2>
    <div class="grid">
        @forelse($testimonials as $t)
            <article class="card">
                <p>{{ $t->quote }}</p>
                <p class="muted">{{ $t->author_name }} · {{ $t->author_role }}</p>
            </article>
        @empty
            <p class="muted">{{ __('Testimonials are CMS-managed.') }}</p>
        @endforelse
    </div>
</section>

<section class="wrap section faq" id="faq">
    <div class="section-head">
        <div>
            <p class="kicker">{{ __('Support') }}</p>
            <h2>{{ __('FAQ') }}</h2>
        </div>
        <a href="{{ route('pages.show', 'faq') }}">{{ __('All answers') }}</a>
    </div>
    @forelse($faqs as $faq)
        <details>
            <summary>{{ $faq->question }}</summary>
            <p>{{ $faq->answer }}</p>
        </details>
    @empty
        <p class="muted">{{ __('No published FAQs yet.') }}</p>
    @endforelse
</section>

<section class="cta-band band">
    <div class="wrap">
        <p class="kicker">{{ __('Get started') }}</p>
        <h2>{{ __('Publish a desk the industry can actually work with.') }}</h2>
        <p class="lede" style="margin-inline:auto;">{{ __('Join as a creator, an editor, or a brand — on one account.') }}</p>
        <p style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px;">
            <a class="btn" href="{{ route('register') }}">{{ __('Create account') }}</a>
            <a class="btn secondary" href="{{ route('pages.show', 'contact') }}">{{ __('Talk to us') }}</a>
        </p>
    </div>
</section>
@endsection

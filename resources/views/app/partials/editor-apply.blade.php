{{--
    The editor application.

    Saving, accepting the terms and submitting are three separate acts, and the
    form is laid out to make that obvious. Ticking a box does not put anybody in
    the marketplace; a person reads the application and decides.
--}}

@if($profile)
    <div class="card" style="margin-bottom:16px">
        <p class="kicker">{{ __('Application') }}</p>
        <p><span class="chip">{{ $profile->statusLabel() }}</span></p>

        @if($profile->application_status === \App\Models\EditorProfile::MORE_INFO)
            @include('partials.state', [
                'state' => 'more_info',
                'detail' => $profile->review_note,
            ])
        @elseif($profile->application_status === \App\Models\EditorProfile::REJECTED)
            @include('partials.state', [
                'state' => 'verification_rejected',
                'detail' => $profile->review_note,
            ])
        @elseif($profile->isAwaitingDecision())
            @include('partials.state', [
                'state' => 'verification_pending',
                'detail' => $profile->submitted_at
                    ? __('Sent :when.', ['when' => $profile->submitted_at->diffForHumans()])
                    : null,
            ])
        @elseif($profile->application_status === \App\Models\EditorProfile::SUSPENDED)
            @include('partials.state', [
                'state' => 'suspended',
                'detail' => $profile->review_note,
            ])
        @elseif($profile->isApproved())
            <p class="muted">{{ __('You are listed in the marketplace.') }}</p>
            @if($profile->username)
                <p><a class="btn secondary" href="{{ \App\Support\PublicUrl::profile($profile->username) }}">{{ __('View your public page') }}</a></p>
            @endif
        @endif
    </div>
@endif

@if($profile && ! $profile->isEditable())
    {{-- Read-only while somebody is reading it. Editing underneath a reviewer
         would mean they decide on something that no longer exists. --}}
    <p class="muted">{{ __('Your application cannot be changed while it is being reviewed.') }}</p>
@else
    <form class="form" method="post" action="{{ route('app.editors.apply') }}">
        @csrf

        <h2>{{ __('About you') }}</h2>

        <label for="display_name">{{ __('Name') }}</label>
        <input id="display_name" name="display_name" maxlength="120"
               value="{{ old('display_name', $profile?->display_name) }}">

        <label for="bio">{{ __('Short bio') }}</label>
        <textarea id="bio" name="bio" maxlength="3000">{{ old('bio', $profile?->bio) }}</textarea>

        <label for="years_experience">{{ __('Years doing this') }}</label>
        <input id="years_experience" name="years_experience" type="number" min="0" max="70"
               value="{{ old('years_experience', $profile?->years_experience) }}">

        <label for="location">{{ __('Where you are') }}</label>
        <input id="location" name="location" maxlength="160"
               value="{{ old('location', $profile?->location) }}">

        <label for="languages">{{ __('Languages you work in, one per line') }}</label>
        <textarea id="languages" name="languages">{{ old('languages', implode("\n", $profile?->languages ?? [])) }}</textarea>

        <h2>{{ __('What you do') }}</h2>

        <label for="specializations">{{ __('What you specialise in, one per line') }}</label>
        <textarea id="specializations" name="specializations"
                  placeholder="Short form&#10;Documentary&#10;Colour grading">{{ old('specializations', implode("\n", $profile?->specializations ?? [])) }}</textarea>

        <label for="software">{{ __('Software you use, one per line') }}</label>
        <textarea id="software" name="software"
                  placeholder="Premiere Pro&#10;DaVinci Resolve&#10;After Effects">{{ old('software', implode("\n", $profile?->software ?? [])) }}</textarea>

        <label for="services">{{ __('Services you offer, one per line') }}</label>
        <textarea id="services" name="services"
                  placeholder="Reel editing&#10;Long-form editing&#10;Thumbnail design">{{ old('services', implode("\n", $profile?->services ?? [])) }}</textarea>

        <label for="portfolio_url">{{ __('A link to your work') }}</label>
        <input id="portfolio_url" name="portfolio_url" maxlength="2000"
               value="{{ old('portfolio_url', $profile?->portfolio_url) }}" placeholder="https://">

        <h2>{{ __('Working with you') }}</h2>

        <label for="starting_price_minor">{{ __('Starting price, in paise') }}</label>
        <input id="starting_price_minor" name="starting_price_minor" type="number" min="0"
               value="{{ old('starting_price_minor', $profile?->starting_price_minor) }}">
        <p class="muted">{{ __('Paise, not rupees — ₹5,000 is 500000.') }}</p>

        <label for="availability">{{ __('Your availability') }}</label>
        <input id="availability" name="availability" maxlength="120"
               value="{{ old('availability', $profile?->availability) }}"
               placeholder="{{ __('Two projects a month') }}">

        @unless($profile?->terms_accepted_at)
            <label class="checkbox">
                <input type="checkbox" name="accept_terms" value="1">
                {!! __('I accept the :terms', [
                    'terms' => '<a href="'.route('pages.show', 'editor-terms').'">'.__('editor terms').'</a>',
                ]) !!}
            </label>
            {{-- Said plainly, because the opposite assumption is the common one. --}}
            <p class="muted">{{ __('Accepting the terms does not list you. A person reviews every application.') }}</p>
        @else
            <p class="muted">{{ __('Terms accepted :when.', ['when' => $profile->terms_accepted_at->diffForHumans()]) }}</p>
        @endunless

        <button class="btn secondary" type="submit">{{ __('Save') }}</button>
    </form>

    @if($profile)
        @php($missing = $profile->missingForSubmission())

        <form method="post" action="{{ route('app.editors.submit') }}" style="margin-top:16px">
            @csrf
            <button class="btn" type="submit" @disabled($missing !== [])>
                {{ __('Submit for review') }}
            </button>

            @if($missing !== [])
                {{-- Names what is missing rather than saying "incomplete", so
                     nobody has to hunt for the field. --}}
                <p class="muted">{{ __('Still needed: :list.', ['list' => implode(', ', $missing)]) }}</p>
            @endif
        </form>
    @endif
@endif

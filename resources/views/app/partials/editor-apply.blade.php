<form class="form" method="post" action="{{ route('app.editors.apply') }}">
    @csrf
    <p class="muted">{{ $profile?->application_status ?? __('no profile') }}</p>
    <label>{{ __('Bio') }}<textarea name="bio" required>{{ $profile?->bio }}</textarea></label>
    <label>{{ __('Software (comma)') }}<input name="software" value="{{ implode(',', $profile?->software ?? []) }}"></label>
    <label>{{ __('Specializations (comma)') }}<input name="specializations" value="{{ implode(',', $profile?->specializations ?? []) }}"></label>
    <label>{{ __('Starting price (paise)') }}<input name="starting_price_minor" type="number"></label>
    <button class="btn" type="submit">{{ __('Apply for editor access') }}</button>
</form>

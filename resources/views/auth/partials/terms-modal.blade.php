{{--
    Role-specific terms. Only the selected role's block is shown, because an
    influencer, an editor and a brand are agreeing to genuinely different
    things and one shared wall of text means nobody reads their own part.
--}}
<div class="modal" data-terms-modal hidden role="dialog" aria-modal="true" aria-labelledby="terms-title">
    <div class="modal-card">
        <div class="modal-head">
            @foreach($terms as $key => $role)
                <span class="modal-role" data-terms-for="{{ $key }}" hidden>{{ $role['label'] }}</span>
            @endforeach
            <h2 id="terms-title">{{ __('Terms & Conditions') }}</h2>
            @foreach($terms as $key => $role)
                <p data-terms-for="{{ $key }}" hidden>{{ $role['intro'] }}</p>
            @endforeach
        </div>

        <div class="modal-body">
            @foreach($terms as $key => $role)
                <div data-terms-for="{{ $key }}" hidden>
                    @foreach($role['points'] as $point)
                        <div class="term">
                            <h3>{{ $point['title'] }}</h3>
                            <p>{{ $point['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="modal-foot">
            <button class="btn" type="button" data-terms-accept>{{ __('I agree & accept') }}</button>
            <button class="btn ghost" type="button" data-terms-close>{{ __('Close') }}</button>
            <p class="modal-note">
                {{ __('This is a plain-language summary, not the binding agreement. The full policies are at') }}
                <a href="{{ route('pages.show', 'terms') }}" target="_blank" rel="noopener">{{ __('/p/terms') }}</a>.
            </p>
        </div>
    </div>
</div>

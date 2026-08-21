{{--
    The terms of one offer. Shared by the opening offer and every counter, so
    the two cannot drift into asking for different things.

    Prefilled from the offer on the table where there is one: a counter is
    usually a small change to what was proposed, not a blank page.
--}}
<label for="amount_minor">{{ __('Amount, in paise') }}</label>
<input id="amount_minor" name="amount_minor" type="number" min="1" required
       value="{{ old('amount_minor', $offer?->amount_minor) }}">
<p class="muted">{{ __('Paise, not rupees — ₹5,000 is 500000. Money is stored in the smallest unit so nothing is ever lost to rounding.') }}</p>

<label for="deliverables">{{ __('Deliverables, one per line') }}</label>
<textarea id="deliverables" name="deliverables"
          placeholder="2 reels&#10;4 stories&#10;1 grid post">{{ old('deliverables', implode("\n", $offer?->deliverables ?? [])) }}</textarea>
<p class="muted">{{ __('Each line becomes a milestone once the offer is accepted.') }}</p>

<label for="deadline">{{ __('Deadline') }}</label>
<input id="deadline" name="deadline" type="date" value="{{ old('deadline', $offer?->deadline?->toDateString()) }}">

<label for="revision_limit">{{ __('Revisions included') }}</label>
<input id="revision_limit" name="revision_limit" type="number" min="0" max="20"
       value="{{ old('revision_limit', $offer?->revision_limit) }}">

<label for="usage_rights">{{ __('Usage rights') }}</label>
<textarea id="usage_rights" name="usage_rights" maxlength="2000">{{ old('usage_rights', $offer?->usage_rights) }}</textarea>

<label for="note">{{ __('Anything else') }} <span class="muted">{{ __('(optional)') }}</span></label>
<textarea id="note" name="note" maxlength="2000">{{ old('note') }}</textarea>

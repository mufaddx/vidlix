{{--
    The OTP stage: six digit boxes that collapse inward as an orbit rises in
    their place while the code is checked, then settle into a tick.

    One stage, three states — entering, verifying, done — so the transition
    reads as the same object changing rather than two views being swapped.
--}}
<div class="otp-stage" data-otp-stage>
    <div class="otp-boxes" data-otp>
        @for($i = 0; $i < 6; $i++)
            <input type="text"
                   inputmode="numeric"
                   autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                   maxlength="1"
                   aria-label="{{ __('Digit :n of 6', ['n' => $i + 1]) }}"
                   @if($i === 0) autofocus @endif>
        @endfor
    </div>

    <div class="orbit" aria-hidden="true">
        <svg viewBox="0 0 100 100">
            <circle class="orbit-ring" cx="50" cy="50" r="34"/>
            <circle class="orbit-arc slow" cx="50" cy="50" r="42"/>
            <circle class="orbit-arc" cx="50" cy="50" r="34"/>
            <g class="orbit-moon">
                <circle cx="84" cy="50" r="4"/>
            </g>
            <circle class="orbit-hub" cx="50" cy="50" r="7"/>
            <path class="orbit-check" d="M36 51 L46 61 L65 41"/>
        </svg>
    </div>
</div>

<p class="auth-sub" style="text-align:center" role="status" aria-live="polite">
    {{ __('Enter the 6-digit code we sent to') }} <strong data-otp-target>{{ $target ?? '' }}</strong>
</p>

<div class="otp-meta">
    <span class="otp-timer" data-otp-timer></span>
    <button type="button" class="btn link" data-otp-resend disabled>{{ __('Resend code') }}</button>
</div>

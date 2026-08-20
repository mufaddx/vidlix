@extends('layouts.app')
@section('title', __('Custom domain'))
@section('content')

<h1>{{ __('Use your own domain') }}</h1>
<p class="muted">{{ __('Point a domain you own at your contact form, so people reach you at your address instead of ours.') }}</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

@unless($available)
    {{-- Said plainly and up front. Someone who changes their DNS and then finds
         out nothing is listening has done real work for nothing. --}}
    <div class="card">
        <h2>{{ __('Not available yet') }}</h2>
        <p class="muted">
            {{ __('Custom domains need a certificate provider, and none is configured on this installation yet. Nothing you enter here would be served, so the form is switched off rather than left to fail quietly.') }}
        </p>
    </div>
@else

    @if($domain)
        <div class="card" style="margin-bottom:16px">
            <p class="kicker">{{ __('Your domain') }}</p>
            <p class="stat" style="font-size:18px;word-break:break-all">{{ $domain->hostname }}</p>

            <p>
                <span class="chip">{{ $domain->statusLabel() }}</span>
                @if($domain->last_checked_at)
                    <span class="muted">{{ __('Checked :when', ['when' => $domain->last_checked_at->diffForHumans()]) }}</span>
                @endif
            </p>

            @if($domain->last_error)
                <p class="error">{{ $domain->last_error }}</p>
            @endif

            {{-- Three separate facts, shown separately. "Nearly active" is not
                 a state a browser recognises. --}}
            @php($steps = $domain->completedSteps())
            <ul>
                <li>{{ $steps['dns'] ? '✓' : '○' }} {{ __('DNS points at us') }}</li>
                <li>{{ $steps['ownership'] ? '✓' : '○' }} {{ __('Ownership verified') }}</li>
                <li>{{ $steps['ssl'] ? '✓' : '○' }} {{ __('Certificate issued') }}</li>
            </ul>

            @if($domain->isActive())
                <p><a class="btn secondary" href="https://{{ $domain->hostname }}">{{ __('Open it') }}</a></p>
            @else
                <p class="muted">{{ __('Your form stays on your Vidlix link until all three are done.') }}</p>
            @endif

            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                <form method="post" action="{{ route('app.custom-domain.check') }}">
                    @csrf
                    <button class="btn secondary" type="submit">{{ __('Check status') }}</button>
                </form>

                <form method="post" action="{{ route('app.custom-domain.disconnect') }}"
                      onsubmit="return confirm('{{ __('Disconnect this domain?') }}')">
                    @csrf @method('DELETE')
                    <button class="btn secondary" type="submit">{{ __('Disconnect') }}</button>
                </form>
            </div>
        </div>

        @unless($domain->isActive())
            <div class="card">
                <h2>{{ __('Add this DNS record') }}</h2>
                <dl class="a-facts">
                    <dt>{{ __('Type') }}</dt><dd>CNAME</dd>
                    <dt>{{ __('Host') }}</dt><dd>{{ explode('.', $domain->hostname)[0] }}</dd>
                    <dt>{{ __('Target') }}</dt>
                    <dd>
                        @if($domain->dns_target)
                            {{ $domain->dns_target }}
                            <button class="btn secondary" type="button" data-copy="{{ $domain->dns_target }}">{{ __('Copy') }}</button>
                        @else
                            <span class="muted">{{ __('Waiting for the provider to give us a target.') }}</span>
                        @endif
                    </dd>
                </dl>
                <p class="muted">{{ __('DNS can take a few minutes to a few hours to spread. Nothing is wrong if the first check comes back empty.') }}</p>
            </div>
        @endunless
    @else
        <form class="form" method="post" action="{{ route('app.custom-domain.connect') }}">
            @csrf
            <label for="hostname">{{ __('Your domain') }}</label>
            <input id="hostname" name="hostname" required maxlength="253" placeholder="contact.yourdomain.com">
            <p class="muted">{{ __('A subdomain works best — something like contact.yourdomain.com — because it leaves the rest of your site alone.') }}</p>

            <button class="btn" type="submit">{{ __('Connect domain') }}</button>
        </form>
    @endif
@endunless
@endsection

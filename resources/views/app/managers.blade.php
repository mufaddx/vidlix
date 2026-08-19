@extends('layouts.app')
@section('title', __('Management'))
@section('content')
<h1>{{ __('Management') }}</h1>

<h2>{{ __('Accounts you manage') }}</h2>
@forelse($representing as $a)
    <div class="card">
        <strong>{{ $a->owner?->name }}</strong> · {{ ucfirst($a->scope) }}
        @if($a->isCompanyProvided())
            <p class="muted">{{ __('Vidlix provided this arrangement.') }}</p>
        @endif
        <form method="post" action="{{ route('workspace.manage') }}">@csrf
            <input type="hidden" name="owner_user_id" value="{{ $a->owner_user_id }}">
            <input type="hidden" name="scope" value="{{ $a->scope }}">
            <button class="btn secondary" type="submit">{{ __('Act as this account') }}</button>
        </form>
    </div>
@empty
    <p class="muted">{{ __('You do not manage anyone else\'s account.') }}</p>
@endforelse

<h2>{{ __('Your managers') }}</h2>
<p class="muted">{{ __('Nobody can apply to manage your account. You appoint them, and you can revoke access at any time — it stops on their very next request.') }}</p>

@forelse($appointed as $a)
    <div class="card">
        <strong>{{ $a->manager?->name ?? $a->manager?->email }}</strong> · {{ ucfirst($a->scope) }} · {{ $a->status }}
        @if($a->isCompanyProvided())<p class="muted">{{ __('Provided by Vidlix') }}</p>@endif
        @if($a->status === 'active')
            <form method="post" action="{{ route('app.managers.revoke', $a) }}">@csrf
                <button type="submit">{{ __('Revoke access') }}</button>
            </form>
        @endif
    </div>
@empty
    <p class="muted">{{ __('No managers appointed yet.') }}</p>
@endforelse

@if($invites->where('status', 'invited')->count())
    <h3>{{ __('Pending invitations') }}</h3>
    @foreach($invites->where('status', 'invited') as $i)
        <p class="muted">{{ $i->email }} · {{ ucfirst($i->scope) }} · {{ __('expires') }} {{ $i->expires_at?->diffForHumans() }}</p>
    @endforeach
@endif

<h2>{{ __('Appoint a manager') }}</h2>
@if(empty($scopes))
    <p class="muted">{{ __('You need a creator, brand or editor account before you can delegate one.') }}</p>
@else
    <form class="form" method="post" action="{{ route('app.managers.invite') }}">@csrf
        <label for="scope">{{ __('Which account') }}</label>
        <select id="scope" name="scope" required>
            @foreach($scopes as $scope)
                <option value="{{ $scope }}">{{ ucfirst($scope) }}</option>
            @endforeach
        </select>

        <label for="email">{{ __('Their email') }}</label>
        <input id="email" name="email" type="email" required>

        <label for="mobile">{{ __('Their mobile number') }}</label>
        <input id="mobile" name="mobile">

        <label for="name">{{ __('Their name') }}</label>
        <input id="name" name="name">

        <button class="btn" type="submit">{{ __('Send invitation') }}</button>
    </form>
    <p class="muted">{{ __('They get a link to set a password and activate. Until they do, nothing is shared with them.') }}</p>
@endif
@endsection

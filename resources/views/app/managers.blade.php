@extends('layouts.app')
@section('title', __('Management'))
@section('content')
<h1>{{ __('Managers') }}</h1>
<form class="form" method="post" action="{{ route('app.managers.invite') }}">
    @csrf
    <label>Email<input name="email" type="email" required></label>
    <label>Name<input name="name"></label>
    <button class="btn" type="submit">{{ __('Invite') }}</button>
</form>
<form method="post" action="{{ route('app.managers.subscribe') }}">
    @csrf
    <select name="plan_id">@foreach($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
    <button class="btn secondary" type="submit">{{ __('Start plan (no charge without provider)') }}</button>
</form>
<h2>{{ __('Incoming invites') }}</h2>
@foreach($incoming as $i)
    <form method="post" action="{{ route('app.managers.accept', $i->token) }}">@csrf<button class="btn" type="submit">{{ __('Accept') }} {{ $i->email }}</button></form>
@endforeach
<h2>{{ __('Relationships') }}</h2>
@foreach($rels as $r)
    <p>{{ $r->status }} creator {{ $r->creator_user_id }} manager {{ $r->manager_user_id }}
        @if($r->creator_user_id === auth()->id() && $r->status === 'active')
            <form method="post" action="{{ route('app.managers.revoke', $r) }}">@csrf<button type="submit">{{ __('Revoke') }}</button></form>
        @endif
    </p>
@endforeach
@endsection

@extends('layouts.admin')
@section('title', __('Members'))
@section('subheading', __(':count accounts. Open one to see everything about it.', ['count' => $users->total()]))
@section('content')

<form class="a-form" method="get" style="margin-bottom:18px">
    <div class="a-form-row">
        <div>
            <label for="q">{{ __('Search') }}</label>
            <input id="q" name="q" value="{{ $q }}" placeholder="{{ __('Name, email or mobile') }}">
        </div>
        <div>
            <label for="status">{{ __('Status') }}</label>
            <select id="status" name="status">
                <option value="">{{ __('Any') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('Active') }}</option>
                <option value="suspended" @selected($status === 'suspended')>{{ __('Suspended') }}</option>
            </select>
        </div>
    </div>
    <div class="a-actions"><button class="a-btn ghost" type="submit">{{ __('Search') }}</button></div>
</form>

<div class="a-panel">
    <table class="a-table">
        <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Email') }}</th><th>{{ __('Status') }}</th><th>{{ __('Joined') }}</th></tr></thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>
                    <a href="{{ route('admin.members.show', $user) }}"><strong>{{ $user->name }}</strong></a>
                    <span class="a-sub">{{ $user->mobile ?: '—' }}</span>
                </td>
                <td>
                    @forelse($user->roles as $role)<span class="a-tag">{{ $role->slug }}</span>@empty<span class="a-sub">—</span>@endforelse
                </td>
                <td class="a-sub">
                    {{ $user->email }}
                    @if(! $user->hasVerifiedEmail())<span class="a-tag warn">{{ __('unverified') }}</span>@endif
                </td>
                <td><span class="a-tag {{ $user->status === 'active' ? 'ok' : 'danger' }}">{{ $user->status }}</span></td>
                <td class="a-sub">{{ $user->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('No members match.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $users->links() }}
@endsection

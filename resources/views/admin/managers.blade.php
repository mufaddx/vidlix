@extends('layouts.admin')
@section('title', __('Managers'))
@section('subheading', __('Who manages whom, and which arrangements Vidlix provided'))
@section('content')

<div class="a-cards">
    <div class="a-card"><div class="a-label">{{ __('Active assignments') }}</div><div class="a-value">{{ $summary['active'] }}</div></div>
    <div class="a-card"><div class="a-label">{{ __('People managing') }}</div><div class="a-value">{{ $summary['managers'] }}</div></div>
    <div class="a-card"><div class="a-label">{{ __('Provided by Vidlix') }}</div><div class="a-value">{{ $summary['company'] }}</div></div>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Per manager') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Manager') }}</th><th>{{ __('Total') }}</th><th>{{ __('Influencers') }}</th><th>{{ __('Editors') }}</th><th>{{ __('Brands') }}</th><th>{{ __('From Vidlix') }}</th></tr></thead>
        <tbody>
        @forelse($byManager as $row)
            <tr>
                <td>{{ $row['manager']?->name }}<span class="a-sub">{{ $row['manager']?->email }}</span></td>
                <td><strong>{{ $row['total'] }}</strong></td>
                <td>{{ $row['creators'] }}</td>
                <td>{{ $row['editors'] }}</td>
                <td>{{ $row['brands'] }}</td>
                <td>{{ $row['company_provided'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="a-empty">{{ __('Nobody manages an account yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('All assignments') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Manager') }}</th><th>{{ __('Arranged by') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse($assignments as $a)
            <tr>
                <td>{{ $a->owner?->name }}<span class="a-sub">{{ $a->owner?->email }}</span></td>
                <td>{{ ucfirst($a->scope) }}</td>
                <td>{{ $a->manager?->name }}<span class="a-sub">{{ $a->manager?->email }}</span></td>
                <td><span class="a-tag {{ $a->isCompanyProvided() ? 'warn' : '' }}">{{ $a->isCompanyProvided() ? __('Vidlix') : __('Account holder') }}</span></td>
                <td><span class="a-tag {{ $a->status === 'active' ? 'ok' : 'danger' }}">{{ $a->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('Nothing yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($pending->count())
<div class="a-panel">
    <div class="a-panel-head">{{ __('Pending invitations') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Invited') }}</th><th>{{ __('For') }}</th><th>{{ __('Arranged by') }}</th><th>{{ __('Expires') }}</th></tr></thead>
        <tbody>
        @foreach($pending as $i)
            <tr>
                <td>{{ $i->email }}</td>
                <td>{{ $i->owner?->name }} <span class="a-sub">{{ $i->scope }}</span></td>
                <td>{{ $i->isCompanyProvided() ? __('Vidlix') : __('Account holder') }}</td>
                <td class="a-sub">{{ $i->expires_at?->diffForHumans() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@can(\App\Support\Ability::MANAGERS_ASSIGN)
<div class="a-panel">
    <div class="a-panel-head">{{ __('Provide a manager') }}</div>
    <div class="a-panel-body">
        <form class="a-form" method="post" action="{{ route('admin.managers.assign') }}">@csrf
            <div><label for="owner_email">{{ __('Member email — whose account is managed') }}</label><input id="owner_email" name="owner_email" type="email" required value="{{ old('owner_email') }}"></div>
            <div>
                <label for="scope">{{ __('Which side of their account') }}</label>
                <select id="scope" name="scope" required>
                    <option value="creator">{{ __('Influencer') }}</option>
                    <option value="editor">{{ __('Editor') }}</option>
                    <option value="brand">{{ __('Brand') }}</option>
                </select>
            </div>
            <div class="a-form-row">
                <div><label for="manager_email">{{ __('Manager email') }}</label><input id="manager_email" name="manager_email" type="email" required value="{{ old('manager_email') }}"></div>
                <div><label for="manager_name">{{ __('Manager name') }}</label><input id="manager_name" name="manager_name" value="{{ old('manager_name') }}"></div>
            </div>
            <div><label for="manager_mobile">{{ __('Manager mobile') }}</label><input id="manager_mobile" name="manager_mobile" value="{{ old('manager_mobile') }}"></div>
            <button class="a-btn" type="submit">{{ __('Send invitation') }}</button>
            <p class="a-hint">{{ __('The member is told this manager came from Vidlix, not from them. Nothing is shared until the manager accepts.') }}</p>
        </form>
    </div>
</div>
@endcan
@endsection

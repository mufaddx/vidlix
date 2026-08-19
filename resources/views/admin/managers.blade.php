@extends('layouts.app')
@section('title', __('Managers'))
@section('content')
<h1>{{ __('Managers') }}</h1>

<div class="grid">
    <div class="card"><p class="stat">{{ $summary['active'] }}</p><p class="muted">{{ __('Active assignments') }}</p></div>
    <div class="card"><p class="stat">{{ $summary['managers'] }}</p><p class="muted">{{ __('People managing accounts') }}</p></div>
    <div class="card"><p class="stat">{{ $summary['company'] }}</p><p class="muted">{{ __('Provided by Vidlix') }}</p></div>
</div>

<h2>{{ __('Per manager') }}</h2>
<table class="table">
    <tr><th>{{ __('Manager') }}</th><th>{{ __('Total') }}</th><th>{{ __('Creators') }}</th><th>{{ __('Editors') }}</th><th>{{ __('Brands') }}</th><th>{{ __('From Vidlix') }}</th></tr>
    @forelse($byManager as $row)
        <tr>
            <td>{{ $row['manager']?->name }}<br><span class="muted">{{ $row['manager']?->email }}</span></td>
            <td>{{ $row['total'] }}</td>
            <td>{{ $row['creators'] }}</td>
            <td>{{ $row['editors'] }}</td>
            <td>{{ $row['brands'] }}</td>
            <td>{{ $row['company_provided'] }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">{{ __('Nobody manages an account yet.') }}</td></tr>
    @endforelse
</table>

<h2>{{ __('All assignments') }}</h2>
<table class="table">
    <tr><th>{{ __('Account') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Manager') }}</th><th>{{ __('Source') }}</th><th>{{ __('Status') }}</th></tr>
    @foreach($assignments as $a)
        <tr>
            <td>{{ $a->owner?->name }}<br><span class="muted">{{ $a->owner?->email }}</span></td>
            <td>{{ ucfirst($a->scope) }}</td>
            <td>{{ $a->manager?->name }}<br><span class="muted">{{ $a->manager?->email }}</span></td>
            <td>{{ $a->isCompanyProvided() ? __('Vidlix') : __('Account holder') }}</td>
            <td>{{ $a->status }}</td>
        </tr>
    @endforeach
</table>

@if($pending->count())
    <h2>{{ __('Pending invitations') }}</h2>
    @foreach($pending as $i)
        <p class="muted">{{ $i->email }} → {{ $i->owner?->name }} ({{ $i->scope }}) · {{ $i->isCompanyProvided() ? __('from Vidlix') : __('from the account holder') }} · {{ __('expires') }} {{ $i->expires_at?->diffForHumans() }}</p>
    @endforeach
@endif

@can(\App\Support\Ability::MANAGERS_ASSIGN)
    <h2>{{ __('Provide a manager') }}</h2>
    <p class="muted">{{ __('The member is told this manager came from Vidlix, not from them.') }}</p>
    <form class="form" method="post" action="{{ route('admin.managers.assign') }}">@csrf
        <label for="owner_email">{{ __('Member email (whose account is managed)') }}</label>
        <input id="owner_email" name="owner_email" type="email" required value="{{ old('owner_email') }}">

        <label for="scope">{{ __('Which side of their account') }}</label>
        <select id="scope" name="scope" required>
            <option value="creator">{{ __('Creator') }}</option>
            <option value="editor">{{ __('Editor') }}</option>
            <option value="brand">{{ __('Brand') }}</option>
        </select>

        <label for="manager_email">{{ __('Manager email') }}</label>
        <input id="manager_email" name="manager_email" type="email" required value="{{ old('manager_email') }}">

        <label for="manager_name">{{ __('Manager name') }}</label>
        <input id="manager_name" name="manager_name" value="{{ old('manager_name') }}">

        <label for="manager_mobile">{{ __('Manager mobile') }}</label>
        <input id="manager_mobile" name="manager_mobile" value="{{ old('manager_mobile') }}">

        <button class="btn" type="submit">{{ __('Send invitation') }}</button>
    </form>
@endcan
@endsection

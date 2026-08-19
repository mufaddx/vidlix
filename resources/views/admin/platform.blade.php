@extends('layouts.admin')
@section('title', __('Feature switches'))
@section('subheading', __('Turn capabilities on or off, and close the site, without a deploy'))
@section('content')

@if(session('status'))
    <div class="a-panel" style="padding:14px 16px"><span class="a-tag ok">{{ __('Saved') }}</span> {{ session('status') }}</div>
@endif

<div class="a-panel">
    <div class="a-panel-head">{{ __('Maintenance') }}</div>
    <div style="padding:16px">
        <p class="a-sub" style="margin-top:0">
            {{-- Said here so nobody has to read the middleware to find out. --}}
            {{ __('Closing the site shows members a notice. Staff, sign-in and provider webhooks stay open, because a payment confirmation must never be turned away: the money moved whether the site is up or not.') }}
        </p>
        <form method="post" action="{{ route('admin.platform.maintenance') }}">@csrf
            <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <input type="checkbox" name="enabled" value="1" @checked($maintenance)>
                <strong>{{ __('Close the site to members') }}</strong>
            </label>
            <label style="display:block;margin-bottom:8px">
                {{ __('What members will see') }}
                <input type="text" name="message" maxlength="300" value="{{ $maintenanceMessage }}">
            </label>
            <button class="a-btn" type="submit">{{ __('Save') }}</button>
        </form>
    </div>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Features') }}</div>
    <table class="a-table">
        <thead><tr>
            <th>{{ __('Feature') }}</th>
            <th style="width:14%">{{ __('State') }}</th>
            <th style="width:18%">{{ __('Open to') }}</th>
            <th style="width:12%"></th>
        </tr></thead>
        <tbody>
        @foreach($flags as $flag)
            <tr>
                <td>
                    <form method="post" action="{{ route('admin.platform.flag') }}" id="flag-{{ $flag['key'] }}">@csrf
                        <input type="hidden" name="key" value="{{ $flag['key'] }}">
                    </form>
                    {{ $flag['name'] }}
                    <span class="a-sub">{{ $flag['description'] }}</span>
                </td>
                <td>
                    <label style="display:flex;align-items:center;gap:8px">
                        <input form="flag-{{ $flag['key'] }}" type="checkbox" name="enabled" value="1" @checked($flag['is_enabled'])>
                        {{ $flag['is_enabled'] ? __('On') : __('Off') }}
                    </label>
                </td>
                <td>
                    <select form="flag-{{ $flag['key'] }}" name="audience">
                        @foreach($audiences as $value => $label)
                            <option value="{{ $value }}" @selected($flag['audience'] === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </td>
                <td><button class="a-btn" form="flag-{{ $flag['key'] }}" type="submit">{{ __('Save') }}</button></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

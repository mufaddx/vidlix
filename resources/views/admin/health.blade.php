@extends('layouts.admin')
@section('title', __('System health'))
@section('subheading', __('Measured now, on this request — nothing here is assumed'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Checks') }}</div>
    <table class="a-table">
        <thead><tr>
            <th style="width:22%">{{ __('Component') }}</th>
            <th style="width:16%">{{ __('State') }}</th>
            <th>{{ __('Detail') }}</th>
        </tr></thead>
        <tbody>
        @foreach($checks as $check)
            <tr>
                <td><strong>{{ $check['name'] }}</strong></td>
                <td>
                    @php($tone = ['ok' => 'ok', 'warn' => 'warn', 'down' => 'danger', 'unconfigured' => 'warn'][$check['state']] ?? '')
                    <span class="a-tag {{ $tone }}">{{ __(ucfirst(str_replace('unconfigured', 'not configured', $check['state']))) }}</span>
                </td>
                <td class="a-sub">{{ $check['detail'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<p class="a-sub">
    {{-- A provider with no credentials is not healthy and is not broken; it is
         simply not connected, and saying so is the only honest answer. --}}
    {{ __('"Not configured" means no credentials are present. Nothing that depends on that provider is ever reported as confirmed.') }}
</p>
@endsection

@extends('layouts.admin')
@section('title', __('All brands'))
@section('subheading', __(':count brand accounts', ['count' => $brands->total()]))
@section('content')
<form class="a-form" method="get" style="margin-bottom:18px">
    <input type="hidden" name="section" value="brands">
    <input name="q" value="{{ $q }}" placeholder="{{ __('Search company name') }}">
    <button class="a-btn ghost" type="submit">{{ __('Search') }}</button>
</form>

<div class="a-panel">
    <table class="a-table">
        <thead><tr><th>{{ __('Brand') }}</th><th>{{ __('Industry') }}</th><th>{{ __('Website') }}</th><th>{{ __('Verification') }}</th></tr></thead>
        <tbody>
        @forelse($brands as $brand)
            <tr>
                <td>{{ $brand->company_name }}<span class="a-sub">{{ $brand->user?->email }}</span></td>
                <td>{{ $brand->industry ?? '—' }}</td>
                <td>{{ $brand->website ?? '—' }}</td>
                <td><span class="a-tag {{ $brand->verification_status === 'verified' ? 'ok' : 'warn' }}">{{ $brand->verification_status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No brands yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $brands->links() }}
@endsection

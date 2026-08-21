@extends('layouts.admin')
@section('title', __('Verification'))
@section('subheading', __('Accounts and campaigns waiting on a decision'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Editors') }} ({{ $editors->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Editor') }}</th><th>{{ __('Status') }}</th><th style="width:24%">{{ __('Decision') }}</th></tr></thead>
        <tbody>
        @forelse($editors as $e)
            <tr>
                <td>{{ $e->display_name }}<span class="a-sub">&#64;{{ $e->username }}</span></td>
                <td><span class="a-tag warn">{{ $e->application_status }}</span></td>
                <td>
                    <form method="post" action="{{ route('admin.editors.decide', $e) }}" style="display:flex;gap:6px">@csrf
                        <button class="a-btn" name="decision" value="approved">{{ __('Approve') }}</button>
                        <button class="a-btn danger" name="decision" value="rejected">{{ __('Reject') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="a-empty">{{ __('Nothing waiting.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Brands') }} ({{ $brands->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Brand') }}</th><th>{{ __('Status') }}</th><th style="width:24%">{{ __('Decision') }}</th></tr></thead>
        <tbody>
        @forelse($brands as $b)
            <tr>
                <td>{{ $b->company_name }}<span class="a-sub">{{ $b->website }}</span></td>
                <td><span class="a-tag warn">{{ $b->verification_status }}</span></td>
                <td>
                    <form method="post" action="{{ route('admin.brands.decide', $b) }}" style="display:flex;gap:6px">@csrf
                        <button class="a-btn" name="decision" value="verified">{{ __('Verify') }}</button>
                        <button class="a-btn danger" name="decision" value="rejected">{{ __('Reject') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="a-empty">{{ __('Nothing waiting.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Campaigns') }} ({{ $campaigns->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Status') }}</th><th style="width:24%">{{ __('Decision') }}</th></tr></thead>
        <tbody>
        @forelse($campaigns as $c)
            <tr>
                <td>{{ $c->name }}<span class="a-sub">{{ $c->objective }}</span></td>
                <td><span class="a-tag warn">{{ $c->status }}</span></td>
                <td>
                    <form method="post" action="{{ route('admin.campaigns.decide', $c) }}" style="display:flex;gap:6px">@csrf
                        <button class="a-btn" name="decision" value="published">{{ __('Publish') }}</button>
                        {{-- Sent back as a draft rather than cancelled: the brand
                             can fix it and resubmit, which cancelling forecloses. --}}
                        <button class="a-btn danger" name="decision" value="draft">{{ __('Send back') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="a-empty">{{ __('Nothing waiting.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

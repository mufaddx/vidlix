@extends('layouts.admin')
@section('title', __('Verification'))
@section('subheading', __('Accounts and campaigns waiting on a decision'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Editors') }} ({{ $editors->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Editor') }}</th><th>{{ __('Application') }}</th><th style="width:38%">{{ __('Decision') }}</th></tr></thead>
        <tbody>
        @forelse($editors as $e)
            <tr>
                <td>
                    {{ $e->display_name }}<span class="a-sub">&#64;{{ $e->username }}</span>
                    @if($e->submitted_at)
                        <span class="a-sub">{{ __('sent :when', ['when' => $e->submitted_at->diffForHumans()]) }}</span>
                    @endif
                </td>
                <td>
                    <span class="a-tag warn">{{ $e->statusLabel() }}</span>
                    {{-- What they actually said, so a decision is not made from
                         a name and a status alone. --}}
                    <dl class="a-facts">
                        @if($e->years_experience !== null)
                            <dt>{{ __('Experience') }}</dt><dd>{{ $e->years_experience }} {{ __('years') }}</dd>
                        @endif
                        @if($e->specializations)
                            <dt>{{ __('Specialises in') }}</dt><dd>{{ implode(' · ', $e->specializations) }}</dd>
                        @endif
                        @if($e->software)
                            <dt>{{ __('Software') }}</dt><dd>{{ implode(' · ', $e->software) }}</dd>
                        @endif
                        @if($e->services)
                            <dt>{{ __('Services') }}</dt><dd>{{ implode(' · ', $e->services) }}</dd>
                        @endif
                        @if($e->portfolio_url)
                            <dt>{{ __('Work') }}</dt>
                            <dd><a href="{{ $e->portfolio_url }}" rel="noopener noreferrer" target="_blank">{{ $e->portfolio_url }}</a></dd>
                        @endif
                        @if($e->bio)
                            <dt>{{ __('Bio') }}</dt><dd class="a-sub">{{ $e->bio }}</dd>
                        @endif
                    </dl>
                </td>
                <td>
                    <form class="a-form" method="post" action="{{ route('admin.editors.decide', $e) }}">@csrf
                        {{-- Required for anything but an approval: a rejection
                             with no reason is one the applicant cannot act on. --}}
                        <label for="note-{{ $e->id }}">{{ __('Note to the applicant') }}</label>
                        <input id="note-{{ $e->id }}" name="note" maxlength="2000"
                               placeholder="{{ __('Needed for anything but approval') }}">

                        <div class="a-actions">
                            <button class="a-btn" name="decision" value="approved">{{ __('Approve') }}</button>
                            <button class="a-btn ghost" name="decision" value="more_info">{{ __('Ask for more') }}</button>
                            <button class="a-btn danger" name="decision" value="rejected">{{ __('Reject') }}</button>
                        </div>
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

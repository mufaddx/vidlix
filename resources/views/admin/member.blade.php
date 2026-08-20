@extends('layouts.admin')
@section('title', $member->name)
@section('subheading', $member->email.' · '.__('joined').' '.$member->created_at->diffForHumans())
@section('content')

<a class="a-back" href="{{ route('admin.members') }}">← {{ __('All members') }}</a>

{{-- Identity ------------------------------------------------------------- --}}
<div class="a-panel">
    <div class="a-panel-head">{{ __('Account') }}</div>
    <dl class="a-facts">
        <dt>{{ __('Name') }}</dt><dd>{{ $member->name }}</dd>
        <dt>{{ __('Email') }}</dt>
        <dd>{{ $member->email }}
            @if($member->hasVerifiedEmail())<span class="a-tag ok">{{ __('verified') }}</span>
            @else<span class="a-tag warn">{{ __('unverified') }}</span>@endif
        </dd>
        <dt>{{ __('Mobile') }}</dt><dd>{{ $member->mobile ?: '—' }}</dd>
        <dt>{{ __('Status') }}</dt>
        <dd><span class="a-tag {{ $member->status === 'active' ? 'ok' : 'danger' }}">{{ $member->status }}</span></dd>
        <dt>{{ __('Roles') }}</dt>
        <dd>
            @forelse($member->roles as $role)<span class="a-tag">{{ $role->slug }}</span>@empty
                <span class="a-sub">{{ __('No roles applied for yet') }}</span>
            @endforelse
        </dd>
        <dt>{{ __('Joined') }}</dt><dd>{{ $member->created_at }}</dd>
    </dl>
</div>

{{-- Money ---------------------------------------------------------------- --}}
<div class="a-panel">
    <div class="a-panel-head">{{ __('Money') }}</div>
    <div class="a-money">
        <div><div class="a-label">{{ __('Available') }}</div><div class="a-value">₹{{ number_format($money['available'] / 100, 2) }}</div></div>
        <div><div class="a-label">{{ __('Held in escrow') }}</div><div class="a-value">₹{{ number_format($money['reserved'] / 100, 2) }}</div></div>
        <div><div class="a-label">{{ __('Paid out') }}</div><div class="a-value">₹{{ number_format($money['withdrawn'] / 100, 2) }}</div></div>
    </div>
    <div class="a-panel-body" style="border-top:1px solid var(--a-line)">
        <p class="a-hint" style="color:var(--a-muted);font-size:12px;margin:0">
            {{ __('Every figure is a sum over ledger entries. Nothing here is a stored balance, so it cannot drift from what the member sees.') }}
        </p>
    </div>
</div>

@if($member->creatorProfile)
<div class="a-panel">
    <div class="a-panel-head">{{ __('Influencer profile') }}</div>
    <dl class="a-facts">
        <dt>{{ __('Display name') }}</dt><dd>{{ $member->creatorProfile->display_name }}</dd>
        <dt>{{ __('Public URL') }}</dt><dd>/u/{{ $member->creatorProfile->username }}</dd>
        <dt>{{ __('Followers') }}</dt>
        <dd>
            @if($member->creatorProfile->follower_count !== null)
                {{ number_format($member->creatorProfile->follower_count) }}
                <span class="a-sub">{{ __('synced') }} {{ $member->creatorProfile->follower_count_synced_at?->diffForHumans() }}</span>
            @else
                <span class="a-sub">{{ __('Not synced — Instagram has never returned a count for this account.') }}</span>
            @endif
        </dd>
        <dt>{{ __('Instagram') }}</dt>
        <dd><span class="a-tag {{ $member->creatorProfile->instagram_connection_status === 'connected' ? 'ok' : 'warn' }}">{{ $member->creatorProfile->instagram_connection_status ?? 'disconnected' }}</span></dd>
        <dt>{{ __('Categories') }}</dt>
        <dd>@forelse($creatorCategories as $c)<span class="a-tag">{{ $c->name }}</span>@empty<span class="a-sub">—</span>@endforelse</dd>
        <dt>{{ __('Public page') }}</dt>
        <dd>
            <span class="a-tag {{ $member->creatorProfile->visibility === 'public' ? 'ok' : 'warn' }}">{{ $member->creatorProfile->visibility }}</span>
            @can(\App\Support\Ability::USERS_MANAGE)
                <form method="post" action="{{ route('admin.members.visibility', $member) }}" style="margin-top:8px">@csrf
                    <input type="hidden" name="visibility" value="{{ $member->creatorProfile->visibility === 'public' ? 'private' : 'public' }}">
                    <button class="a-btn ghost" type="submit">{{ $member->creatorProfile->visibility === 'public' ? __('Take page down') : __('Publish page') }}</button>
                </form>
            @endcan
        </dd>
    </dl>
</div>
@endif

@if($member->editorProfile)
<div class="a-panel">
    <div class="a-panel-head">{{ __('Editor profile') }}</div>
    <dl class="a-facts">
        <dt>{{ __('Display name') }}</dt><dd>{{ $member->editorProfile->display_name }}</dd>
        <dt>{{ __('Public URL') }}</dt><dd>/editors/{{ $member->editorProfile->username }}</dd>
        <dt>{{ __('Application') }}</dt>
        <dd><span class="a-tag {{ $member->editorProfile->application_status === 'approved' ? 'ok' : 'warn' }}">{{ $member->editorProfile->application_status }}</span></dd>
        <dt>{{ __('Works on') }}</dt>
        <dd>@forelse($editorCategories as $c)<span class="a-tag">{{ $c->name }}</span>@empty<span class="a-sub">—</span>@endforelse</dd>
        <dt>{{ __('Starting price') }}</dt>
        <dd>{{ $member->editorProfile->starting_price_minor ? '₹'.number_format($member->editorProfile->starting_price_minor / 100) : '—' }}</dd>
    </dl>
</div>
@endif

@if($member->brandProfile)
<div class="a-panel">
    <div class="a-panel-head">{{ __('Brand profile') }}</div>
    <dl class="a-facts">
        <dt>{{ __('Company') }}</dt><dd>{{ $member->brandProfile->company_name }}</dd>
        <dt>{{ __('Website') }}</dt><dd>{{ $member->brandProfile->website ?: '—' }}</dd>
        <dt>{{ __('Industry') }}</dt><dd>{{ $member->brandProfile->industry ?: '—' }}</dd>
        <dt>{{ __('Verification') }}</dt>
        <dd><span class="a-tag {{ $member->brandProfile->verification_status === 'verified' ? 'ok' : 'warn' }}">{{ $member->brandProfile->verification_status }}</span></dd>
    </dl>
</div>
@endif

{{-- Who they talk to ------------------------------------------------------ --}}
<div class="a-panel">
    <div class="a-panel-head">{{ __('Conversations') }} ({{ $conversations->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('With') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Messages') }}</th><th>{{ __('Last activity') }}</th></tr></thead>
        <tbody>
        @forelse($conversations as $c)
            <tr>
                <td>{{ $c->subject }}</td>
                <td>{{ $c->externalContact?->name ?? $c->externalContact?->email ?? __('Internal') }}</td>
                <td><span class="a-tag">{{ $c->channel }}</span></td>
                <td>{{ $c->messages_count }}</td>
                <td class="a-sub">{{ $c->last_message_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('No conversations.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Work ------------------------------------------------------------------ --}}
<div class="a-panel">
    <div class="a-panel-head">{{ __('Projects') }} ({{ $projects->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Project') }}</th><th>{{ __('Status') }}</th><th>{{ __('Value') }}</th><th>{{ __('Deadline') }}</th></tr></thead>
        <tbody>
        @forelse($projects as $p)
            <tr>
                <td>{{ $p->name }}</td>
                <td><span class="a-tag">{{ $p->status }}</span></td>
                <td>₹{{ number_format($p->total_amount_minor / 100, 2) }}</td>
                <td class="a-sub">{{ $p->deadline ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No projects.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($applications->count())
<div class="a-panel">
    <div class="a-panel-head">{{ __('Campaign applications') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Proposed fee') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @foreach($applications as $a)
            <tr>
                <td>{{ $a->campaign?->name ?? '—' }}</td>
                <td>₹{{ number_format($a->proposed_fee_minor / 100, 2) }}</td>
                <td><span class="a-tag">{{ $a->status }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Ledger and payouts ---------------------------------------------------- --}}
<div class="a-panel">
    <div class="a-panel-head">{{ __('Ledger') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('When') }}</th><th>{{ __('State') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Reference') }}</th></tr></thead>
        <tbody>
        @forelse($ledger as $entry)
            <tr>
                <td class="a-sub">{{ $entry->created_at }}</td>
                <td><span class="a-tag">{{ $entry->state }}</span></td>
                <td>{{ $entry->amount_minor < 0 ? '−' : '' }}₹{{ number_format(abs($entry->amount_minor) / 100, 2) }}</td>
                <td class="a-sub">{{ $entry->provider_reference ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No ledger entries.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@if($withdrawals->count())
<div class="a-panel">
    <div class="a-panel-head">{{ __('Withdrawals') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Provider note') }}</th></tr></thead>
        <tbody>
        @foreach($withdrawals as $w)
            <tr>
                <td>₹{{ number_format($w->amount_minor / 100, 2) }}</td>
                <td><span class="a-tag {{ $w->status === 'paid' ? 'ok' : 'warn' }}">{{ $w->status }}</span></td>
                <td class="a-sub">{{ $w->last_provider_detail ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- History --------------------------------------------------------------- --}}
<div class="a-panel">
    <div class="a-panel-head">{{ __('Recent activity') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Action') }}</th></tr></thead>
        <tbody>
        @forelse($activity as $row)
            <tr><td class="a-sub">{{ $row->created_at }}</td><td>{{ $row->action }}</td></tr>
        @empty
            <tr><td colspan="2" class="a-empty">{{ __('Nothing recorded.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{-- Controls -------------------------------------------------------------- --}}
@can(\App\Support\Ability::USERS_MANAGE)
<div class="a-panel">
    <div class="a-panel-head">{{ __('Account controls') }}</div>
    <div class="a-panel-body">
        @if($member->isSuperAdmin())
            <p class="a-hint" style="color:var(--a-muted)">{{ __('A super admin account cannot be suspended from here.') }}</p>
        @else
            <form class="a-form" method="post" action="{{ route('admin.members.status', $member) }}">@csrf
                <input type="hidden" name="status" value="{{ $member->status === 'active' ? 'suspended' : 'active' }}">
                <div>
                    <label for="reason">{{ __('Reason (recorded in the audit log)') }}</label>
                    <input id="reason" name="reason" maxlength="400">
                </div>
                <div class="a-actions">
                    <button class="a-btn {{ $member->status === 'active' ? 'danger' : '' }}" type="submit">
                        {{ $member->status === 'active' ? __('Suspend this account') : __('Restore this account') }}
                    </button>
                </div>
                <p class="a-hint">{{ __('Suspension is reversible and destroys nothing. Their work, threads and ledger stay exactly as they are; they simply cannot sign in.') }}</p>
            </form>
        @endif
    </div>
</div>
@endcan
@endsection

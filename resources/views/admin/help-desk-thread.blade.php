@extends('layouts.admin')
@section('title', $thread->conversation?->subject ?? __('Help desk thread'))
@section('subheading', $thread->reference.' · '.$messages->count().' '.__('messages'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Who this is') }}</div>
    <table class="a-table">
        <tbody>
        <tr>
            <td style="width:30%">{{ __('Name') }}</td>
            <td>{{ $thread->user?->name ?? $thread->conversation?->externalContact?->name ?? __('Unknown') }}</td>
        </tr>
        <tr>
            <td>{{ __('Email') }}</td>
            <td>{{ $thread->user?->email ?? $thread->conversation?->externalContact?->email }}</td>
        </tr>
        <tr>
            <td>{{ __('Account') }}</td>
            <td>
                @if($thread->user)
                    <span class="a-tag ok">{{ __('Member') }}</span>
                    <span class="a-sub">{{ implode(', ', $thread->user->roleSlugs()) ?: __('no roles yet') }}</span>
                @else
                    <span class="a-tag">{{ __('Not a member') }}</span>
                    <span class="a-sub">{{ __('Wrote in by email only.') }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td>{{ __('Status') }}</td>
            <td><span class="a-tag {{ $thread->status === 'closed' ? '' : 'warn' }}">{{ $thread->status }}</span></td>
        </tr>
        </tbody>
    </table>
</div>

<div class="a-thread">
    @foreach($messages as $message)
        @php $outbound = $message->direction === 'outbound'; @endphp
        <div class="a-msg {{ $outbound ? 'out' : '' }}">
            <div class="a-msg-meta">
                <strong>{{ $outbound ? ($message->actor?->name ?? __('Vidlix')) : ($thread->user?->name ?? $thread->conversation?->externalContact?->name ?? __('Them')) }}</strong>
                <span>{{ $message->created_at }}</span>
                <span class="a-tag {{ $message->delivery_status === 'delivered' ? 'ok' : ($message->delivery_status === 'bounced' ? 'danger' : '') }}">{{ $message->delivery_status }}</span>
            </div>
            <div class="a-msg-body">{{ $message->body }}</div>
        </div>
    @endforeach
</div>

@if($canReply && $thread->status !== 'closed')
    <div class="a-panel">
        <div class="a-panel-head">{{ __('Reply by email') }}</div>
        <div class="a-panel-body">
            <form class="a-form" method="post" action="{{ route('admin.help-desk.reply', $thread) }}">@csrf
                <textarea name="body" required maxlength="8000" placeholder="{{ __('Write your reply…') }}"></textarea>
                <button class="a-btn" type="submit">{{ __('Send reply') }}</button>
                <p class="a-hint">{{ __('It is queued, and shown as sent only once the email provider confirms it. Their reply comes back to this thread.') }}</p>
            </form>
        </div>
    </div>

    <form method="post" action="{{ route('admin.help-desk.close', $thread) }}">@csrf
        <button class="a-btn ghost" type="submit">{{ __('Close this thread') }}</button>
    </form>
@elseif(! $canReply)
    <div class="a-notice info">{{ __('You can read this thread but not reply. That needs the "reply to people from the help desk" ability.') }}</div>
@else
    <div class="a-notice info">{{ __('This thread is closed. A new message from them will reopen it.') }}</div>
@endif
@endsection

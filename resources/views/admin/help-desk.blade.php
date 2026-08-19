@extends('layouts.admin')
@section('title', __('Help desk'))
@section('subheading', $address
    ? __('Everything sent to :address, plus help requests raised inside the app.', ['address' => $address])
    : __('Help requests raised inside the app. The mailbox is not configured yet.'))
@section('content')

<div class="a-filters">
    @foreach(['open' => __('Open'), 'pending' => __('Waiting on them'), 'closed' => __('Closed'), 'all' => __('All')] as $key => $label)
        <a class="a-filter {{ $status === $key ? 'is-active' : '' }}"
           href="{{ route('admin.help-desk', ['status' => $key, 'section' => 'operations']) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="a-panel">
    <table class="a-table">
        <thead>
            <tr>
                <th style="width:26%">{{ __('From') }}</th>
                <th>{{ __('Subject') }}</th>
                <th style="width:9%">{{ __('Messages') }}</th>
                <th style="width:14%">{{ __('Status') }}</th>
                <th style="width:14%">{{ __('Last activity') }}</th>
            </tr>
        </thead>
        <tbody>
        @forelse($threads as $thread)
            @php
                $name = $thread->user?->name ?? $thread->conversation?->externalContact?->name ?? __('Unknown');
                $email = $thread->user?->email ?? $thread->conversation?->externalContact?->email;
            @endphp
            <tr>
                <td>
                    <a href="{{ route('admin.help-desk.show', ['thread' => $thread, 'section' => 'operations']) }}"><strong>{{ $name }}</strong></a>
                    <span class="a-sub">{{ $email }}</span>
                    <span class="a-sub">{{ $thread->user ? __('Member') : __('Not a member') }}</span>
                </td>
                <td>
                    <a href="{{ route('admin.help-desk.show', ['thread' => $thread, 'section' => 'operations']) }}">{{ $thread->conversation?->subject }}</a>
                    <span class="a-sub">{{ $thread->reference }}</span>
                </td>
                <td>{{ $thread->conversation?->messages_count ?? 0 }}</td>
                <td>
                    <span class="a-tag {{ $thread->status === 'open' ? 'warn' : ($thread->status === 'closed' ? '' : 'ok') }}">{{ $thread->status }}</span>
                </td>
                <td class="a-sub">{{ $thread->conversation?->last_message_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">
                {{ __('No messages here.') }}
                @if($address)<br>{{ __('Anyone can write to :address and it will appear in this list.', ['address' => $address]) }}@endif
            </td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $threads->links() }}
@endsection

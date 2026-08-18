@extends('layouts.app')
@section('title', __('Inbox'))
@section('content')
<h1>{{ __('External inquiries') }}</h1>
@if($conversations->isEmpty())
    <p class="muted">{{ __('No messages yet. Share your public page after you publish it.') }}</p>
@else
<table class="table">
    <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('From') }}</th><th>{{ __('Updated') }}</th></tr></thead>
    <tbody>
    @foreach($conversations as $c)
        <tr>
            <td><a href="{{ route('creator.inbox.show', $c->conversation_uuid) }}">{{ $c->subject }}</a></td>
            <td>{{ $c->externalContact?->email }}</td>
            <td>{{ $c->last_message_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $conversations->links() }}
@endif
@endsection

@extends('layouts.admin')
@section('title', __('Tickets'))
@section('subheading', __('The member-facing ticket list. Staff answer these from the help desk.'))
@section('content')

<div class="a-panel">
    <table class="a-table">
        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Category') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse($items as $t)
            <tr>
                <td>{{ $t->subject }}<span class="a-sub">#{{ $t->id }}</span></td>
                <td>{{ $t->category }}</td>
                <td>{{ $t->priority }}</td>
                <td><span class="a-tag {{ $t->status === 'open' ? 'warn' : '' }}">{{ $t->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No tickets.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

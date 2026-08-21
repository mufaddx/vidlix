@extends('layouts.autodm')
@section('title', __('Automation history'))
@section('content')

<p><a href="{{ route('autodm.index') }}">{{ __('Back to AutoDM') }}</a></p>

<h1>{{ $automation->name }}</h1>
<p class="muted">{{ __('Every comment this automation saw, and what became of it.') }}</p>

<table class="a-table">
    <thead>
    <tr>
        <th>{{ __('When') }}</th>
        <th>{{ __('Action') }}</th>
        <th>{{ __('Outcome') }}</th>
        <th>{{ __('Why') }}</th>
        <th>{{ __('Attempts') }}</th>
    </tr>
    </thead>
    <tbody>
    @forelse($runs as $run)
        <tr>
            <td class="a-sub">{{ $run->created_at }}</td>
            <td>{{ str_replace('_', ' ', $run->action) }}</td>
            <td>
                {{-- Sent, skipped and failed are three different things and are
                     never collapsed: a skip is not a fault to chase. --}}
                <span class="a-tag {{ $run->succeeded() ? 'ok' : ($run->status === 'skipped' ? 'warn' : 'danger') }}">
                    {{ str_replace('_', ' ', $run->status) }}
                </span>
            </td>
            <td class="a-sub">{{ $run->detail ?: '—' }}</td>
            <td class="a-sub">{{ $run->attempts }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="a-empty">{{ __('Nothing has run yet.') }}</td></tr>
    @endforelse
    </tbody>
</table>

{{ $runs->links() }}
@endsection

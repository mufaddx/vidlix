@extends('layouts.admin')
@section('title', __('Categories'))
@section('subheading', __('Proposed by members, waiting for review'))
@section('content')
<div class="a-panel">
    <div class="a-panel-head">{{ __('Awaiting review') }} ({{ $pending->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('In use by') }}</th><th>{{ __('Proposed') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($pending as $category)
            <tr>
                <td>{{ $category->name }}<span class="a-sub">{{ $category->slug }}</span></td>
                <td>{{ $category->assignments->count() }}</td>
                <td class="a-sub">{{ $category->created_at->diffForHumans() }}</td>
                <td>
                    <form method="post" action="{{ route('admin.categories.decide', $category) }}" style="display:flex;gap:6px">@csrf
                        <button class="a-btn" name="decision" value="approve">{{ __('Approve') }}</button>
                        <button class="a-btn danger" name="decision" value="reject">{{ __('Reject') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('Nothing waiting. Members can propose a category at any time.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Public list') }} ({{ $approved->count() }})</div>
    <div class="a-panel-body">
        @foreach($approved as $category)
            <span class="a-tag">{{ $category->name }}</span>
        @endforeach
    </div>
</div>
<p class="a-hint" style="color:var(--a-muted);font-size:12px">{{ __('A rejected category stays attached to whoever proposed it — removing it would silently strip a category from their profile — but it never appears in the public list.') }}</p>
@endsection

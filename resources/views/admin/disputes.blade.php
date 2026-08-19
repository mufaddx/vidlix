@extends('layouts.admin')
@section('title', __('Disputes'))
@section('subheading', __('Disagreements between members waiting on a decision'))
@section('content')

@forelse($items as $d)
    <div class="a-panel">
        <div class="a-panel-head">{{ $d->reason }} <span class="a-tag">{{ $d->status }}</span></div>
        <div class="a-panel-body">
            <p class="a-sub" style="color:var(--a-muted);font-size:12px">{{ $d->dispute_uuid }}</p>
            <p style="white-space:pre-wrap">{{ $d->statement }}</p>
            @if($d->status !== 'resolved')
                <form class="a-form" method="post" action="{{ route('admin.disputes.resolve', $d) }}">@csrf
                    <label for="resolution-{{ $d->id }}">{{ __('Resolution') }}</label>
                    <textarea id="resolution-{{ $d->id }}" name="resolution" required></textarea>
                    <button class="a-btn" type="submit">{{ __('Resolve') }}</button>
                </form>
            @endif
        </div>
    </div>
@empty
    <div class="a-panel"><div class="a-empty">{{ __('No disputes.') }}</div></div>
@endforelse
@endsection

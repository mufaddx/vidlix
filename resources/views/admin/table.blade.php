@extends('layouts.admin')
@section('title', $title)
@section('content')
<div class="a-panel">
    <table class="a-table">
        <thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($headers) }}" class="a-empty">{{ __('Nothing to show.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection

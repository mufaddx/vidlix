@extends('layouts.app')
@section('title', $project->name)
@section('content')
<p class="kicker">{{ $project->status }} · {{ __('deadline') }} {{ $project->deadline }}</p>
<h1>{{ $project->name }}</h1>
<form method="post" action="{{ route('app.projects.transition', $project) }}">
    @csrf
    <select name="status">
        @foreach(['proposal_sent','awaiting_advance','advance_paid','active','draft_submitted','revision_requested','revision_submitted','final_submitted','remaining_payment','client_approved','settlement_pending','completed'] as $s)
            <option>{{ $s }}</option>
        @endforeach
    </select>
    <button class="btn secondary" type="submit">{{ __('Transition') }}</button>
</form>
<form method="post" action="{{ route('app.projects.file', $project) }}" enctype="multipart/form-data">
    @csrf
    <input type="file" name="file" required>
    <select name="kind"><option>raw</option><option>draft</option><option>final</option><option>brief</option></select>
    <label><input type="checkbox" name="watermarked" value="1"> {{ __('Watermarked preview') }}</label>
    <button class="btn" type="submit">{{ __('Upload') }}</button>
</form>
<form method="post" action="{{ route('app.projects.revision', $project) }}">@csrf<textarea name="feedback" required></textarea><button class="btn secondary" type="submit">{{ __('Request revision') }}</button></form>
<form method="post" action="{{ route('app.projects.pay', $project) }}">@csrf<input type="number" name="amount_minor" required><button class="btn" type="submit">{{ __('Request payment') }}</button></form>
<h2>{{ __('Files') }}</h2>
@foreach($project->files as $f)<p>{{ $f->kind }} · {{ $f->original_name }} · {{ $f->watermarked ? 'WM' : '' }}</p>@endforeach
<h2>{{ __('Payments') }}</h2>
@foreach($payments as $pay)<p>{{ $pay->payment_uuid }} · {{ $pay->status }} · {{ $pay->amount_minor }}</p>@endforeach
<h2>{{ __('Invoices') }}</h2>
@foreach($invoices as $inv)<p>{{ $inv->invoice_number }} · {{ $inv->status }}</p>@endforeach
@endsection

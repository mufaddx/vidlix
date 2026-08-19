@extends('layouts.app')
@section('title', __('Brand'))
@section('content')
<h1>{{ __('Brand verification') }}</h1>

@if(session('status'))
    <div class="banner">{{ session('status') }}</div>
@endif

<p class="muted">{{ __('Status') }}: {{ $profile->verification_status }}</p>

@if($missing !== [])
    <div class="banner">
        {{-- Telling the brand what is missing beats refusing without saying why. --}}
        <strong>{{ __('Verification still needs:') }}</strong> {{ implode(', ', $missing) }}
    </div>
@endif

<form class="form" method="post" action="{{ route('app.brand.save') }}">
    @csrf

    <h2>{{ __('Company') }}</h2>
    <label>{{ __('Trading name') }}<input name="company_name" value="{{ old('company_name', $profile->company_name) }}" required></label>
    <label>{{ __('Registered legal name') }}<input name="legal_name" value="{{ old('legal_name', $profile->legal_name) }}"></label>
    <label>{{ __('Website') }}<input name="website" type="url" value="{{ old('website', $profile->website) }}"></label>
    <label>{{ __('Industry') }}<input name="industry" value="{{ old('industry', $profile->industry) }}"></label>

    <h2>{{ __('Tax and registration') }}</h2>
    <label>{{ __('GSTIN') }}<input name="gstin" value="{{ old('gstin', $profile->gstin) }}" placeholder="27AAAAA0000A1Z5"></label>
    @error('gstin')<p class="error">{{ $message }}</p>@enderror
    <label>{{ __('Company PAN') }}<input name="pan" value="{{ old('pan', $profile->pan) }}" placeholder="AAAAA0000A"></label>
    @error('pan')<p class="error">{{ $message }}</p>@enderror
    <label>{{ __('CIN (if incorporated)') }}<input name="cin" value="{{ old('cin', $profile->cin) }}"></label>

    <h2>{{ __('Registered address') }}</h2>
    <label>{{ __('Address') }}<textarea name="registered_address" rows="3">{{ old('registered_address', $profile->registered_address) }}</textarea></label>
    <label>{{ __('State') }}<input name="billing_state" value="{{ old('billing_state', $profile->billing_state) }}"></label>
    <label>{{ __('Country') }}<input name="billing_country" value="{{ old('billing_country', $profile->billing_country) }}"></label>
    <label>{{ __('PIN code') }}<input name="billing_pincode" value="{{ old('billing_pincode', $profile->billing_pincode) }}"></label>

    <h2>{{ __('Authorised person') }}</h2>
    <p class="muted">{{ __('The person who can sign for the company on Vidlix.') }}</p>
    <label>{{ __('Full name') }}<input name="authorized_person_name" value="{{ old('authorized_person_name', $profile->authorized_person_name) }}"></label>
    <label>{{ __('Designation') }}<input name="authorized_person_designation" value="{{ old('authorized_person_designation', $profile->authorized_person_designation) }}"></label>
    <label>{{ __('Email') }}<input name="authorized_person_email" type="email" value="{{ old('authorized_person_email', $profile->authorized_person_email) }}"></label>
    <label>{{ __('Phone') }}<input name="authorized_person_phone" value="{{ old('authorized_person_phone', $profile->authorized_person_phone) }}"></label>

    <button class="btn" type="submit">{{ __('Save') }}</button>
</form>

<h2>{{ __('Documents') }}</h2>
<p class="muted">{{ __('PDF or image, up to 10 MB. Uploading a document does not verify the brand: an operator reviews it.') }}</p>

<form class="form" method="post" action="{{ route('app.brand.documents.store') }}" enctype="multipart/form-data">
    @csrf
    <label>{{ __('Kind') }}
        <select name="kind" required>
            @foreach($kinds as $value => $label)
                <option value="{{ $value }}">{{ __($label) }}</option>
            @endforeach
        </select>
    </label>
    <label>{{ __('File') }}<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label>
    @error('document')<p class="error">{{ $message }}</p>@enderror
    <button class="btn secondary" type="submit">{{ __('Upload') }}</button>
</form>

@if($documents->isNotEmpty())
<table class="table">
    <thead><tr><th>{{ __('Kind') }}</th><th>{{ __('File') }}</th><th>{{ __('Review') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($documents as $doc)
        <tr>
            <td>{{ __($kinds[$doc->kind] ?? $doc->kind) }}</td>
            <td>{{ $doc->original_name }}</td>
            <td>
                {{ $doc->review_status }}
                @if($doc->review_note)<br><span class="muted">{{ $doc->review_note }}</span>@endif
            </td>
            <td>
                @if($doc->review_status !== 'approved')
                    <form method="post" action="{{ route('app.brand.documents.destroy', $doc) }}">
                        @csrf @method('DELETE')
                        <button class="btn secondary" type="submit">{{ __('Remove') }}</button>
                    </form>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif
@endsection

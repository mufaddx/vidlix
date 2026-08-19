@extends('layouts.app')
@section('title', __('Employees'))
@section('content')
<h1>{{ __('Employees') }}</h1>
<p class="muted">{{ __('Abilities are granted one at a time. Reaching the admin panel grants nothing on its own — every screen checks the specific ability it needs.') }}</p>

@forelse($employees as $employee)
    <div class="card">
        <strong>{{ $employee->user?->name }}</strong>
        <span class="muted">{{ $employee->employee_code }}</span>
        @if($employee->title) · {{ $employee->title }} @endif
        · <span class="kicker">{{ $employee->status }}</span>
        <p class="muted">{{ $employee->user?->email }}</p>

        <form method="post" action="{{ route('admin.employees.abilities', $employee) }}">@csrf
            @foreach($groups as $group => $abilities)
                <p class="kicker">{{ $group }}</p>
                <div class="chips">
                    @foreach($abilities as $ability => $description)
                        @continue($ability === \App\Support\Ability::EMPLOYEES_MANAGE)
                        <label class="chip" title="{{ $description }}">
                            <input type="checkbox" name="abilities[]" value="{{ $ability }}"
                                   @checked(in_array($ability, $employee->abilityList(), true))>
                            <span>{{ $description }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
            <button class="btn" type="submit">{{ __('Save abilities') }}</button>
        </form>

        <form method="post" action="{{ route('admin.employees.status', $employee) }}">@csrf
            <input type="hidden" name="status" value="{{ $employee->status === 'active' ? 'suspended' : 'active' }}">
            <button type="submit">{{ $employee->status === 'active' ? __('Suspend') : __('Reactivate') }}</button>
        </form>
    </div>
@empty
    <p class="muted">{{ __('No employees yet.') }}</p>
@endforelse

<h2>{{ __('Add an employee') }}</h2>
<form class="form" method="post" action="{{ route('admin.employees.store') }}">@csrf
    <label for="name">{{ __('Name') }}</label>
    <input id="name" name="name" required value="{{ old('name') }}">

    <label for="email">{{ __('Work email') }}</label>
    <input id="email" name="email" type="email" required value="{{ old('email') }}">

    <label for="mobile">{{ __('Mobile') }}</label>
    <input id="mobile" name="mobile" value="{{ old('mobile') }}">

    <label for="title">{{ __('Job title') }}</label>
    <input id="title" name="title" value="{{ old('title') }}" placeholder="{{ __('e.g. Support Executive') }}">

    <label for="password">{{ __('Temporary password') }}</label>
    <input id="password" name="password" type="password" required autocomplete="new-password">

    <label for="password_confirmation">{{ __('Confirm password') }}</label>
    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

    <p class="kicker">{{ __('What may they do?') }}</p>
    @foreach($groups as $group => $abilities)
        <p class="muted">{{ $group }}</p>
        <div class="chips">
            @foreach($abilities as $ability => $description)
                @continue($ability === \App\Support\Ability::EMPLOYEES_MANAGE)
                <label class="chip" title="{{ $description }}">
                    <input type="checkbox" name="abilities[]" value="{{ $ability }}">
                    <span>{{ $description }}</span>
                </label>
            @endforeach
        </div>
    @endforeach

    <button class="btn" type="submit">{{ __('Create employee') }}</button>
</form>
<p class="muted">{{ __('An employee ID is generated automatically. Granting abilities is reserved for the super admin and cannot be delegated.') }}</p>
@endsection

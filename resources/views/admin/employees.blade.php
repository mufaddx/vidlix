@extends('layouts.admin')
@section('title', __('Employees'))
@section('subheading', __('Staff accounts and exactly what each of them may do'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Current staff') }} ({{ $employees->count() }})</div>
    <table class="a-table">
        <thead><tr><th>{{ __('Person') }}</th><th>{{ __('ID') }}</th><th>{{ __('Can do') }}</th><th style="width:16%">{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse($employees as $employee)
            <tr>
                <td>
                    {{ $employee->user?->name }}
                    <span class="a-sub">{{ $employee->user?->email }}</span>
                    @if($employee->title)<span class="a-sub">{{ $employee->title }}</span>@endif
                </td>
                <td class="a-sub">{{ $employee->employee_code }}</td>
                <td>
                    @forelse($employee->abilityList() as $ability)
                        <span class="a-tag">{{ \App\Support\Ability::label($ability) }}</span>
                    @empty
                        <span class="a-sub">{{ __('Nothing yet') }}</span>
                    @endforelse
                    <details style="margin-top:8px">
                        <summary style="cursor:pointer;font-size:12px;color:var(--a-muted)">{{ __('Change') }}</summary>
                        <form method="post" action="{{ route('admin.employees.abilities', $employee) }}" style="margin-top:10px">@csrf
                            @foreach($groups as $group => $abilities)
                                <p class="a-group-label" style="padding-left:0">{{ $group }}</p>
                                <div class="a-checks">
                                    @foreach($abilities as $ability => $description)
                                        @continue($ability === \App\Support\Ability::EMPLOYEES_MANAGE)
                                        <label class="a-check">
                                            <input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, $employee->abilityList(), true))>
                                            <span>{{ $description }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                            <button class="a-btn" type="submit">{{ __('Save') }}</button>
                        </form>
                    </details>
                </td>
                <td>
                    <span class="a-tag {{ $employee->status === 'active' ? 'ok' : 'danger' }}">{{ $employee->status }}</span>
                    <form method="post" action="{{ route('admin.employees.status', $employee) }}" style="margin-top:8px">@csrf
                        <input type="hidden" name="status" value="{{ $employee->status === 'active' ? 'suspended' : 'active' }}">
                        <button class="a-btn ghost" type="submit">{{ $employee->status === 'active' ? __('Suspend') : __('Reactivate') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No employees yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Add an employee') }}</div>
    <div class="a-panel-body">
        <form class="a-form" method="post" action="{{ route('admin.employees.store') }}">@csrf
            <div class="a-form-row">
                <div><label for="name">{{ __('Name') }}</label><input id="name" name="name" required value="{{ old('name') }}"></div>
                <div><label for="title">{{ __('Job title') }}</label><input id="title" name="title" value="{{ old('title') }}" placeholder="{{ __('Support Executive') }}"></div>
            </div>
            <div class="a-form-row">
                <div><label for="email">{{ __('Work email') }}</label><input id="email" name="email" type="email" required value="{{ old('email') }}"></div>
                <div><label for="mobile">{{ __('Mobile') }}</label><input id="mobile" name="mobile" value="{{ old('mobile') }}"></div>
            </div>
            <div class="a-form-row">
                <div><label for="password">{{ __('Temporary password') }}</label><span class="field-password"><input id="password" name="password" type="password" required autocomplete="new-password">@include('partials.reveal', ['for' => 'password'])</span></div>
                <div><label for="password_confirmation">{{ __('Confirm') }}</label><span class="field-password"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">@include('partials.reveal', ['for' => 'password_confirmation'])</span></div>
            </div>

            @foreach($groups as $group => $abilities)
                <p class="a-group-label" style="padding-left:0">{{ $group }}</p>
                <div class="a-checks">
                    @foreach($abilities as $ability => $description)
                        @continue($ability === \App\Support\Ability::EMPLOYEES_MANAGE)
                        <label class="a-check">
                            <input type="checkbox" name="abilities[]" value="{{ $ability }}">
                            <span>{{ $description }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach

            <button class="a-btn" type="submit">{{ __('Create employee') }}</button>
            <p class="a-hint">{{ __('An employee ID is generated automatically. Granting abilities stays with the super admin and cannot be handed to an employee.') }}</p>
        </form>
    </div>
</div>
@endsection

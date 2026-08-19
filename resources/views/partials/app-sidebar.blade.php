<aside class="side" id="app-nav">
    <div class="side-head">
        <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
        @include('partials.theme-toggle')
    </div>
    @include('partials.account-switcher')
    <nav class="side-nav" aria-label="{{ __('Workspace') }}">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">{{ __('Home') }}</a>
        <a class="{{ request()->routeIs('app.notifications') ? 'active' : '' }}" href="{{ route('app.notifications') }}">{{ __('Notifications') }}</a>
        @if(auth()->user()?->creatorProfile)
            <a class="{{ request()->routeIs('creator.inbox*') ? 'active' : '' }}" href="{{ route('creator.inbox') }}">{{ __('Inbox') }}</a>
            <a class="{{ request()->routeIs('creator.public-page') ? 'active' : '' }}" href="{{ route('creator.public-page') }}">{{ __('Public page') }}</a>
            <a href="{{ route('app.instagram') }}">{{ __('Instagram') }}</a>
            <a href="{{ route('app.automations') }}">{{ __('Automation') }}</a>
            <a href="{{ route('app.managers') }}">{{ __('Management') }}</a>
        @endif
        <a class="{{ request()->routeIs('app.roles') ? 'active' : '' }}" href="{{ route('app.roles') }}">{{ __('Roles & categories') }}</a>
        <a href="{{ route('app.editors') }}">{{ __('Editor') }}</a>
        <a href="{{ route('app.brand') }}">{{ __('Brand') }}</a>
        @if(auth()->user()?->brandProfile)
            <a class="{{ request()->routeIs('app.discover') ? 'active' : '' }}" href="{{ route('app.discover') }}">{{ __('Find creators') }}</a>
        @endif
        <a href="{{ route('app.campaigns') }}">{{ __('Campaigns') }}</a>
        <a href="{{ route('app.applications') }}">{{ __('Applications') }}</a>
        <a href="{{ route('app.projects') }}">{{ __('Projects') }}</a>
        <a href="{{ route('app.proposals') }}">{{ __('Proposals') }}</a>
        <a href="{{ route('app.chat') }}">{{ __('Chat') }}</a>
        <a href="{{ route('app.portfolio') }}">{{ __('Portfolio') }}</a>
        <a href="{{ route('app.invoices') }}">{{ __('Invoices') }}</a>
        <a href="{{ route('app.earnings') }}">{{ __('Earnings') }}</a>
        <a href="{{ route('app.disputes') }}">{{ __('Disputes') }}</a>
        <a href="{{ route('app.tickets') }}">{{ __('Support') }}</a>
        <a href="{{ route('app.settings') }}">{{ __('Settings') }}</a>
        @if(auth()->user()?->hasRole('super_admin') || auth()->user()?->hasRole('content') || auth()->user()?->hasRole('operations') || auth()->user()?->hasRole('finance') || auth()->user()?->hasRole('verification') || auth()->user()?->hasRole('support'))
            <a href="{{ route('admin.dashboard') }}">{{ __('Admin') }}</a>
            <a href="{{ route('admin.verification') }}">{{ __('Verify') }}</a>
            <a href="{{ route('admin.finance') }}">{{ __('Finance') }}</a>
        @endif
    </nav>

    @if(auth()->user()?->isStaff())
        <p class="switcher-label">{{ __('Admin') }}</p>
        <nav class="side-nav" aria-label="{{ __('Admin') }}">
            <a href="{{ route('admin.dashboard') }}">{{ __('Overview') }}</a>
            @can(\App\Support\Ability::SUPPORT_VIEW)<a href="{{ route('admin.help-desk') }}">{{ __('Help desk') }}</a>@endcan
            @can(\App\Support\Ability::VERIFICATION_DECIDE)<a href="{{ route('admin.verification') }}">{{ __('Verification') }}</a>@endcan
            @can(\App\Support\Ability::FINANCE_VIEW)<a href="{{ route('admin.finance') }}">{{ __('Finance') }}</a>@endcan
            @can(\App\Support\Ability::DISPUTES_RESOLVE)<a href="{{ route('admin.disputes') }}">{{ __('Disputes') }}</a>@endcan
            @can(\App\Support\Ability::MANAGERS_VIEW)<a href="{{ route('admin.managers') }}">{{ __('Managers') }}</a>@endcan
            @can(\App\Support\Ability::USERS_VIEW)<a href="{{ route('admin.users') }}">{{ __('Members') }}</a>@endcan
            @can(\App\Support\Ability::CMS_MANAGE)<a href="{{ route('admin.cms') }}">{{ __('Website copy') }}</a>@endcan
            @can(\App\Support\Ability::EMPLOYEES_MANAGE)<a href="{{ route('admin.employees') }}">{{ __('Employees') }}</a>@endcan
        </nav>
    @endif
    <form method="post" action="{{ route('workspace.switch') }}">
        @csrf
        <label class="hp" for="active-role">{{ __('Workspace') }}</label>
        <select id="active-role" name="role" onchange="this.form.submit()">
            @foreach(auth()->user()->roleSlugs() as $role)
                <option value="{{ $role }}" @selected(session('active_role')===$role)>{{ $role }}</option>
            @endforeach
        </select>
    </form>
</aside>

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
            <a class="{{ request()->routeIs('inbox*') ? 'active' : '' }}" href="{{ route('inbox') }}">{{ __('Inbox') }}</a>
            <a class="{{ request()->routeIs('creator.public-page') ? 'active' : '' }}" href="{{ route('creator.public-page') }}">{{ __('Public page') }}</a>
            <a class="{{ request()->routeIs('app.contact-form') ? 'active' : '' }}" href="{{ route('app.contact-form') }}">{{ __('Contact form') }}</a>
            <a class="{{ request()->routeIs('app.custom-domain') ? 'active' : '' }}" href="{{ route('app.custom-domain') }}">{{ __('Custom domain') }}</a>
            <a href="{{ route('app.instagram') }}">{{ __('Instagram') }}</a>
            <a class="{{ request()->routeIs('autodm.*') ? 'active' : '' }}" href="{{ route('autodm.index') }}">{{ __('AutoDM') }}</a>
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
        <a class="{{ request()->routeIs('app.negotiations*') ? 'active' : '' }}" href="{{ route('app.negotiations') }}">{{ __('Negotiations') }}</a>
        <a href="{{ route('app.chat') }}">{{ __('Chat') }}</a>
        <a href="{{ route('app.portfolio') }}">{{ __('Portfolio') }}</a>
        <a href="{{ route('app.invoices') }}">{{ __('Invoices') }}</a>
        <a href="{{ route('app.earnings') }}">{{ __('Earnings') }}</a>
        <a href="{{ route('app.disputes') }}">{{ __('Disputes') }}</a>
        <a href="{{ route('app.tickets') }}">{{ __('Support') }}</a>
        <a href="{{ route('app.settings') }}">{{ __('Settings') }}</a>
    </nav>

    @if(auth()->user()?->isStaff())
        {{-- One link only. The admin panel is a separate place with its own
             navigation; mixing its screens into the member sidebar is what made
             the two feel like one product. --}}
        <p class="switcher-label">{{ __('Staff') }}</p>
        <nav class="side-nav" aria-label="{{ __('Staff') }}">
            <a href="{{ route('admin.dashboard') }}">{{ __('Open admin panel') }}</a>
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

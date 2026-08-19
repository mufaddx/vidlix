@php($accounts = app(\App\Services\Workspace\WorkspaceContext::class)->switchableAccounts(auth()->user()))
@if($accounts->count() > 1)
    <div class="switcher">
        <p class="switcher-label">{{ __('Acting as') }}</p>
        @foreach($accounts as $account)
            <form method="post" action="{{ route('workspace.manage') }}">@csrf
                <input type="hidden" name="owner_user_id" value="{{ $account['owner_user_id'] }}">
                <input type="hidden" name="scope" value="{{ $account['scope'] }}">
                <button type="submit" class="switcher-item {{ $account['active'] ? 'is-active' : '' }}" @disabled($account['active'])>
                    <span class="switcher-name">{{ $account['label'] }}</span>
                    <span class="switcher-sub">{{ $account['sublabel'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
@endif

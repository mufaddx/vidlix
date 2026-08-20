@php($profiles = app(\App\Services\Workspace\WorkspaceContext::class)->switchableProfiles(auth()->user()))
@if($profiles->count() > 1)
    <div class="switcher">
        <p class="switcher-label">{{ __('Working in') }}</p>
        @foreach($profiles as $profile)
            <form method="post" action="{{ route('workspace.switch') }}">@csrf
                <input type="hidden" name="role" value="{{ $profile['type'] }}">
                <button type="submit" class="switcher-item {{ $profile['active'] ? 'is-active' : '' }}" @disabled($profile['active'])>
                    <span class="switcher-name">{{ $profile['label'] }}</span>
                </button>
            </form>
        @endforeach
    </div>
@endif

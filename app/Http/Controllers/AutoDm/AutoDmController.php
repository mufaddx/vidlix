<?php

namespace App\Http\Controllers\AutoDm;

use App\Http\Controllers\Controller;
use App\Models\AutodmAutomation;
use App\Models\AutodmRun;
use App\Models\InstagramAccount;
use App\Models\InstagramMedium;
use App\Services\AutoDm\AutomationBuilder;
use App\Services\AutoDm\Capabilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The AutoDM dashboard.
 *
 * Automations are addressed by uuid and always scoped to the signed-in user, so
 * a uuid belonging to somebody else is a 404 rather than a refusal — the same
 * rule the rest of the application follows.
 */
class AutoDmController extends Controller
{
    public function __construct(
        private AutomationBuilder $builder,
        private Capabilities $capabilities,
    ) {}

    public function index(Request $request): View
    {
        $account = $this->account($request);

        return view('autodm.dashboard', [
            'account' => $account,
            'capabilities' => $account ? $this->capabilities->summaryFor($account) : [],
            'providerConfigured' => $this->capabilities->providerConfigured(),
            'media' => $account
                ? InstagramMedium::query()
                    ->where('instagram_account_id', $account->id)
                    ->orderByDesc('published_at')
                    ->limit(24)
                    ->get()
                : collect(),
            'automations' => AutodmAutomation::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get(),
            'recentRuns' => $account
                ? AutodmRun::query()
                    ->whereIn('autodm_automation_id', AutodmAutomation::query()
                        ->where('user_id', $request->user()->id)
                        ->select('id'))
                    ->latest()
                    ->limit(20)
                    ->get()
                : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $account = $this->account($request);

        abort_unless($account !== null, 403, __('Connect Instagram before building an automation.'));

        return view('autodm.build', [
            'account' => $account,
            'automation' => null,
            'version' => null,
            'capabilities' => $this->capabilities->summaryFor($account),
            'media' => InstagramMedium::query()
                ->where('instagram_account_id', $account->id)
                ->orderByDesc('published_at')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->account($request);

        abort_unless($account !== null, 403);

        $automation = $this->builder->create($request->user(), $account, $this->input($request));

        return redirect()
            ->route('autodm.review', $automation->uuid)
            ->with('status', __('Saved as a draft. Nothing runs until you activate it.'));
    }

    public function edit(Request $request, string $uuid): View
    {
        $automation = $this->mine($request, $uuid);

        return view('autodm.build', [
            'account' => $automation->account,
            'automation' => $automation,
            'version' => $automation->draftVersion(),
            'capabilities' => $automation->account
                ? $this->capabilities->summaryFor($automation->account)
                : [],
            'media' => InstagramMedium::query()
                ->where('instagram_account_id', $automation->instagram_account_id)
                ->orderByDesc('published_at')
                ->get(),
        ]);
    }

    public function update(Request $request, string $uuid): RedirectResponse
    {
        $automation = $this->mine($request, $uuid);

        $this->builder->saveDraft($automation, $this->input($request));

        return redirect()
            ->route('autodm.review', $automation->uuid)
            ->with('status', __('Draft saved.'));
    }

    /** The last screen before anything is switched on. */
    public function review(Request $request, string $uuid): View
    {
        $automation = $this->mine($request, $uuid);

        return view('autodm.review', $this->builder->review($automation));
    }

    public function activate(Request $request, string $uuid): RedirectResponse
    {
        $automation = $this->mine($request, $uuid);

        // Permissions, media ownership and provider capability are all checked
        // again inside activate(), not trusted from when this was drafted.
        $this->builder->activate($automation, $request->user());

        return redirect()
            ->route('autodm.index')
            ->with('status', __('Active. It runs on new comments from now on.'));
    }

    public function deactivate(Request $request, string $uuid): RedirectResponse
    {
        $this->builder->deactivate($this->mine($request, $uuid), $request->user());

        return back()->with('status', __('Switched off. Nothing further will be sent.'));
    }

    public function duplicate(Request $request, string $uuid): RedirectResponse
    {
        $copy = $this->builder->duplicate($this->mine($request, $uuid), $request->user());

        return redirect()
            ->route('autodm.edit', $copy->uuid)
            ->with('status', __('Copied as a draft.'));
    }

    public function runs(Request $request, string $uuid): View
    {
        $automation = $this->mine($request, $uuid);

        return view('autodm.runs', [
            'automation' => $automation,
            'runs' => AutodmRun::query()
                ->where('autodm_automation_id', $automation->id)
                ->latest()
                ->paginate(50),
        ]);
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'instagram_media_id' => ['nullable', 'integer'],
            'trigger_type' => ['required', 'in:any_comment,keywords'],
            'keywords' => ['nullable', 'string', 'max:4000'],
            'public_reply_text' => ['nullable', 'string', 'max:1000'],
            'private_reply_text' => ['nullable', 'string', 'max:1000'],
            'private_reply_url' => ['nullable', 'string', 'max:2000'],
        ]);

        return $data + [
            'whole_word' => $request->boolean('whole_word'),
            'public_reply_enabled' => $request->boolean('public_reply_enabled'),
            'private_reply_enabled' => $request->boolean('private_reply_enabled'),
        ];
    }

    private function account(Request $request): ?InstagramAccount
    {
        $profile = $request->user()->creatorProfile;

        return $profile === null
            ? null
            : InstagramAccount::query()->where('creator_profile_id', $profile->id)->first();
    }

    private function mine(Request $request, string $uuid): AutodmAutomation
    {
        $automation = AutodmAutomation::query()
            ->where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($automation !== null, 404);

        return $automation;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\InstagramProviderInterface;
use App\Contracts\PaymentProviderInterface;
use App\Contracts\PayoutProviderInterface;
use App\Contracts\PushProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\CampaignApplication;
use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\ManagerAssignment;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Withdrawal;
use App\Services\Audit\AuditLogger;
use App\Services\Email\OutboundEmailService;
use App\Services\Identity\AccountProvisioner;
use App\Services\Ledger\LedgerService;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The workspace half of /api/v1: the endpoints the Flutter client needs to
 * reach parity with the web workspace. Every response is derived from the same
 * models the Blade views use, so the two clients can never disagree.
 */
class WorkspaceApiController extends Controller
{
    public function projects(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->where(fn ($q) => $q
                ->where('owner_user_id', $request->user()->id)
                ->orWhere('counterparty_user_id', $request->user()->id))
            ->latest()
            ->paginate(20);

        return $this->ok($request, $projects);
    }

    public function project(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->involves($request->user()), 404);

        return $this->ok($request, [
            'project' => $project,
            // Sent so the app never keeps its own copy of the state machine.
            'next_states' => MarketplaceEngine::projectTransitions()[$project->status] ?? [],
            'files' => $project->files()->latest()->get(),
            'revisions' => $project->revisions()->latest()->get(),
            'payments' => Payment::query()
                ->where('payable_type', Project::class)
                ->where('payable_id', $project->getKey())
                ->latest()
                ->get(),
        ]);
    }

    public function transitionProject(Request $request, Project $project, MarketplaceEngine $engine): JsonResponse
    {
        abort_unless($project->involves($request->user()), 404);
        $data = $request->validate(['status' => ['required', 'string', 'max:40']]);
        $engine->transitionProject($project, $data['status']);

        return $this->ok($request, ['status' => $project->fresh()->status]);
    }

    public function applications(Request $request): JsonResponse
    {
        $profile = $request->user()->creatorProfile;
        if (! $profile) {
            return $this->ok($request, []);
        }

        return $this->ok($request, CampaignApplication::query()
            ->where('creator_profile_id', $profile->id)
            ->with('campaign:id,name,slug,status')
            ->latest()
            ->paginate(20));
    }

    /**
     * Earnings are a projection over ledger entries. Nothing is read from a
     * stored balance column, because no such column exists.
     */
    public function earnings(Request $request, LedgerService $ledger): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $accountIds = LedgerAccount::query()->where('user_id', $userId)->pluck('id');

        return $this->ok($request, [
            'currency' => config('vidlix.currency', 'INR'),
            'available_minor' => $ledger->balance($userId, LedgerService::STATE_AVAILABLE),
            'reserved_minor' => $ledger->balance($userId, LedgerService::STATE_RESERVED),
            'withdrawn_minor' => $ledger->balance($userId, LedgerService::STATE_WITHDRAWN),
            'entries' => LedgerEntry::query()->whereIn('ledger_account_id', $accountIds)->latest()->limit(100)->get(),
            'withdrawals' => Withdrawal::query()->where('user_id', $userId)->latest()->get(),
            'payout_provider_configured' => app(PayoutProviderInterface::class)->isConfigured(),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        return $this->ok($request, Invoice::query()
            ->where(fn ($q) => $q
                ->where('seller_user_id', $request->user()->id)
                ->orWhere('buyer_user_id', $request->user()->id))
            ->with('items')
            ->latest()
            ->paginate(20));
    }

    public function managers(Request $request): JsonResponse
    {
        return $this->ok($request, [
            'representing' => ManagerAssignment::query()
                ->active()
                ->where('manager_user_id', $request->user()->id)
                ->with('owner:id,name,email')
                ->get(),
            'my_managers' => ManagerAssignment::query()
                ->active()
                ->where('owner_user_id', $request->user()->id)
                ->with('manager:id,name,email')
                ->get(),
        ]);
    }

    public function postMessage(Request $request, string $uuid, MarketplaceEngine $engine): JsonResponse
    {
        $conversation = Conversation::query()->where('conversation_uuid', $uuid)->firstOrFail();
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);

        if ($conversation->channel === 'internal') {
            $message = $engine->postInternalMessage($conversation, $request->user(), $data['body']);

            return $this->ok($request, ['id' => $message->id, 'delivery_status' => $message->delivery_status], 201);
        }

        // External threads leave the platform, so they go through the outbound
        // email path and are never marked sent by this request.
        $profile = $request->user()->creatorProfile;
        abort_unless($profile && $conversation->creator_profile_id === $profile->id, 404);

        $outbound = app(OutboundEmailService::class);
        $message = $conversation->messages()->create([
            'actor_user_id' => $request->user()->id,
            'acting_for_creator_id' => session('acting_for_creator_id'),
            'direction' => 'outbound',
            'body' => $data['body'],
            'delivery_status' => $outbound->initialStatus(),
        ]);
        $conversation->update(['last_message_at' => now()]);
        $outbound->queue($message);

        return $this->ok($request, ['id' => $message->id, 'delivery_status' => $message->fresh()->delivery_status], 201);
    }

    public function paymentStatus(Request $request, string $uuid, PaymentProviderInterface $payments): JsonResponse
    {
        $payment = Payment::query()->where('payment_uuid', $uuid)->firstOrFail();
        abort_unless($payment->payer_user_id === $request->user()->id, 404);

        return $this->ok($request, [
            'payment_uuid' => $payment->payment_uuid,
            'status' => $payment->status,
            'amount_minor' => $payment->amount_minor,
            'currency' => $payment->currency,
            'checkout_url' => $payment->checkout_url,
            'provider_configured' => $payments->isConfigured(),
            // Returning from the checkout page does not change this status.
            'note' => 'A payment is only captured once a signed provider webhook is confirmed against the provider API.',
        ]);
    }

    public function instagram(Request $request, InstagramProviderInterface $instagram): JsonResponse
    {
        $profile = $request->user()->creatorProfile;
        $account = $profile?->instagramAccount;

        return $this->ok($request, [
            'provider_configured' => $instagram->isConfigured(),
            'status' => $account->status ?? 'not_connected',
            'username' => $account->username ?? null,
            'last_synced_at' => $account->last_synced_at ?? null,
            // Absent means the Graph API did not return it. Nothing is filled in.
            'insights' => $account->insights ?? [],
            'connect_url' => $profile ? $instagram->authorizationUrl($profile) : null,
        ]);
    }

    /**
     * Take on another role from the phone.
     *
     * The same rules as the website: an account is an account, and a person
     * adds creator, editor or brand to it whenever they decide to. Manager is
     * absent on purpose - nobody applies to be one.
     */
    public function applyForRole(Request $request, AccountProvisioner $provisioner, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:creator,editor,brand'],
        ]);

        $user = $request->user();

        if (in_array($data['role'], $user->roleSlugs(), true)) {
            return $this->ok($request, ['roles' => $user->roleSlugs(), 'already_held' => true]);
        }

        $role = Role::query()->where('slug', $data['role'])->firstOrFail();
        $user->roles()->attach($role);
        $provisioner->provisionRole($user, $role->slug);
        $audit->record('role.applied', $user, ['role' => $role->slug]);

        return $this->ok($request, [
            'roles' => $user->fresh()->roleSlugs(),
            'already_held' => false,
        ], 201);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:android,ios,web'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        DeviceToken::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $data['token']],
            [
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return $this->ok($request, [
            'registered' => true,
            'push_provider_configured' => app(PushProviderInterface::class)->isConfigured(),
        ], 201);
    }

    private function ok(Request $request, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => $data,
            'request_id' => $request->attributes->get('request_id'),
        ], $status);
    }
}

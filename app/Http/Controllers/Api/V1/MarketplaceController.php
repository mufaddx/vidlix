<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentProviderInterface;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\CreatorProfile;
use App\Models\EditorProfile;
use App\Models\Project;
use App\Models\User;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function creators(): JsonResponse
    {
        $creators = CreatorProfile::query()
            ->where('visibility', 'public')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => $creators,
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }

    /**
     * Starts a hosted checkout and records the pending payment so a later
     * webhook can be matched to it. Returning a URL is not a payment: the
     * response never reports success, only that a checkout exists.
     */
    public function createPayment(Request $request, PaymentProviderInterface $payments, MarketplaceEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        if (! $payments->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Payments cannot start until PAYMENT_PROVIDER credentials are configured. No success state was recorded.',
                'code' => 'PROVIDER_NOT_CONFIGURED',
                'data' => $payments->createCheckout($data['amount_minor'], strtoupper($data['currency']), []),
                'request_id' => $request->attributes->get('request_id'),
            ], 503);
        }

        if (! filled($data['project_id'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'A payment must belong to a project so the provider webhook can be matched to it.',
                'code' => 'PROJECT_REQUIRED',
                'request_id' => $request->attributes->get('request_id'),
            ], 422);
        }

        $project = Project::query()->findOrFail($data['project_id']);
        abort_unless($project->involves($request->user()), 404);

        $payment = $engine->requestPayment(
            $request->user(),
            $project,
            (int) $data['amount_minor'],
            'project',
        );

        return response()->json([
            'success' => filled($payment->checkout_url),
            'message' => $payment->last_provider_detail,
            'code' => filled($payment->checkout_url) ? 'CHECKOUT_CREATED' : 'CHECKOUT_UNAVAILABLE',
            'data' => [
                'payment_uuid' => $payment->payment_uuid,
                'status' => $payment->status,
                'checkout_url' => $payment->checkout_url,
                'amount_minor' => $payment->amount_minor,
                'currency' => $payment->currency,
            ],
            'request_id' => $request->attributes->get('request_id'),
        ], filled($payment->checkout_url) ? 201 : 502);
    }

    public function editors(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => EditorProfile::query()->where('application_status', 'approved')->paginate(20),
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }

    public function campaigns(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => Campaign::query()->where('status', 'published')->paginate(20),
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }

    public function apply(Request $request, Campaign $campaign, MarketplaceEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'proposed_fee_minor' => ['required', 'integer', 'min:1'],
            'message' => ['required', 'string'],
        ]);
        $application = $engine->applyToCampaign($request->user(), $campaign, $data);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => ['id' => $application->id, 'status' => $application->status],
            'request_id' => $request->attributes->get('request_id'),
        ], 201);
    }

    public function storeProject(Request $request, MarketplaceEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'counterparty_user_id' => ['required', 'exists:users,id'],
            'total_amount_minor' => ['required', 'integer', 'min:1'],
            'advance_amount_minor' => ['nullable', 'integer'],
        ]);
        $project = $engine->createProject($request->user(), User::query()->findOrFail($data['counterparty_user_id']), $data);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => ['id' => $project->id, 'status' => $project->status],
            'request_id' => $request->attributes->get('request_id'),
        ], 201);
    }

    public function withdraw(Request $request, MarketplaceEngine $engine): JsonResponse
    {
        $w = $engine->requestWithdrawal(
            $request->user(),
            (int) $request->validate(['amount_minor' => ['required', 'integer', 'min:1']])['amount_minor'],
        );

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => ['id' => $w->id, 'status' => $w->status],
            'request_id' => $request->attributes->get('request_id'),
        ], 201);
    }

    public function inbox(Request $request): JsonResponse
    {
        $profile = $request->user()->creatorProfile;
        $conversations = Conversation::query()
            ->when($profile, fn ($q) => $q->where('creator_profile_id', $profile->id))
            ->when(! $profile, fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('user_id', $request->user()->id)))
            ->with('externalContact')
            ->latest('last_message_at')
            ->paginate(30);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => $conversations,
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }

    public function messages(Request $request, string $uuid): JsonResponse
    {
        $conversation = Conversation::query()->where('conversation_uuid', $uuid)->firstOrFail();
        $user = $request->user();
        $allowed = $conversation->participants()->where('user_id', $user->id)->exists()
            || $user->creatorProfile?->id === $conversation->creator_profile_id;
        abort_unless($allowed, 404);

        return response()->json([
            'success' => true,
            'code' => 'OK',
            'data' => $conversation->messages()->latest()->limit(100)->get(),
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }
}

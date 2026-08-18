<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentSettlementTest extends TestCase
{
    use RefreshDatabase;

    private function configureRazorpay(): void
    {
        config([
            'vidlix.providers.payment' => 'razorpay',
            'vidlix.payment.key_id' => 'rzp_test_key',
            'vidlix.payment.key_secret' => 'rzp_test_secret',
            'vidlix.payment.api_base' => 'https://api.razorpay.com/v1',
            'vidlix.webhooks.payment_secret' => 'payment-hook-secret',
        ]);
    }

    private function project(): Project
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        return Project::query()->create([
            'name' => 'Six reels',
            'status' => 'awaiting_advance',
            'total_amount_minor' => 500000,
            'advance_amount_minor' => 250000,
            'revision_limit' => 2,
            'owner_user_id' => $buyer->id,
            'counterparty_user_id' => $seller->id,
        ]);
    }

    private function signedPost(string $uri, array $payload, string $secret, array $headers = [])
    {
        $body = json_encode($payload);

        return $this->call('POST', $uri, [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $headers), $body);
    }

    public function test_checkout_returns_a_url_without_marking_anything_paid(): void
    {
        $this->configureRazorpay();
        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::response([
                'id' => 'plink_ABC123',
                'short_url' => 'https://rzp.io/i/abc123',
                'status' => 'created',
            ], 200),
        ]);

        $project = $this->project();
        $payment = app(MarketplaceEngine::class)->requestPayment(
            $project->owner,
            $project,
            250000,
            'advance',
        );

        $this->assertSame('pending', $payment->status);
        $this->assertSame('plink_ABC123', $payment->provider_payment_id);
        $this->assertSame('https://rzp.io/i/abc123', $payment->checkout_url);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_signed_webhook_settles_only_after_the_provider_api_confirms_payment(): void
    {
        $this->configureRazorpay();
        $project = $this->project();
        $payment = Payment::query()->create([
            'payment_uuid' => 'uuid-advance-1',
            'status' => 'pending',
            'amount_minor' => 250000,
            'currency' => 'INR',
            'provider' => 'razorpay',
            'provider_payment_id' => 'plink_ABC123',
            'payer_user_id' => $project->owner_user_id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_ABC123' => Http::response([
                'id' => 'plink_ABC123',
                'status' => 'paid',
                'amount' => 250000,
                'amount_paid' => 250000,
                'currency' => 'INR',
            ], 200),
        ]);

        $this->signedPost('/webhooks/payment', [
            'id' => 'evt_pay_1',
            'event' => 'payment_link.paid',
            'payload' => ['payment_link' => ['entity' => ['id' => 'plink_ABC123', 'reference_id' => 'uuid-advance-1']]],
        ], 'payment-hook-secret')->assertOk()->assertJsonPath('outcome', 'settled');

        $this->assertSame('captured', $payment->fresh()->status);
        $this->assertDatabaseHas('ledger_entries', [
            'state' => 'reserved',
            'amount_minor' => 250000,
            'provider_reference' => 'plink_ABC123',
        ]);
        // The seller, not the payer, is credited.
        $this->assertDatabaseHas('ledger_accounts', ['user_id' => $project->counterparty_user_id, 'kind' => 'earnings']);
    }

    public function test_signed_webhook_does_not_settle_when_the_provider_still_reports_unpaid(): void
    {
        $this->configureRazorpay();
        $project = $this->project();
        Payment::query()->create([
            'payment_uuid' => 'uuid-advance-2',
            'status' => 'pending',
            'amount_minor' => 250000,
            'currency' => 'INR',
            'provider' => 'razorpay',
            'provider_payment_id' => 'plink_UNPAID',
            'payer_user_id' => $project->owner_user_id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_UNPAID' => Http::response([
                'id' => 'plink_UNPAID',
                'status' => 'created',
                'amount' => 250000,
                'amount_paid' => 0,
            ], 200),
        ]);

        $this->signedPost('/webhooks/payment', [
            'id' => 'evt_pay_2',
            'event' => 'payment_link.paid',
            'payload' => ['payment_link' => ['entity' => ['id' => 'plink_UNPAID', 'reference_id' => 'uuid-advance-2']]],
        ], 'payment-hook-secret')->assertOk()->assertJsonPath('outcome', 'not_confirmed');

        $this->assertDatabaseHas('payments', ['payment_uuid' => 'uuid-advance-2', 'status' => 'pending']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_a_replayed_payment_webhook_credits_the_ledger_only_once(): void
    {
        $this->configureRazorpay();
        $project = $this->project();
        Payment::query()->create([
            'payment_uuid' => 'uuid-advance-3',
            'status' => 'pending',
            'amount_minor' => 250000,
            'currency' => 'INR',
            'provider' => 'razorpay',
            'provider_payment_id' => 'plink_REPLAY',
            'payer_user_id' => $project->owner_user_id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        Http::fake([
            'api.razorpay.com/v1/payment_links/plink_REPLAY' => Http::response([
                'id' => 'plink_REPLAY',
                'status' => 'paid',
                'amount_paid' => 250000,
                'currency' => 'INR',
            ], 200),
        ]);

        $payload = [
            'id' => 'evt_pay_3',
            'event' => 'payment_link.paid',
            'payload' => ['payment_link' => ['entity' => ['id' => 'plink_REPLAY', 'reference_id' => 'uuid-advance-3']]],
        ];

        $this->signedPost('/webhooks/payment', $payload, 'payment-hook-secret')->assertOk();
        $this->signedPost('/webhooks/payment', $payload, 'payment-hook-secret')
            ->assertOk()
            ->assertJsonPath('code', 'DUPLICATE_IGNORED');

        $this->assertSame(1, LedgerEntry::query()->where('state', 'reserved')->count());
    }

    public function test_a_forged_webhook_cannot_block_the_genuine_event_that_follows_it(): void
    {
        $this->configureRazorpay();

        $payload = ['id' => 'evt_pay_4', 'event' => 'payment_link.paid'];

        $this->signedPost('/webhooks/payment', $payload, 'the-wrong-secret')->assertStatus(401);
        // The genuine delivery of the same event id must still be accepted.
        $this->signedPost('/webhooks/payment', $payload, 'payment-hook-secret')
            ->assertOk()
            ->assertJsonPath('code', 'ACCEPTED');
    }

    public function test_a_webhook_without_payment_credentials_settles_nothing(): void
    {
        // Signature secret present, provider credentials absent: authentic, but
        // there is no authoritative API to confirm the amount against.
        config([
            'vidlix.providers.payment' => 'unconfigured',
            'vidlix.webhooks.payment_secret' => 'payment-hook-secret',
        ]);

        $project = $this->project();
        Payment::query()->create([
            'payment_uuid' => 'uuid-advance-4',
            'status' => 'pending',
            'amount_minor' => 250000,
            'currency' => 'INR',
            'provider' => 'unconfigured',
            'payer_user_id' => $project->owner_user_id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        $this->signedPost('/webhooks/payment', [
            'id' => 'evt_pay_5',
            'event' => 'payment_link.paid',
            'payment_uuid' => 'uuid-advance-4',
        ], 'payment-hook-secret')->assertOk()->assertJsonPath('outcome', 'provider_not_configured');

        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseHas('payments', ['payment_uuid' => 'uuid-advance-4', 'status' => 'awaiting_provider']);
    }

    public function test_completing_a_project_releases_escrow_with_appended_entries_only(): void
    {
        $this->configureRazorpay();
        $project = $this->project();
        $payment = Payment::query()->create([
            'payment_uuid' => 'uuid-final-1',
            'status' => 'captured',
            'amount_minor' => 500000,
            'currency' => 'INR',
            'provider' => 'razorpay',
            'provider_payment_id' => 'plink_DONE',
            'payer_user_id' => $project->owner_user_id,
            'payable_type' => Project::class,
            'payable_id' => $project->id,
        ]);

        $engine = app(MarketplaceEngine::class);
        $engine->releaseProjectEarnings($project);

        $seller = User::query()->find($project->counterparty_user_id);
        $this->assertSame(500000, $seller->availableLedgerMinor());
        // Reserved is zeroed by a reversing entry, not by editing the original.
        $this->assertSame(-500000, (int) LedgerEntry::query()->where('state', 'reserved')->sum('amount_minor'));
        $this->assertSame('settled', $payment->fresh()->status);
    }
}

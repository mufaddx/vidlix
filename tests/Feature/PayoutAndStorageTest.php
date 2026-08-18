<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\PayoutAccount;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Role;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\Ledger\LedgerService;
use App\Services\Marketplace\MarketplaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayoutAndStorageTest extends TestCase
{
    use RefreshDatabase;

    private function configureRazorpayX(): void
    {
        config([
            'vidlix.providers.payout' => 'razorpayx',
            'vidlix.payout.key_id' => 'rzp_test_key',
            'vidlix.payout.key_secret' => 'rzp_test_secret',
            'vidlix.payout.account_number' => '2323230000000000',
            'vidlix.payout.api_base' => 'https://api.razorpay.com/v1',
            'vidlix.webhooks.payout_secret' => 'payout-hook-secret',
        ]);
    }

    /** Gives the user a real available balance through append-only entries. */
    private function fundedUser(int $availableMinor): User
    {
        $user = User::factory()->create();
        app(LedgerService::class)->append(
            userId: $user->id,
            kind: 'earnings',
            state: LedgerService::STATE_AVAILABLE,
            amountMinor: $availableMinor,
            currency: 'INR',
            meta: ['reason' => 'test_fixture'],
        );

        return $user;
    }

    private function signedPost(string $uri, array $payload, string $secret)
    {
        $body = json_encode($payload);

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_a_withdrawal_cannot_exceed_the_ledger_balance(): void
    {
        $this->configureRazorpayX();
        $user = $this->fundedUser(100000);

        $this->expectException(ValidationException::class);
        app(MarketplaceEngine::class)->requestWithdrawal($user, 200000);
    }

    public function test_admin_approval_instructs_the_provider_without_touching_the_ledger(): void
    {
        $this->configureRazorpayX();
        $user = $this->fundedUser(100000);
        $account = PayoutAccount::query()->create([
            'user_id' => $user->id,
            'provider_beneficiary_ref' => 'fa_TEST123',
            'masked_account' => 'XXXX1234',
            'status' => 'verified',
        ]);

        Http::fake([
            'api.razorpay.com/v1/payouts' => Http::response([
                'id' => 'pout_TEST1',
                'status' => 'queued',
                'amount' => 100000,
            ], 200),
        ]);

        $engine = app(MarketplaceEngine::class);
        $withdrawal = $engine->requestWithdrawal($user, 100000);
        $this->assertSame($account->id, $withdrawal->payout_account_id);

        $result = $engine->approveWithdrawal($withdrawal);

        $this->assertSame('processing', $result['status']);
        $this->assertSame('pout_TEST1', $withdrawal->fresh()->provider_payout_id);
        // Instructed, not settled: the balance is unchanged until the webhook.
        $this->assertSame(100000, $user->fresh()->availableLedgerMinor());
    }

    public function test_only_a_confirmed_payout_webhook_debits_the_ledger(): void
    {
        $this->configureRazorpayX();
        $user = $this->fundedUser(100000);
        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount_minor' => 100000,
            'currency' => 'INR',
            'status' => 'processing',
            'provider_payout_id' => 'pout_TEST1',
        ]);

        Http::fake([
            'api.razorpay.com/v1/payouts/pout_TEST1' => Http::response([
                'id' => 'pout_TEST1',
                'status' => 'processed',
                'amount' => 100000,
            ], 200),
        ]);

        $this->signedPost('/webhooks/payout', [
            'id' => 'evt_payout_1',
            'event' => 'payout.processed',
            'payload' => ['payout' => ['entity' => ['id' => 'pout_TEST1', 'reference_id' => 'wd_'.$withdrawal->id]]],
        ], 'payout-hook-secret')->assertOk()->assertJsonPath('outcome', 'settled');

        $this->assertSame('paid', $withdrawal->fresh()->status);
        $this->assertSame(0, $user->fresh()->availableLedgerMinor());
        $this->assertSame(100000, (int) LedgerEntry::query()->where('state', 'withdrawn')->sum('amount_minor'));
    }

    public function test_a_payout_webhook_the_provider_has_not_processed_settles_nothing(): void
    {
        $this->configureRazorpayX();
        $user = $this->fundedUser(100000);
        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount_minor' => 100000,
            'currency' => 'INR',
            'status' => 'processing',
            'provider_payout_id' => 'pout_PENDING',
        ]);

        Http::fake([
            'api.razorpay.com/v1/payouts/pout_PENDING' => Http::response([
                'id' => 'pout_PENDING',
                'status' => 'queued',
            ], 200),
        ]);

        $this->signedPost('/webhooks/payout', [
            'id' => 'evt_payout_2',
            'event' => 'payout.processed',
            'payload' => ['payout' => ['entity' => ['id' => 'pout_PENDING', 'reference_id' => 'wd_'.$withdrawal->id]]],
        ], 'payout-hook-secret')->assertOk()->assertJsonPath('outcome', 'not_confirmed');

        $this->assertSame('processing', $withdrawal->fresh()->status);
        $this->assertSame(100000, $user->fresh()->availableLedgerMinor());
    }

    public function test_an_admin_cannot_mark_a_withdrawal_paid_by_hand(): void
    {
        $this->configureRazorpayX();
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']));

        $user = $this->fundedUser(100000);
        $withdrawal = Withdrawal::query()->create([
            'user_id' => $user->id,
            'amount_minor' => 100000,
            'currency' => 'INR',
            'status' => 'requested',
        ]);

        $this->actingAs($admin)
            ->post('/admin/withdrawals/'.$withdrawal->id, ['decision' => 'paid'])
            ->assertSessionHasErrors('decision');

        $this->assertSame('requested', $withdrawal->fresh()->status);
        $this->assertSame(100000, $user->fresh()->availableLedgerMinor());
    }

    public function test_project_media_goes_to_object_storage_and_the_database_keeps_only_the_key(): void
    {
        Storage::fake('s3');
        config(['vidlix.media.disk' => 's3']);

        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $project = Project::query()->create([
            'name' => 'Launch film',
            'status' => 'active',
            'total_amount_minor' => 100000,
            'advance_amount_minor' => 0,
            'revision_limit' => 2,
            'owner_user_id' => $owner->id,
            'counterparty_user_id' => $editor->id,
        ]);

        $file = UploadedFile::fake()->create('cut-01.mp4', 2048, 'video/mp4');
        $record = app(MarketplaceEngine::class)->storeProjectFile($project, $editor, $file, 'draft');

        Storage::disk('s3')->assertExists($record->storage_key);
        $this->assertSame('s3', $record->disk);
        $this->assertStringStartsWith('projects/'.$project->id.'/', $record->storage_key);

        // The row carries metadata and a key, never file contents.
        $columns = array_keys(ProjectFile::query()->find($record->id)->getAttributes());
        $this->assertNotContains('contents', $columns);
        $this->assertNotContains('data', $columns);
        $this->assertSame($file->getSize(), (int) $record->size_bytes);
    }
}

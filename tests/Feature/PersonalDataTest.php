<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Getting your data out, and closing the account without wiping the ledger.
 */
class PersonalDataTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'leaver@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('correct-horse-battery'),
        ]);
    }

    public function test_a_member_can_download_their_own_data(): void
    {
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('app.privacy.export'));

        $response->assertOk();
        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame('leaver@example.test', $payload['account']['email']);
        $this->assertArrayHasKey('ledger_entries', $payload);
    }

    public function test_closing_an_account_keeps_the_ledger_and_drops_the_identity(): void
    {
        $user = $this->member();

        $account = LedgerAccount::query()->create([
            'user_id' => $user->id,
            'kind' => 'earnings',
            'currency' => 'INR',
        ]);
        LedgerEntry::query()->create([
            'ledger_account_id' => $account->id,
            'entry_uuid' => (string) Str::uuid(),
            'state' => 'available',
            'amount_minor' => 500000,
            'currency' => 'INR',
        ]);

        $this->actingAs($user)->delete(route('app.privacy.destroy'), [
            'password' => 'correct-horse-battery',
            'confirm' => 'DELETE',
        ])->assertRedirect('/');

        // The money history has to survive: it is append-only and legally kept.
        $this->assertDatabaseCount('ledger_entries', 1);

        $user->refresh();
        $this->assertSame('Closed account', $user->name);
        $this->assertNotSame('leaver@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertGuest();
    }

    public function test_the_wrong_password_does_not_close_the_account(): void
    {
        $user = $this->member();

        $this->from(route('app.privacy'))
            ->actingAs($user)
            ->delete(route('app.privacy.destroy'), [
                'password' => 'not-my-password',
                'confirm' => 'DELETE',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame('leaver@example.test', $user->fresh()->email);
    }

    public function test_closing_requires_typing_delete(): void
    {
        $user = $this->member();

        $this->from(route('app.privacy'))
            ->actingAs($user)
            ->delete(route('app.privacy.destroy'), [
                'password' => 'correct-horse-battery',
                'confirm' => 'yes',
            ])
            ->assertSessionHasErrors('confirm');

        $this->assertSame('leaver@example.test', $user->fresh()->email);
    }

    public function test_the_legal_pages_carry_real_text_not_placeholders(): void
    {
        $this->seed();

        foreach (['terms', 'privacy', 'refund'] as $slug) {
            $page = CmsPage::query()->where('slug', $slug)->firstOrFail();

            $this->assertGreaterThan(600, strlen($page->body), "{$slug} is still a stub");
            $this->assertStringNotContainsString('Replace this CMS body', $page->body);
        }
    }
}

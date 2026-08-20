<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nudges for work that is waiting on somebody, sent once a day at most.
 */
class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    public function test_an_overdue_invoice_reminds_the_person_who_owes(): void
    {
        $buyer = $this->member('buyer@example.test');
        $seller = $this->member('seller@example.test');

        Invoice::query()->create([
            'invoice_number' => 'INV-1',
            'seller_user_id' => $seller->id,
            'buyer_user_id' => $buyer->id,
            'subtotal_minor' => 100000,
            'tax_minor' => 18000,
            'fee_minor' => 0,
            'total_minor' => 118000,
            'currency' => 'INR',
            'status' => 'issued',
            'due_date' => now()->subDays(2),
        ]);

        $this->artisan('vidlix:reminders')->assertSuccessful();

        $this->assertSame(1, $buyer->fresh()->notifications()->count());
        // The seller is not chased about their own invoice.
        $this->assertSame(0, $seller->fresh()->notifications()->count());
    }

    public function test_the_same_reminder_is_not_sent_twice_in_one_day(): void
    {
        $buyer = $this->member('buyer2@example.test');

        Invoice::query()->create([
            'invoice_number' => 'INV-2',
            'seller_user_id' => $this->member('seller2@example.test')->id,
            'buyer_user_id' => $buyer->id,
            'subtotal_minor' => 50000,
            'tax_minor' => 0,
            'fee_minor' => 0,
            'total_minor' => 50000,
            'currency' => 'INR',
            'status' => 'issued',
            'due_date' => now()->subDay(),
        ]);

        // The HTTP-triggered scheduler can fire more than once. One nudge must
        // not become several.
        $this->artisan('vidlix:reminders');
        $this->artisan('vidlix:reminders');

        $this->assertSame(1, $buyer->fresh()->notifications()->count());
    }

    public function test_an_invoice_that_is_not_yet_due_is_left_alone(): void
    {
        $buyer = $this->member('buyer3@example.test');

        Invoice::query()->create([
            'invoice_number' => 'INV-3',
            'seller_user_id' => $this->member('seller3@example.test')->id,
            'buyer_user_id' => $buyer->id,
            'subtotal_minor' => 50000,
            'tax_minor' => 0,
            'fee_minor' => 0,
            'total_minor' => 50000,
            'currency' => 'INR',
            'status' => 'issued',
            'due_date' => now()->addWeek(),
        ]);

        $this->artisan('vidlix:reminders');

        $this->assertSame(0, $buyer->fresh()->notifications()->count());
    }

    public function test_a_project_stalled_three_days_nudges_both_sides(): void
    {
        $owner = $this->member('owner@example.test');
        $other = $this->member('other@example.test');

        $project = Project::query()->create([
            'name' => 'Waiting project',
            'owner_user_id' => $owner->id,
            'counterparty_user_id' => $other->id,
            'status' => 'draft_submitted',
        ]);
        // Written through the query builder: an Eloquent update refreshes
        // updated_at again and the row would not look stale.
        Project::query()->whereKey($project->getKey())->update(['updated_at' => now()->subDays(5)]);

        $this->artisan('vidlix:reminders');

        $this->assertSame(1, $owner->fresh()->notifications()->count());
        $this->assertSame(1, $other->fresh()->notifications()->count());
    }

    public function test_a_project_that_moved_yesterday_is_not_chased(): void
    {
        $owner = $this->member('owner2@example.test');

        Project::query()->create([
            'name' => 'Fresh project',
            'owner_user_id' => $owner->id,
            'status' => 'draft_submitted',
        ]);

        $this->artisan('vidlix:reminders');

        $this->assertSame(0, $owner->fresh()->notifications()->count());
    }
}

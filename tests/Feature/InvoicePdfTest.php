<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $seller, User $buyer): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-20260820-ABCDE',
            'seller_user_id' => $seller->id,
            'buyer_user_id' => $buyer->id,
            'subtotal_minor' => 500000,
            'tax_minor' => 0,
            'fee_minor' => 50000,
            'total_minor' => 550000,
            'currency' => 'INR',
            'status' => 'issued',
            'due_date' => now()->addDays(14),
        ]);
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Three reels, June',
            'amount_minor' => 500000,
        ]);

        return $invoice;
    }

    public function test_a_party_to_the_invoice_can_download_it_as_a_pdf(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $invoice = $this->invoice($seller, $buyer);

        foreach ([$seller, $buyer] as $party) {
            $response = $this->actingAs($party)->get(route('app.invoices.pdf', $invoice));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_a_stranger_cannot_download_someone_elses_invoice(): void
    {
        $seller = User::factory()->create(['email_verified_at' => now()]);
        $buyer = User::factory()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('app.invoices.pdf', $this->invoice($seller, $buyer)))
            ->assertNotFound();
    }
}

<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders an invoice as a PDF.
 *
 * The file is generated on request rather than stored: an invoice's totals and
 * status can change until it is settled, and a cached PDF would quietly go
 * stale. `pdf_storage_key` stays for the day a signed, frozen copy is archived.
 */
class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['items', 'seller', 'buyer']);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'money' => fn (?int $minor) => number_format(((int) $minor) / 100, 2).' '.$invoice->currency,
        ])->output();
    }

    public function filename(Invoice $invoice): string
    {
        return $invoice->invoice_number.'.pdf';
    }
}

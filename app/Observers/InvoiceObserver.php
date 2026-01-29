<?php

namespace App\Observers;

use App\Models\Invoice;

/**
 * Observer for Invoice model to handle tenant assignment.
 * 
 * Invoices inherit tenant_id from their parent service.
 * 
 * Requirements: 18.3
 */
class InvoiceObserver
{
    /**
     * Handle the Invoice "creating" event.
     * 
     * Automatically assign tenant_id from the parent service.
     */
    public function creating(Invoice $invoice): void
    {
        // If tenant_id is not set, inherit from service
        if ($invoice->tenant_id === null && $invoice->service_id) {
            $service = $invoice->service;
            if ($service && $service->tenant_id) {
                $invoice->tenant_id = $service->tenant_id;
            }
        }
    }
}

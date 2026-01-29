<?php

namespace App\Observers;

use App\Models\Service;

/**
 * Observer for Service model to handle tenant assignment.
 * 
 * Services inherit tenant_id from their parent customer.
 * 
 * Requirements: 18.3
 */
class ServiceObserver
{
    /**
     * Handle the Service "creating" event.
     * 
     * Automatically assign tenant_id from the parent customer.
     */
    public function creating(Service $service): void
    {
        // If tenant_id is not set, inherit from customer
        if ($service->tenant_id === null && $service->customer_id) {
            $customer = $service->customer;
            if ($customer && $customer->tenant_id) {
                $service->tenant_id = $customer->tenant_id;
            }
        }
    }
}

<?php

namespace App\Observers;

use App\Models\Ticket;

/**
 * Observer for Ticket model to handle tenant assignment.
 * 
 * Tickets inherit tenant_id from their parent customer.
 * 
 * Requirements: 18.3
 */
class TicketObserver
{
    /**
     * Handle the Ticket "creating" event.
     * 
     * Automatically assign tenant_id from the parent customer.
     */
    public function creating(Ticket $ticket): void
    {
        // If tenant_id is not set, inherit from customer
        if ($ticket->tenant_id === null && $ticket->customer_id) {
            $customer = $ticket->customer;
            if ($customer && $customer->tenant_id) {
                $ticket->tenant_id = $customer->tenant_id;
            }
        }
    }
}

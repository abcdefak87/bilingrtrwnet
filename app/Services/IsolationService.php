<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IsolationService
{
    /**
     * The Mikrotik service instance.
     */
    protected MikrotikService $mikrotikService;

    /**
     * Grace period in days after due date before isolation.
     */
    protected int $gracePeriodDays;

    /**
     * Isolation profile name in Mikrotik.
     */
    protected string $isolationProfile;

    /**
     * Create a new IsolationService instance.
     */
    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotikService = $mikrotikService;
        $this->gracePeriodDays = config('billing.grace_period_days', 3);
        $this->isolationProfile = config('mikrotik.profiles.isolation', 'Isolir');
    }

    /**
     * Check for overdue services that need to be isolated.
     *
     * Returns a collection of services where:
     * - Invoice status is 'unpaid'
     * - current_date > due_date + grace_period
     * - Service status is 'active' (not already isolated)
     *
     * @return Collection Collection of services that need isolation
     */
    public function checkOverdueServices(): Collection
    {
        $today = Carbon::today();
        $cutoffDate = $today->copy()->subDays($this->gracePeriodDays);

        // Find all unpaid invoices where due_date + grace_period has passed
        $overdueInvoices = Invoice::where('status', 'unpaid')
            ->whereDate('due_date', '<', $cutoffDate)
            ->with(['service.mikrotikRouter', 'service.customer'])
            ->get();

        // Filter to only include services that are currently active
        $servicesToIsolate = collect();

        foreach ($overdueInvoices as $invoice) {
            $service = $invoice->service;

            // Only include active services that haven't been isolated yet
            if ($service && $service->status === 'active') {
                $servicesToIsolate->push($service);
            }
        }

        Log::info('Checked for overdue services', [
            'total_overdue_invoices' => $overdueInvoices->count(),
            'services_to_isolate' => $servicesToIsolate->count(),
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $servicesToIsolate;
    }

    /**
     * Isolate a service by changing its Mikrotik profile to isolation profile.
     *
     * This method:
     * 1. Calls Mikrotik API to change user profile to "Isolir"
     * 2. Updates service status to "isolated"
     * 3. Records isolation timestamp
     *
     * @param Service $service The service to isolate
     * @param Invoice $invoice The overdue invoice causing isolation
     * @return bool True if isolation successful, false otherwise
     */
    public function isolateService(Service $service, Invoice $invoice): bool
    {
        try {
            // Validate service has required data
            if (!$service->mikrotik_user_id || !$service->mikrotikRouter) {
                Log::error('Cannot isolate service: missing Mikrotik data', [
                    'service_id' => $service->id,
                    'has_mikrotik_user_id' => !empty($service->mikrotik_user_id),
                    'has_router' => !empty($service->mikrotikRouter),
                ]);
                return false;
            }

            // Call Mikrotik API to change profile to isolation profile
            $this->mikrotikService->updateUserProfile(
                $service->mikrotikRouter,
                $service->mikrotik_user_id,
                $this->isolationProfile
            );

            // Update service status and record isolation timestamp
            $service->update([
                'status' => 'isolated',
                'isolation_timestamp' => Carbon::now(),
            ]);

            Log::info('Service isolated successfully', [
                'service_id' => $service->id,
                'customer_id' => $service->customer_id,
                'invoice_id' => $invoice->id,
                'mikrotik_user_id' => $service->mikrotik_user_id,
                'isolation_profile' => $this->isolationProfile,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to isolate service', [
                'service_id' => $service->id,
                'customer_id' => $service->customer_id,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Restore a service by changing its Mikrotik profile back to original.
     *
     * This method:
     * 1. Calls Mikrotik API to restore original package profile
     * 2. Updates service status to "active"
     * 3. Clears isolation timestamp
     *
     * @param Service $service The service to restore
     * @return bool True if restoration successful, false otherwise
     */
    public function restoreService(Service $service): bool
    {
        try {
            // Validate service has required data
            if (!$service->mikrotik_user_id || !$service->mikrotikRouter || !$service->package) {
                Log::error('Cannot restore service: missing required data', [
                    'service_id' => $service->id,
                    'has_mikrotik_user_id' => !empty($service->mikrotik_user_id),
                    'has_router' => !empty($service->mikrotikRouter),
                    'has_package' => !empty($service->package),
                ]);
                return false;
            }

            // Get original profile name from package
            // Assuming profile name follows pattern: "Package-{package_name}" or stored in package
            $originalProfile = $this->getOriginalProfile($service);

            // Call Mikrotik API to restore original profile
            $this->mikrotikService->updateUserProfile(
                $service->mikrotikRouter,
                $service->mikrotik_user_id,
                $originalProfile
            );

            // Update service status and clear isolation timestamp
            $service->update([
                'status' => 'active',
                'isolation_timestamp' => null,
            ]);

            Log::info('Service restored successfully', [
                'service_id' => $service->id,
                'customer_id' => $service->customer_id,
                'mikrotik_user_id' => $service->mikrotik_user_id,
                'original_profile' => $originalProfile,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to restore service', [
                'service_id' => $service->id,
                'customer_id' => $service->customer_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get the original profile name for a service.
     *
     * This method determines the original Mikrotik profile name based on the package.
     * The profile name follows the pattern: "Package-{package_name}"
     *
     * @param Service $service The service
     * @return string The original profile name
     */
    protected function getOriginalProfile(Service $service): string
    {
        // If package has a specific profile name, use it
        if (!empty($service->package->mikrotik_profile)) {
            return $service->package->mikrotik_profile;
        }

        // Otherwise, construct profile name from package name
        $prefix = config('mikrotik.profiles.default_prefix', 'Package-');
        return $prefix . $service->package->name;
    }

    /**
     * Get isolation history for a service.
     *
     * Returns a collection of isolation events (when service was isolated and restored).
     * This is useful for tracking customer payment behavior.
     *
     * Note: This is a basic implementation. For full audit trail,
     * consider implementing an isolation_history table.
     *
     * @param Service $service The service
     * @return Collection Collection of isolation events
     */
    public function getIsolationHistory(Service $service): Collection
    {
        // For now, return basic information from the service
        // In a full implementation, this would query an isolation_history table
        $history = collect();

        if ($service->isolation_timestamp) {
            $history->push([
                'service_id' => $service->id,
                'status' => $service->status,
                'isolated_at' => $service->isolation_timestamp,
                'is_currently_isolated' => $service->status === 'isolated',
            ]);
        }

        return $history;
    }

    /**
     * Check if a service is currently isolated.
     *
     * @param Service $service The service
     * @return bool True if service is isolated
     */
    public function isIsolated(Service $service): bool
    {
        return $service->status === 'isolated';
    }

    /**
     * Check if a service can be isolated.
     *
     * A service can be isolated if:
     * - Status is 'active'
     * - Has Mikrotik user ID
     * - Has associated router
     *
     * @param Service $service The service
     * @return bool True if service can be isolated
     */
    public function canBeIsolated(Service $service): bool
    {
        return $service->status === 'active'
            && !empty($service->mikrotik_user_id)
            && !empty($service->mikrotikRouter);
    }
}

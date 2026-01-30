<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MikrotikRouter;
use App\Models\Package;
use App\Models\Service;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ServiceProvisioningService
{
    /**
     * Create a new ServiceProvisioningService instance.
     */
    public function __construct(
        protected MikrotikService $mikrotikService
    ) {
    }

    /**
     * Generate unique PPPoE credentials.
     *
     * @return array{username: string, password: string}
     */
    public function generateCredentials(): array
    {
        // Generate unique username with format: pppoe_YYYYMMDD_RANDOM
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        $username = "pppoe_{$date}_{$random}";

        // Ensure username is unique
        $attempt = 0;
        while (Service::where('username_pppoe', $username)->exists() && $attempt < 10) {
            $random = strtoupper(Str::random(6));
            $username = "pppoe_{$date}_{$random}";
            $attempt++;
        }

        if ($attempt >= 10) {
            throw new Exception('Failed to generate unique PPPoE username after 10 attempts');
        }

        // Generate secure random password (12 characters: letters, numbers, special chars)
        $password = $this->generateSecurePassword();

        Log::info('Generated PPPoE credentials', [
            'username' => $username,
        ]);

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Generate a secure random password.
     *
     * @param  int  $length
     * @return string
     */
    protected function generateSecurePassword(int $length = 12): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*';

        $all = $uppercase.$lowercase.$numbers.$special;

        // Ensure at least one character from each set
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Create a service record for a customer.
     *
     * @param  Customer  $customer
     * @param  Package  $package
     * @param  MikrotikRouter  $router
     * @return Service
     *
     * @throws Exception
     */
    public function createService(Customer $customer, Package $package, MikrotikRouter $router): Service
    {
        DB::beginTransaction();

        try {
            // Generate unique PPPoE credentials
            $credentials = $this->generateCredentials();

            // Create service record with status 'pending'
            $service = Service::create([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'mikrotik_id' => $router->id,
                'tenant_id' => $customer->tenant_id,
                'username_pppoe' => $credentials['username'],
                'password_encrypted' => $credentials['password'], // Will be encrypted by model mutator
                'status' => 'pending',
                'activation_date' => now(),
                'expiry_date' => now()->addDays(30), // Default 30 days billing cycle
            ]);

            Log::info('Service record created', [
                'service_id' => $service->id,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $credentials['username'],
            ]);

            DB::commit();

            return $service;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create service record', [
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Provision service to Mikrotik router.
     *
     * @param  Service  $service
     * @return bool
     */
    public function provisionToRouter(Service $service): bool
    {
        try {
            $router = $service->mikrotikRouter;
            $package = $service->package;

            // Get the decrypted password
            $password = $service->password_encrypted;

            // Determine the profile name based on package
            // In production, this should be configured or stored in the package
            $profile = $this->getProfileName($package);

            Log::info('Attempting to provision service to Mikrotik', [
                'service_id' => $service->id,
                'router_id' => $router->id,
                'router_name' => $router->name,
                'username' => $service->username_pppoe,
                'profile' => $profile,
            ]);

            // Create PPPoE user on Mikrotik
            $mikrotikUserId = $this->mikrotikService->createPPPoEUser(
                $router,
                $service->username_pppoe,
                $password,
                $profile
            );

            // Update service with Mikrotik user ID and set status to active
            $service->update([
                'mikrotik_user_id' => $mikrotikUserId,
                'status' => 'active',
            ]);

            Log::info('Service successfully provisioned to Mikrotik', [
                'service_id' => $service->id,
                'mikrotik_user_id' => $mikrotikUserId,
                'status' => 'active',
            ]);

            return true;
        } catch (Exception $e) {
            // Mark service as provisioning_failed
            $service->update([
                'status' => 'provisioning_failed',
            ]);

            Log::error('Failed to provision service to Mikrotik', [
                'service_id' => $service->id,
                'router_id' => $service->mikrotik_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get the Mikrotik profile name for a package.
     *
     * @param  Package  $package
     * @return string
     */
    protected function getProfileName(Package $package): string
    {
        // In production, this should be stored in the package or configuration
        // For now, we'll use a simple naming convention based on package name
        return str_replace(' ', '_', $package->name);
    }

    /**
     * Provision a service (create record and push to Mikrotik).
     *
     * @param  Customer  $customer
     * @param  Package  $package
     * @param  MikrotikRouter  $router
     * @return array{service: Service, success: bool, credentials: array}
     *
     * @throws Exception
     */
    public function provisionService(Customer $customer, Package $package, MikrotikRouter $router): array
    {
        // Create service record
        $service = $this->createService($customer, $package, $router);

        // Store credentials before provisioning (they'll be encrypted in DB)
        $credentials = [
            'username' => $service->username_pppoe,
            'password' => $service->password_encrypted, // This will be decrypted by accessor
        ];

        // Provision to Mikrotik
        $success = $this->provisionToRouter($service);

        return [
            'service' => $service->fresh(), // Reload to get updated status
            'success' => $success,
            'credentials' => $credentials,
        ];
    }

    /**
     * Isolate a service by changing its Mikrotik profile.
     *
     * @param  Service  $service
     * @return bool
     */
    public function isolateService(Service $service): bool
    {
        try {
            $router = $service->mikrotikRouter;

            if (! $service->mikrotik_user_id) {
                throw new Exception('Service does not have a Mikrotik user ID');
            }

            Log::info('Attempting to isolate service', [
                'service_id' => $service->id,
                'router_id' => $router->id,
                'mikrotik_user_id' => $service->mikrotik_user_id,
            ]);

            // Update user profile to "Isolir" (1kbps limit)
            $this->mikrotikService->updateUserProfile(
                $router,
                $service->mikrotik_user_id,
                'Isolir'
            );

            // Update service status
            $service->update([
                'status' => 'isolated',
            ]);

            Log::info('Service successfully isolated', [
                'service_id' => $service->id,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to isolate service', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Restore a service by changing its Mikrotik profile back to original.
     *
     * @param  Service  $service
     * @return bool
     */
    public function restoreService(Service $service): bool
    {
        try {
            $router = $service->mikrotikRouter;
            $package = $service->package;

            if (! $service->mikrotik_user_id) {
                throw new Exception('Service does not have a Mikrotik user ID');
            }

            Log::info('Attempting to restore service', [
                'service_id' => $service->id,
                'router_id' => $router->id,
                'mikrotik_user_id' => $service->mikrotik_user_id,
            ]);

            // Get the original profile name
            $profile = $this->getProfileName($package);

            // Update user profile back to original
            $this->mikrotikService->updateUserProfile(
                $router,
                $service->mikrotik_user_id,
                $profile
            );

            // Update service status
            $service->update([
                'status' => 'active',
            ]);

            Log::info('Service successfully restored', [
                'service_id' => $service->id,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to restore service', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Terminate a service by deleting it from Mikrotik.
     *
     * @param  Service  $service
     * @return bool
     */
    public function terminateService(Service $service): bool
    {
        DB::beginTransaction();

        try {
            $router = $service->mikrotikRouter;

            if ($service->mikrotik_user_id) {
                Log::info('Attempting to terminate service', [
                    'service_id' => $service->id,
                    'router_id' => $router->id,
                    'mikrotik_user_id' => $service->mikrotik_user_id,
                ]);

                // Delete user from Mikrotik
                $this->mikrotikService->deleteUser(
                    $router,
                    $service->mikrotik_user_id
                );
            }

            // Update service status
            $service->update([
                'status' => 'terminated',
            ]);

            DB::commit();

            Log::info('Service successfully terminated', [
                'service_id' => $service->id,
            ]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to terminate service', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

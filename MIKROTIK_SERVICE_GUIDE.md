# Mikrotik Service Guide

## Overview

The `MikrotikService` class provides a robust interface for interacting with Mikrotik routers via the RouterOS API. It includes advanced features like connection pooling, automatic retry mechanisms, and comprehensive error handling.

## Installation

The service has been installed and configured with the following components:

1. **RouterOS API Library**: `evilfreelancer/routeros-api-php` v1.6.0
2. **Service Class**: `App\Services\MikrotikService`
3. **Configuration File**: `config/mikrotik.php`
4. **Service Provider**: Registered as singleton in `AppServiceProvider`

## Configuration

All Mikrotik-related settings are stored in `config/mikrotik.php` and can be overridden via environment variables:

### Connection Settings

```env
# Connection timeout in seconds
MIKROTIK_TIMEOUT=5

# Number of connection attempts before failing
MIKROTIK_ATTEMPTS=3

# Delay between retry attempts in milliseconds
MIKROTIK_RETRY_DELAY=1000

# Enable connection pooling
MIKROTIK_POOLING_ENABLED=true

# Maximum number of connections to keep in pool per router
MIKROTIK_POOL_SIZE=3

# Connection idle timeout in seconds
MIKROTIK_POOL_IDLE_TIMEOUT=300
```

### Profile Settings

```env
# Profile name for isolated/suspended users
MIKROTIK_ISOLATION_PROFILE=Isolir

# Default profile prefix for regular users
MIKROTIK_DEFAULT_PROFILE_PREFIX=Package-
```

### Logging Settings

```env
# Enable detailed logging of API calls
MIKROTIK_LOGGING_ENABLED=true

# Log channel to use
MIKROTIK_LOG_CHANNEL=stack
```

## Features

### 1. Connection Pooling

The service implements connection pooling to improve performance and reduce connection overhead:

- **Automatic Pool Management**: Connections are automatically created, reused, and cleaned up
- **Per-Router Pools**: Each router has its own connection pool
- **Configurable Pool Size**: Control the maximum number of connections per router
- **Idle Timeout**: Automatically removes connections that have been idle too long
- **Thread-Safe**: Pool operations are designed to be safe in concurrent environments

### 2. Automatic Retry Mechanism

All operations include automatic retry with exponential backoff:

- Configurable number of retry attempts
- Configurable delay between retries
- Detailed logging of retry attempts
- Graceful failure after max attempts

### 3. Comprehensive Logging

All operations are logged with appropriate context:

- Connection attempts and failures
- API operations (create, update, delete)
- Pool statistics and management
- Error details with full context

## Usage

### Basic Usage

```php
use App\Services\MikrotikService;
use App\Models\MikrotikRouter;

// Get the service instance (singleton)
$mikrotikService = app(MikrotikService::class);

// Get a router
$router = MikrotikRouter::find(1);
```

### Test Connection

```php
// Test if the router is reachable
if ($mikrotikService->testConnection($router)) {
    echo "Connection successful!";
} else {
    echo "Connection failed!";
}
```

### Create PPPoE User

```php
try {
    $userId = $mikrotikService->createPPPoEUser(
        router: $router,
        username: 'customer123',
        password: 'securePassword123',
        profile: 'Package-10Mbps',
        additionalParams: [
            'comment' => 'Customer John Doe',
            'local-address' => '10.10.10.1',
            'remote-address' => '10.10.10.100'
        ]
    );
    
    echo "User created with ID: {$userId}";
} catch (Exception $e) {
    echo "Failed to create user: {$e->getMessage()}";
}
```

### Update User Profile (Isolation/Restoration)

```php
try {
    // Isolate user
    $mikrotikService->updateUserProfile(
        router: $router,
        userId: '*1A',
        newProfile: 'Isolir'
    );
    
    // Restore user
    $mikrotikService->updateUserProfile(
        router: $router,
        userId: '*1A',
        newProfile: 'Package-10Mbps'
    );
    
    echo "Profile updated successfully!";
} catch (Exception $e) {
    echo "Failed to update profile: {$e->getMessage()}";
}
```

### Delete User

```php
try {
    $mikrotikService->deleteUser(
        router: $router,
        userId: '*1A'
    );
    
    echo "User deleted successfully!";
} catch (Exception $e) {
    echo "Failed to delete user: {$e->getMessage()}";
}
```

### Get User Information

```php
try {
    $userInfo = $mikrotikService->getUserInfo(
        router: $router,
        username: 'customer123'
    );
    
    if ($userInfo) {
        print_r($userInfo);
    } else {
        echo "User not found";
    }
} catch (Exception $e) {
    echo "Failed to get user info: {$e->getMessage()}";
}
```

## Connection Pool Management

### Get Pool Statistics

```php
// Get stats for a specific router
$stats = $mikrotikService->getPoolStats($router);
echo "Total connections: {$stats['total']}\n";
echo "Available: {$stats['available']}\n";
echo "In use: {$stats['in_use']}\n";

// Get stats for all routers
$allStats = $mikrotikService->getPoolStats();
foreach ($allStats as $poolKey => $stats) {
    echo "{$poolKey}: {$stats['total']} total, {$stats['available']} available\n";
}
```

### Clear Connection Pool

```php
// Clear pool for a specific router
$mikrotikService->clearPool($router);

// Clear all pools
$mikrotikService->clearPool();
```

## Integration with Service Provisioning

Here's an example of how to integrate the MikrotikService with service provisioning:

```php
use App\Services\MikrotikService;
use App\Models\Service;
use App\Models\MikrotikRouter;
use Illuminate\Support\Facades\DB;

class ServiceProvisioningService
{
    public function __construct(
        protected MikrotikService $mikrotikService
    ) {}
    
    public function provisionService(Service $service): bool
    {
        DB::beginTransaction();
        
        try {
            $router = $service->mikrotikRouter;
            
            // Create PPPoE user on Mikrotik
            $mikrotikUserId = $this->mikrotikService->createPPPoEUser(
                router: $router,
                username: $service->username_pppoe,
                password: $service->password_encrypted,
                profile: "Package-{$service->package->name}",
                additionalParams: [
                    'comment' => "Customer: {$service->customer->name}",
                ]
            );
            
            // Store Mikrotik user ID for future reference
            $service->update([
                'mikrotik_user_id' => $mikrotikUserId,
                'status' => 'active'
            ]);
            
            DB::commit();
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            
            $service->update([
                'status' => 'provisioning_failed'
            ]);
            
            Log::error('Service provisioning failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    public function isolateService(Service $service): bool
    {
        try {
            $this->mikrotikService->updateUserProfile(
                router: $service->mikrotikRouter,
                userId: $service->mikrotik_user_id,
                newProfile: config('mikrotik.profiles.isolation')
            );
            
            $service->update([
                'status' => 'isolated',
                'isolated_at' => now()
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Service isolation failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    public function restoreService(Service $service): bool
    {
        try {
            $this->mikrotikService->updateUserProfile(
                router: $service->mikrotikRouter,
                userId: $service->mikrotik_user_id,
                newProfile: "Package-{$service->package->name}"
            );
            
            $service->update([
                'status' => 'active',
                'isolated_at' => null
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Log::error('Service restoration failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
```

## Error Handling

The service throws exceptions for all failures. Always wrap calls in try-catch blocks:

```php
try {
    $mikrotikService->createPPPoEUser($router, $username, $password, $profile);
} catch (Exception $e) {
    // Handle the error
    Log::error('Mikrotik operation failed', [
        'error' => $e->getMessage(),
        'router_id' => $router->id
    ]);
    
    // Notify admin or queue for retry
}
```

## Best Practices

1. **Always Use Dependency Injection**: Inject the service via constructor to leverage the singleton pattern
2. **Handle Exceptions**: Always wrap Mikrotik operations in try-catch blocks
3. **Use Transactions**: Wrap database updates and Mikrotik operations in database transactions
4. **Queue Heavy Operations**: Use Laravel queues for bulk operations
5. **Monitor Pool Stats**: Regularly check pool statistics to optimize pool size
6. **Test Connections**: Use `testConnection()` before performing critical operations
7. **Log Everything**: Enable logging in production for troubleshooting

## Troubleshooting

### Connection Failures

If connections are failing:

1. Check router IP address and port
2. Verify username and password
3. Ensure API service is enabled on Mikrotik
4. Check firewall rules
5. Review logs for detailed error messages

### Pool Issues

If experiencing pool-related issues:

1. Check pool statistics with `getPoolStats()`
2. Adjust `MIKROTIK_POOL_SIZE` if needed
3. Adjust `MIKROTIK_POOL_IDLE_TIMEOUT` if connections are timing out
4. Clear the pool with `clearPool()` to reset

### Performance Issues

If experiencing performance issues:

1. Enable connection pooling if disabled
2. Increase pool size for high-traffic routers
3. Reduce retry attempts for faster failures
4. Use queues for bulk operations

## Testing

The service includes comprehensive unit tests:

```bash
php artisan test --filter=MikrotikServiceTest
```

## Requirements Validation

This implementation satisfies **Requirement 10.1**:

> **Requirement 10.1**: System must connect to Mikrotik via RouterOS API on configured port

The service:
- ✅ Connects to Mikrotik routers via RouterOS API
- ✅ Uses configurable API port (default 8728)
- ✅ Implements connection pooling for efficiency
- ✅ Includes retry mechanism for reliability
- ✅ Provides comprehensive error handling
- ✅ Logs all operations for troubleshooting

## Next Steps

To complete the Mikrotik integration module (Task 6.2), you'll need to:

1. Implement the remaining Mikrotik operations (if any)
2. Create integration tests with mock Mikrotik responses
3. Write property-based tests for API parameter validation
4. Integrate with the service provisioning workflow
5. Create jobs for async Mikrotik operations

## Support

For issues or questions:
- Review the logs in `storage/logs/laravel.log`
- Check the Mikrotik RouterOS API documentation
- Review the `evilfreelancer/routeros-api-php` library documentation

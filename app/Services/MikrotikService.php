<?php

namespace App\Services;

use App\Models\MikrotikRouter;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\QueryException;
use RouterOS\Query;

class MikrotikService
{
    /**
     * Connection pool storage.
     *
     * @var array<string, array>
     */
    protected static array $connectionPool = [];

    /**
     * Maximum number of retry attempts.
     */
    protected int $maxAttempts;

    /**
     * Delay between retry attempts in milliseconds.
     */
    protected int $retryDelay;

    /**
     * Connection timeout in seconds.
     */
    protected int $timeout;

    /**
     * Whether connection pooling is enabled.
     */
    protected bool $poolingEnabled;

    /**
     * Maximum pool size per router.
     */
    protected int $poolSize;

    /**
     * Pool idle timeout in seconds.
     */
    protected int $poolIdleTimeout;

    /**
     * Create a new MikrotikService instance.
     */
    public function __construct()
    {
        $this->maxAttempts = config('mikrotik.connection.attempts', 3);
        $this->retryDelay = config('mikrotik.connection.retry_delay', 1000);
        $this->timeout = config('mikrotik.connection.timeout', 5);
        $this->poolingEnabled = config('mikrotik.connection.pooling_enabled', true);
        $this->poolSize = config('mikrotik.connection.pool_size', 3);
        $this->poolIdleTimeout = config('mikrotik.connection.pool_idle_timeout', 300);
    }

    /**
     * Get a connection to the Mikrotik router.
     *
     * @param  MikrotikRouter  $router
     * @return Client
     *
     * @throws Exception
     */
    protected function getConnection(MikrotikRouter $router): Client
    {
        if ($this->poolingEnabled) {
            return $this->getPooledConnection($router);
        }

        return $this->createConnection($router);
    }

    /**
     * Get a pooled connection or create a new one.
     *
     * @param  MikrotikRouter  $router
     * @return Client
     *
     * @throws Exception
     */
    protected function getPooledConnection(MikrotikRouter $router): Client
    {
        $poolKey = $this->getPoolKey($router);

        // Initialize pool for this router if it doesn't exist
        if (! isset(static::$connectionPool[$poolKey])) {
            static::$connectionPool[$poolKey] = [];
        }

        // Clean up expired connections
        $this->cleanupExpiredConnections($poolKey);

        // Try to get an available connection from the pool
        foreach (static::$connectionPool[$poolKey] as $index => $pooledConnection) {
            if ($pooledConnection['available']) {
                // Mark as in use
                static::$connectionPool[$poolKey][$index]['available'] = false;
                static::$connectionPool[$poolKey][$index]['last_used'] = time();

                $this->log('info', "Reusing pooled connection for router {$router->name}", [
                    'router_id' => $router->id,
                    'pool_key' => $poolKey,
                ]);

                return $pooledConnection['client'];
            }
        }

        // If pool is not full, create a new connection
        if (count(static::$connectionPool[$poolKey]) < $this->poolSize) {
            $client = $this->createConnection($router);

            static::$connectionPool[$poolKey][] = [
                'client' => $client,
                'available' => false,
                'created_at' => time(),
                'last_used' => time(),
            ];

            $this->log('info', "Created new pooled connection for router {$router->name}", [
                'router_id' => $router->id,
                'pool_key' => $poolKey,
                'pool_size' => count(static::$connectionPool[$poolKey]),
            ]);

            return $client;
        }

        // Pool is full, wait and retry or create a temporary connection
        $this->log('warning', "Connection pool full for router {$router->name}, creating temporary connection", [
            'router_id' => $router->id,
            'pool_key' => $poolKey,
        ]);

        return $this->createConnection($router);
    }

    /**
     * Release a connection back to the pool.
     *
     * @param  MikrotikRouter  $router
     * @param  Client  $client
     * @return void
     */
    public function releaseConnection(MikrotikRouter $router, Client $client): void
    {
        if (! $this->poolingEnabled) {
            return;
        }

        $poolKey = $this->getPoolKey($router);

        if (! isset(static::$connectionPool[$poolKey])) {
            return;
        }

        // Find the connection in the pool and mark it as available
        foreach (static::$connectionPool[$poolKey] as $index => $pooledConnection) {
            if ($pooledConnection['client'] === $client) {
                static::$connectionPool[$poolKey][$index]['available'] = true;
                static::$connectionPool[$poolKey][$index]['last_used'] = time();

                $this->log('debug', "Released connection back to pool for router {$router->name}", [
                    'router_id' => $router->id,
                    'pool_key' => $poolKey,
                ]);

                break;
            }
        }
    }

    /**
     * Create a new connection to the Mikrotik router.
     *
     * @param  MikrotikRouter  $router
     * @return Client
     *
     * @throws Exception
     */
    protected function createConnection(MikrotikRouter $router): Client
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            try {
                $config = new Config([
                    'host' => $router->ip_address,
                    'user' => $router->username,
                    'pass' => $router->password_encrypted, // Will be decrypted by the model accessor
                    'port' => $router->api_port ?? 8728,
                    'timeout' => $this->timeout,
                ]);

                $client = new Client($config);

                $this->log('info', "Successfully connected to router {$router->name}", [
                    'router_id' => $router->id,
                    'ip_address' => $router->ip_address,
                    'attempt' => $attempt + 1,
                ]);

                return $client;
            } catch (ClientException | ConfigException $e) {
                $lastException = $e;
                $attempt++;

                $this->log('warning', "Connection attempt {$attempt} failed for router {$router->name}", [
                    'router_id' => $router->id,
                    'ip_address' => $router->ip_address,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->maxAttempts) {
                    usleep($this->retryDelay * 1000); // Convert to microseconds
                }
            }
        }

        $this->log('error', "Failed to connect to router {$router->name} after {$this->maxAttempts} attempts", [
            'router_id' => $router->id,
            'ip_address' => $router->ip_address,
            'error' => $lastException?->getMessage(),
        ]);

        throw new Exception(
            "Failed to connect to Mikrotik router {$router->name} after {$this->maxAttempts} attempts: "
            .($lastException?->getMessage() ?? 'Unknown error')
        );
    }

    /**
     * Test connection to a Mikrotik router.
     *
     * @param  MikrotikRouter  $router
     * @return bool
     */
    public function testConnection(MikrotikRouter $router): bool
    {
        try {
            $client = $this->getConnection($router);

            // Try to execute a simple query to verify connection
            $query = new Query('/system/identity/print');
            $client->query($query)->read();

            $this->releaseConnection($router, $client);

            $this->log('info', "Connection test successful for router {$router->name}", [
                'router_id' => $router->id,
            ]);

            return true;
        } catch (Exception $e) {
            $this->log('error', "Connection test failed for router {$router->name}", [
                'router_id' => $router->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a PPPoE user on the Mikrotik router.
     *
     * @param  MikrotikRouter  $router
     * @param  string  $username
     * @param  string  $password
     * @param  string  $profile
     * @param  array  $additionalParams
     * @return string Mikrotik user ID
     *
     * @throws Exception
     */
    public function createPPPoEUser(
        MikrotikRouter $router,
        string $username,
        string $password,
        string $profile,
        array $additionalParams = []
    ): string {
        try {
            $client = $this->getConnection($router);

            $query = (new Query('/ppp/secret/add'))
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('profile', $profile)
                ->equal('service', 'pppoe');

            // Add any additional parameters
            foreach ($additionalParams as $key => $value) {
                $query->equal($key, $value);
            }

            $response = $client->query($query)->read();

            $this->releaseConnection($router, $client);

            $userId = $response['after']['ret'] ?? null;

            if (! $userId) {
                throw new Exception('Failed to get user ID from Mikrotik response');
            }

            $this->log('info', "Created PPPoE user {$username} on router {$router->name}", [
                'router_id' => $router->id,
                'username' => $username,
                'profile' => $profile,
                'user_id' => $userId,
            ]);

            return $userId;
        } catch (QueryException | ClientException $e) {
            $this->log('error', "Failed to create PPPoE user {$username} on router {$router->name}", [
                'router_id' => $router->id,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to create PPPoE user: {$e->getMessage()}");
        }
    }

    /**
     * Update a PPPoE user's profile (used for isolation/restoration).
     *
     * @param  MikrotikRouter  $router
     * @param  string  $userId
     * @param  string  $newProfile
     * @return bool
     *
     * @throws Exception
     */
    public function updateUserProfile(MikrotikRouter $router, string $userId, string $newProfile): bool
    {
        try {
            $client = $this->getConnection($router);

            $query = (new Query('/ppp/secret/set'))
                ->equal('.id', $userId)
                ->equal('profile', $newProfile);

            $client->query($query)->read();

            $this->releaseConnection($router, $client);

            $this->log('info', "Updated user profile to {$newProfile} on router {$router->name}", [
                'router_id' => $router->id,
                'user_id' => $userId,
                'new_profile' => $newProfile,
            ]);

            return true;
        } catch (QueryException | ClientException $e) {
            $this->log('error', "Failed to update user profile on router {$router->name}", [
                'router_id' => $router->id,
                'user_id' => $userId,
                'new_profile' => $newProfile,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to update user profile: {$e->getMessage()}");
        }
    }

    /**
     * Delete a PPPoE user from the Mikrotik router.
     *
     * @param  MikrotikRouter  $router
     * @param  string  $userId
     * @return bool
     *
     * @throws Exception
     */
    public function deleteUser(MikrotikRouter $router, string $userId): bool
    {
        try {
            $client = $this->getConnection($router);

            $query = (new Query('/ppp/secret/remove'))
                ->equal('.id', $userId);

            $client->query($query)->read();

            $this->releaseConnection($router, $client);

            $this->log('info', "Deleted user from router {$router->name}", [
                'router_id' => $router->id,
                'user_id' => $userId,
            ]);

            return true;
        } catch (QueryException | ClientException $e) {
            $this->log('error', "Failed to delete user from router {$router->name}", [
                'router_id' => $router->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to delete user: {$e->getMessage()}");
        }
    }

    /**
     * Get information about a PPPoE user.
     *
     * @param  MikrotikRouter  $router
     * @param  string  $username
     * @return array|null
     *
     * @throws Exception
     */
    public function getUserInfo(MikrotikRouter $router, string $username): ?array
    {
        try {
            $client = $this->getConnection($router);

            $query = (new Query('/ppp/secret/print'))
                ->where('name', $username);

            $response = $client->query($query)->read();

            $this->releaseConnection($router, $client);

            if (empty($response)) {
                return null;
            }

            $this->log('debug', "Retrieved user info for {$username} from router {$router->name}", [
                'router_id' => $router->id,
                'username' => $username,
            ]);

            return $response[0] ?? null;
        } catch (QueryException | ClientException $e) {
            $this->log('error', "Failed to get user info from router {$router->name}", [
                'router_id' => $router->id,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Failed to get user info: {$e->getMessage()}");
        }
    }

    /**
     * Get the pool key for a router.
     *
     * @param  MikrotikRouter  $router
     * @return string
     */
    protected function getPoolKey(MikrotikRouter $router): string
    {
        return "router_{$router->id}_{$router->ip_address}";
    }

    /**
     * Clean up expired connections from the pool.
     *
     * @param  string  $poolKey
     * @return void
     */
    protected function cleanupExpiredConnections(string $poolKey): void
    {
        if (! isset(static::$connectionPool[$poolKey])) {
            return;
        }

        $now = time();
        $removed = 0;

        foreach (static::$connectionPool[$poolKey] as $index => $pooledConnection) {
            // Remove connections that have been idle for too long
            if (
                $pooledConnection['available'] &&
                ($now - $pooledConnection['last_used']) > $this->poolIdleTimeout
            ) {
                unset(static::$connectionPool[$poolKey][$index]);
                $removed++;
            }
        }

        // Re-index the array
        if ($removed > 0) {
            static::$connectionPool[$poolKey] = array_values(static::$connectionPool[$poolKey]);

            $this->log('debug', "Cleaned up {$removed} expired connections from pool", [
                'pool_key' => $poolKey,
                'removed' => $removed,
            ]);
        }
    }

    /**
     * Clear all connections from the pool.
     *
     * @param  MikrotikRouter|null  $router
     * @return void
     */
    public function clearPool(?MikrotikRouter $router = null): void
    {
        if ($router) {
            $poolKey = $this->getPoolKey($router);
            unset(static::$connectionPool[$poolKey]);

            $this->log('info', "Cleared connection pool for router {$router->name}", [
                'router_id' => $router->id,
                'pool_key' => $poolKey,
            ]);
        } else {
            static::$connectionPool = [];

            $this->log('info', 'Cleared all connection pools');
        }
    }

    /**
     * Get pool statistics.
     *
     * @param  MikrotikRouter|null  $router
     * @return array
     */
    public function getPoolStats(?MikrotikRouter $router = null): array
    {
        if ($router) {
            $poolKey = $this->getPoolKey($router);

            if (! isset(static::$connectionPool[$poolKey])) {
                return [
                    'total' => 0,
                    'available' => 0,
                    'in_use' => 0,
                ];
            }

            $pool = static::$connectionPool[$poolKey];
            $available = count(array_filter($pool, fn ($conn) => $conn['available']));

            return [
                'total' => count($pool),
                'available' => $available,
                'in_use' => count($pool) - $available,
            ];
        }

        // Return stats for all pools
        $stats = [];
        foreach (static::$connectionPool as $poolKey => $pool) {
            $available = count(array_filter($pool, fn ($conn) => $conn['available']));
            $stats[$poolKey] = [
                'total' => count($pool),
                'available' => $available,
                'in_use' => count($pool) - $available,
            ];
        }

        return $stats;
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param  string  $level
     * @param  string  $message
     * @param  array  $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (! config('mikrotik.logging.enabled', true)) {
            return;
        }

        $channel = config('mikrotik.logging.channel', 'stack');

        Log::channel($channel)->$level($message, $context);
    }
}

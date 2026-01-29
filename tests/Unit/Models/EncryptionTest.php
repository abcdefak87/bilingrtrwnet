<?php

namespace Tests\Unit\Models;

use App\Models\MikrotikRouter;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncryptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function service_password_is_encrypted_when_set(): void
    {
        $plainPassword = 'test_password_123';
        
        $service = new Service();
        $service->password_encrypted = $plainPassword;
        
        // The encrypted value should be different from the plain password
        $this->assertNotEquals($plainPassword, $service->getAttributes()['password_encrypted']);
        
        // But when accessed via the accessor, it should return the plain password
        $this->assertEquals($plainPassword, $service->password_encrypted);
    }

    /** @test */
    public function mikrotik_router_password_is_encrypted_when_set(): void
    {
        $plainPassword = 'router_password_456';
        
        $router = new MikrotikRouter();
        $router->password_encrypted = $plainPassword;
        
        // The encrypted value should be different from the plain password
        $this->assertNotEquals($plainPassword, $router->getAttributes()['password_encrypted']);
        
        // But when accessed via the accessor, it should return the plain password
        $this->assertEquals($plainPassword, $router->password_encrypted);
    }

    /** @test */
    public function service_password_is_hidden_in_array(): void
    {
        $service = Service::make([
            'username_pppoe' => 'test_user',
            'password_encrypted' => 'secret_password',
        ]);
        
        $array = $service->toArray();
        
        $this->assertArrayNotHasKey('password_encrypted', $array);
    }

    /** @test */
    public function mikrotik_router_password_is_hidden_in_array(): void
    {
        $router = MikrotikRouter::make([
            'name' => 'Test Router',
            'ip_address' => '192.168.1.1',
            'username' => 'admin',
            'password_encrypted' => 'secret_password',
        ]);
        
        $array = $router->toArray();
        
        $this->assertArrayNotHasKey('password_encrypted', $array);
    }

    /** @test */
    public function null_password_is_handled_correctly_for_service(): void
    {
        $service = new Service();
        $service->password_encrypted = null;
        
        $this->assertNull($service->password_encrypted);
    }

    /** @test */
    public function null_password_is_handled_correctly_for_mikrotik_router(): void
    {
        $router = new MikrotikRouter();
        $router->password_encrypted = null;
        
        $this->assertNull($router->password_encrypted);
    }
}

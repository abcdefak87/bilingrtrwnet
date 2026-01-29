<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DeviceMonitoring;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Ticket;
use Tests\TestCase;

class ModelAttributesTest extends TestCase
{
    /** @test */
    public function customer_has_correct_fillable_attributes(): void
    {
        $customer = new Customer();
        $fillable = $customer->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('phone', $fillable);
        $this->assertContains('address', $fillable);
        $this->assertContains('ktp_number', $fillable);
        $this->assertContains('ktp_path', $fillable);
        $this->assertContains('latitude', $fillable);
        $this->assertContains('longitude', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('tenant_id', $fillable);
    }

    /** @test */
    public function service_has_correct_fillable_attributes(): void
    {
        $service = new Service();
        $fillable = $service->getFillable();
        
        $this->assertContains('customer_id', $fillable);
        $this->assertContains('package_id', $fillable);
        $this->assertContains('mikrotik_id', $fillable);
        $this->assertContains('username_pppoe', $fillable);
        $this->assertContains('password_encrypted', $fillable);
        $this->assertContains('ip_address', $fillable);
        $this->assertContains('mikrotik_user_id', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('activation_date', $fillable);
        $this->assertContains('expiry_date', $fillable);
    }

    /** @test */
    public function invoice_has_correct_casts(): void
    {
        $invoice = new Invoice();
        $casts = $invoice->getCasts();
        
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('date', $casts['invoice_date']);
        $this->assertEquals('date', $casts['due_date']);
        $this->assertEquals('datetime', $casts['paid_at']);
    }

    /** @test */
    public function payment_has_correct_casts(): void
    {
        $payment = new Payment();
        $casts = $payment->getCasts();
        
        $this->assertEquals('decimal:2', $casts['amount']);
        $this->assertEquals('array', $casts['metadata']);
    }

    /** @test */
    public function package_has_correct_casts(): void
    {
        $package = new Package();
        $casts = $package->getCasts();
        
        $this->assertEquals('decimal:2', $casts['price']);
        $this->assertEquals('integer', $casts['fup_threshold']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    /** @test */
    public function mikrotik_router_has_correct_casts(): void
    {
        $router = new MikrotikRouter();
        $casts = $router->getCasts();
        
        $this->assertEquals('integer', $casts['api_port']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    /** @test */
    public function device_monitoring_has_correct_casts(): void
    {
        $monitoring = new DeviceMonitoring();
        $casts = $monitoring->getCasts();
        
        $this->assertEquals('float', $casts['cpu_usage']);
        $this->assertEquals('float', $casts['temperature']);
        $this->assertEquals('integer', $casts['uptime']);
        $this->assertEquals('integer', $casts['traffic_in']);
        $this->assertEquals('integer', $casts['traffic_out']);
        $this->assertEquals('datetime', $casts['recorded_at']);
    }

    /** @test */
    public function audit_log_has_correct_casts(): void
    {
        $audit = new AuditLog();
        $casts = $audit->getCasts();
        
        $this->assertEquals('array', $casts['old_values']);
        $this->assertEquals('array', $casts['new_values']);
    }

    /** @test */
    public function service_password_is_hidden(): void
    {
        $service = new Service();
        $hidden = $service->getHidden();
        
        $this->assertContains('password_encrypted', $hidden);
    }

    /** @test */
    public function mikrotik_router_password_is_hidden(): void
    {
        $router = new MikrotikRouter();
        $hidden = $router->getHidden();
        
        $this->assertContains('password_encrypted', $hidden);
    }

    /** @test */
    public function customer_has_correct_decimal_casts(): void
    {
        $customer = new Customer();
        $casts = $customer->getCasts();
        
        $this->assertEquals('decimal:8', $casts['latitude']);
        $this->assertEquals('decimal:8', $casts['longitude']);
    }

    /** @test */
    public function odp_has_correct_decimal_casts(): void
    {
        $odp = new Odp();
        $casts = $odp->getCasts();
        
        $this->assertEquals('decimal:8', $casts['latitude']);
        $this->assertEquals('decimal:8', $casts['longitude']);
    }

    /** @test */
    public function odp_port_has_correct_casts(): void
    {
        $odpPort = new OdpPort();
        $casts = $odpPort->getCasts();
        
        $this->assertEquals('integer', $casts['port_number']);
    }

    /** @test */
    public function service_has_correct_date_casts(): void
    {
        $service = new Service();
        $casts = $service->getCasts();
        
        $this->assertEquals('date', $casts['activation_date']);
        $this->assertEquals('date', $casts['expiry_date']);
    }
}

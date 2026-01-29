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
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    /** @test */
    public function customer_has_services_relationship(): void
    {
        $customer = new Customer();
        $this->assertInstanceOf(HasMany::class, $customer->services());
    }

    /** @test */
    public function customer_has_tickets_relationship(): void
    {
        $customer = new Customer();
        $this->assertInstanceOf(HasMany::class, $customer->tickets());
    }

    /** @test */
    public function service_has_customer_relationship(): void
    {
        $service = new Service();
        $this->assertInstanceOf(BelongsTo::class, $service->customer());
    }

    /** @test */
    public function service_has_package_relationship(): void
    {
        $service = new Service();
        $this->assertInstanceOf(BelongsTo::class, $service->package());
    }

    /** @test */
    public function service_has_mikrotik_router_relationship(): void
    {
        $service = new Service();
        $this->assertInstanceOf(BelongsTo::class, $service->mikrotikRouter());
    }

    /** @test */
    public function service_has_invoices_relationship(): void
    {
        $service = new Service();
        $this->assertInstanceOf(HasMany::class, $service->invoices());
    }

    /** @test */
    public function service_has_odp_port_relationship(): void
    {
        $service = new Service();
        $this->assertInstanceOf(HasOne::class, $service->odpPort());
    }

    /** @test */
    public function invoice_has_service_relationship(): void
    {
        $invoice = new Invoice();
        $this->assertInstanceOf(BelongsTo::class, $invoice->service());
    }

    /** @test */
    public function invoice_has_payments_relationship(): void
    {
        $invoice = new Invoice();
        $this->assertInstanceOf(HasMany::class, $invoice->payments());
    }

    /** @test */
    public function payment_has_invoice_relationship(): void
    {
        $payment = new Payment();
        $this->assertInstanceOf(BelongsTo::class, $payment->invoice());
    }

    /** @test */
    public function package_has_services_relationship(): void
    {
        $package = new Package();
        $this->assertInstanceOf(HasMany::class, $package->services());
    }

    /** @test */
    public function mikrotik_router_has_services_relationship(): void
    {
        $router = new MikrotikRouter();
        $this->assertInstanceOf(HasMany::class, $router->services());
    }

    /** @test */
    public function mikrotik_router_has_device_monitoring_relationship(): void
    {
        $router = new MikrotikRouter();
        $this->assertInstanceOf(HasMany::class, $router->deviceMonitoring());
    }

    /** @test */
    public function ticket_has_customer_relationship(): void
    {
        $ticket = new Ticket();
        $this->assertInstanceOf(BelongsTo::class, $ticket->customer());
    }

    /** @test */
    public function ticket_has_assigned_to_relationship(): void
    {
        $ticket = new Ticket();
        $this->assertInstanceOf(BelongsTo::class, $ticket->assignedTo());
    }

    /** @test */
    public function odp_has_ports_relationship(): void
    {
        $odp = new Odp();
        $this->assertInstanceOf(HasMany::class, $odp->ports());
    }

    /** @test */
    public function odp_port_has_odp_relationship(): void
    {
        $odpPort = new OdpPort();
        $this->assertInstanceOf(BelongsTo::class, $odpPort->odp());
    }

    /** @test */
    public function odp_port_has_service_relationship(): void
    {
        $odpPort = new OdpPort();
        $this->assertInstanceOf(BelongsTo::class, $odpPort->service());
    }

    /** @test */
    public function device_monitoring_has_router_relationship(): void
    {
        $monitoring = new DeviceMonitoring();
        $this->assertInstanceOf(BelongsTo::class, $monitoring->router());
    }

    /** @test */
    public function audit_log_has_user_relationship(): void
    {
        $audit = new AuditLog();
        $this->assertInstanceOf(BelongsTo::class, $audit->user());
    }

    /** @test */
    public function user_has_assigned_tickets_relationship(): void
    {
        $user = new User();
        $this->assertInstanceOf(HasMany::class, $user->assignedTickets());
    }

    /** @test */
    public function user_has_audit_logs_relationship(): void
    {
        $user = new User();
        $this->assertInstanceOf(HasMany::class, $user->auditLogs());
    }
}

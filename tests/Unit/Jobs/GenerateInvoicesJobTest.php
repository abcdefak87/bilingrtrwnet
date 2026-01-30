<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateInvoicesJob;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Service;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateInvoicesJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        // Arrange
        Queue::fake();

        // Act
        GenerateInvoicesJob::dispatch();

        // Assert
        Queue::assertPushed(GenerateInvoicesJob::class);
    }

    /** @test */
    public function it_generates_invoices_for_due_services()
    {
        // Arrange
        $package = Package::factory()->create(['price' => 100000]);
        $today = Carbon::today();
        
        // Create active services that are due for billing
        $service1 = Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'active',
            'expiry_date' => $today,
        ]);
        
        $service2 = Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'active',
            'expiry_date' => $today->copy()->subDay(),
        ]);

        // Act
        $job = new GenerateInvoicesJob();
        $job->handle(new BillingService());

        // Assert
        $this->assertDatabaseHas('invoices', [
            'service_id' => $service1->id,
            'status' => 'unpaid',
        ]);
        
        $this->assertDatabaseHas('invoices', [
            'service_id' => $service2->id,
            'status' => 'unpaid',
        ]);
        
        $this->assertEquals(2, Invoice::count());
    }

    /** @test */
    public function it_does_not_generate_invoices_for_non_active_services()
    {
        // Arrange
        $package = Package::factory()->create();
        $today = Carbon::today();
        
        // Create services with different statuses
        Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'isolated',
            'expiry_date' => $today,
        ]);
        
        Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'suspended',
            'expiry_date' => $today,
        ]);
        
        Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'terminated',
            'expiry_date' => $today,
        ]);

        // Act
        $job = new GenerateInvoicesJob();
        $job->handle(new BillingService());

        // Assert
        $this->assertEquals(0, Invoice::count());
    }

    /** @test */
    public function it_does_not_generate_invoices_for_services_not_yet_due()
    {
        // Arrange
        $package = Package::factory()->create();
        $tomorrow = Carbon::tomorrow();
        
        // Create active service that is not yet due
        Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'active',
            'expiry_date' => $tomorrow,
        ]);

        // Act
        $job = new GenerateInvoicesJob();
        $job->handle(new BillingService());

        // Assert
        $this->assertEquals(0, Invoice::count());
    }

    /** @test */
    public function it_logs_successful_execution()
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with('GenerateInvoicesJob started');
        
        Log::shouldReceive('info')
            ->once()
            ->with('GenerateInvoicesJob completed', \Mockery::type('array'));

        $package = Package::factory()->create();
        Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'active',
            'expiry_date' => Carbon::today(),
        ]);

        // Act
        $job = new GenerateInvoicesJob();
        $job->handle(new BillingService());

        // Assert - expectations verified by Mockery
    }

    /** @test */
    public function it_logs_error_when_execution_fails()
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with('GenerateInvoicesJob started');
        
        Log::shouldReceive('error')
            ->once()
            ->with('GenerateInvoicesJob failed', \Mockery::type('array'));

        // Mock BillingService to throw exception
        $billingService = \Mockery::mock(BillingService::class);
        $billingService->shouldReceive('generateInvoicesForDueServices')
            ->once()
            ->andThrow(new \Exception('Test error'));

        // Act & Assert
        $job = new GenerateInvoicesJob();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test error');
        
        $job->handle($billingService);
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        // Arrange
        $job = new GenerateInvoicesJob();

        // Assert
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(60, $job->backoff);
    }

    /** @test */
    public function it_logs_critical_error_when_all_retries_fail()
    {
        // Arrange
        Log::shouldReceive('critical')
            ->once()
            ->with('GenerateInvoicesJob failed after all retries', \Mockery::type('array'));

        $job = new GenerateInvoicesJob();
        $exception = new \Exception('All retries failed');

        // Act
        $job->failed($exception);

        // Assert - expectations verified by Mockery
    }

    /** @test */
    public function it_generates_invoices_with_correct_amounts()
    {
        // Arrange
        $package1 = Package::factory()->create(['price' => 100000]);
        $package2 = Package::factory()->create(['price' => 200000]);
        $today = Carbon::today();
        
        $service1 = Service::factory()->create([
            'package_id' => $package1->id,
            'status' => 'active',
            'expiry_date' => $today,
        ]);
        
        $service2 = Service::factory()->create([
            'package_id' => $package2->id,
            'status' => 'active',
            'expiry_date' => $today,
        ]);

        // Act
        $job = new GenerateInvoicesJob();
        $job->handle(new BillingService());

        // Assert
        $invoice1 = Invoice::where('service_id', $service1->id)->first();
        $invoice2 = Invoice::where('service_id', $service2->id)->first();
        
        $this->assertEquals(100000, $invoice1->amount);
        $this->assertEquals(200000, $invoice2->amount);
    }

    /** @test */
    public function it_sets_correct_due_dates_for_generated_invoices()
    {
        // Arrange
        config(['billing.cycle_days' => 30]);
        $package = Package::factory()->create();
        $today = Carbon::today();
        $expectedDueDate = $today->copy()->addDays(30);
        
        $service = Service::factory()->create([
            'package_id' => $package->id,
            'status' => 'active',
            'expiry_date' => $today,
        ]);

        // Act
        $job = new GenerateInvoicesJob();
        $job->handle(new BillingService());

        // Assert
        $invoice = Invoice::where('service_id', $service->id)->first();
        $this->assertTrue($invoice->due_date->isSameDay($expectedDueDate));
    }
}

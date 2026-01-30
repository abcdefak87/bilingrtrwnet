<?php

namespace Tests\Unit;

use Tests\TestCase;

class PaymentGatewayLibrariesTest extends TestCase
{
    /**
     * Test that Midtrans library is installed and can be loaded.
     */
    public function test_midtrans_library_is_installed(): void
    {
        $this->assertTrue(
            class_exists(\Midtrans\Config::class),
            'Midtrans library is not installed or cannot be loaded'
        );
        
        $this->assertTrue(
            class_exists(\Midtrans\Snap::class),
            'Midtrans Snap class is not available'
        );
    }

    /**
     * Test that Xendit library is installed and can be loaded.
     */
    public function test_xendit_library_is_installed(): void
    {
        $this->assertTrue(
            class_exists(\Xendit\Configuration::class),
            'Xendit library is not installed or cannot be loaded'
        );
    }

    /**
     * Test that Guzzle HTTP client is available for Tripay.
     */
    public function test_guzzle_http_client_is_available(): void
    {
        $this->assertTrue(
            class_exists(\GuzzleHttp\Client::class),
            'Guzzle HTTP client is not installed or cannot be loaded'
        );
    }

    /**
     * Test that payment gateway configuration file exists.
     */
    public function test_payment_gateway_config_exists(): void
    {
        $config = config('payment-gateways');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('midtrans', $config);
        $this->assertArrayHasKey('xendit', $config);
        $this->assertArrayHasKey('tripay', $config);
    }

    /**
     * Test that Midtrans configuration has required keys.
     */
    public function test_midtrans_config_has_required_keys(): void
    {
        $config = config('payment-gateways.midtrans');
        
        $this->assertArrayHasKey('server_key', $config);
        $this->assertArrayHasKey('client_key', $config);
        $this->assertArrayHasKey('merchant_id', $config);
        $this->assertArrayHasKey('is_production', $config);
    }

    /**
     * Test that Xendit configuration has required keys.
     */
    public function test_xendit_config_has_required_keys(): void
    {
        $config = config('payment-gateways.xendit');
        
        $this->assertArrayHasKey('secret_key', $config);
        $this->assertArrayHasKey('public_key', $config);
        $this->assertArrayHasKey('webhook_token', $config);
        $this->assertArrayHasKey('is_production', $config);
    }

    /**
     * Test that Tripay configuration has required keys.
     */
    public function test_tripay_config_has_required_keys(): void
    {
        $config = config('payment-gateways.tripay');
        
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('private_key', $config);
        $this->assertArrayHasKey('merchant_code', $config);
        $this->assertArrayHasKey('is_production', $config);
        $this->assertArrayHasKey('base_url', $config);
    }

    /**
     * Test that webhook URLs are configured.
     */
    public function test_webhook_urls_are_configured(): void
    {
        $config = config('payment-gateways.webhook');
        
        $this->assertArrayHasKey('midtrans_url', $config);
        $this->assertArrayHasKey('xendit_url', $config);
        $this->assertArrayHasKey('tripay_url', $config);
        
        $this->assertEquals('/api/webhooks/midtrans', $config['midtrans_url']);
        $this->assertEquals('/api/webhooks/xendit', $config['xendit_url']);
        $this->assertEquals('/api/webhooks/tripay', $config['tripay_url']);
    }
}

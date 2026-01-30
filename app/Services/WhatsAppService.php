<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WhatsAppService
{
    protected Client $client;
    protected string $gateway;
    protected array $config;

    public function __construct()
    {
        $this->gateway = config('whatsapp.gateway');
        $this->config = config("whatsapp.{$this->gateway}");
        
        $this->client = new Client([
            'base_uri' => $this->config['base_url'],
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Kirim pesan WhatsApp ke nomor tujuan
     *
     * @param string $phone Nomor telepon tujuan (format: 628xxx)
     * @param string $message Isi pesan
     * @return bool
     */
    public function sendMessage(string $phone, string $message): bool
    {
        // Validasi nomor telepon
        $phone = $this->normalizePhoneNumber($phone);
        
        if (!$this->validatePhoneNumber($phone)) {
            Log::warning('Invalid phone number format', ['phone' => $phone]);
            return false;
        }

        // Check rate limiting
        if (!$this->checkRateLimit()) {
            Log::warning('WhatsApp rate limit exceeded');
            return false;
        }

        // Kirim pesan dengan retry mechanism
        return $this->sendWithRetry($phone, $message);
    }

    /**
     * Kirim pesan dengan retry mechanism
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    protected function sendWithRetry(string $phone, string $message): bool
    {
        $maxAttempts = config('whatsapp.retry.max_attempts');
        $delay = config('whatsapp.retry.delay');
        $multiplier = config('whatsapp.retry.multiplier');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $this->send($phone, $message);
                
                if ($result) {
                    Log::info('WhatsApp message sent successfully', [
                        'phone' => $phone,
                        'attempt' => $attempt,
                    ]);
                    
                    // Increment rate limit counter
                    $this->incrementRateLimitCounter();
                    
                    return true;
                }
            } catch (\Exception $e) {
                Log::error('WhatsApp send failed', [
                    'phone' => $phone,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            // Jika bukan attempt terakhir, tunggu sebelum retry
            if ($attempt < $maxAttempts) {
                sleep($delay);
                $delay *= $multiplier; // Exponential backoff
            }
        }

        Log::error('WhatsApp message failed after all retries', [
            'phone' => $phone,
            'max_attempts' => $maxAttempts,
        ]);

        return false;
    }

    /**
     * Kirim pesan sesuai gateway yang dipilih
     *
     * @param string $phone
     * @param string $message
     * @return bool
     * @throws GuzzleException
     */
    protected function send(string $phone, string $message): bool
    {
        return match ($this->gateway) {
            'fonnte' => $this->sendViaFonnte($phone, $message),
            'wablas' => $this->sendViaWablas($phone, $message),
            default => throw new \InvalidArgumentException("Unsupported WhatsApp gateway: {$this->gateway}"),
        };
    }

    /**
     * Kirim pesan via Fonnte
     *
     * @param string $phone
     * @param string $message
     * @return bool
     * @throws GuzzleException
     */
    protected function sendViaFonnte(string $phone, string $message): bool
    {
        $response = $this->client->post('/send', [
            'headers' => [
                'Authorization' => $this->config['api_key'],
            ],
            'form_params' => [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62', // Indonesia
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        
        // Fonnte returns status: true on success
        return $response->getStatusCode() === 200 && ($body['status'] ?? false);
    }

    /**
     * Kirim pesan via Wablas
     *
     * @param string $phone
     * @param string $message
     * @return bool
     * @throws GuzzleException
     */
    protected function sendViaWablas(string $phone, string $message): bool
    {
        $response = $this->client->post('/api/send-message', [
            'headers' => [
                'Authorization' => $this->config['api_key'],
            ],
            'json' => [
                'phone' => $phone,
                'message' => $message,
            ],
        ]);

        $body = json_decode($response->getBody()->getContents(), true);
        
        // Wablas returns status: true on success
        return $response->getStatusCode() === 200 && ($body['status'] ?? false);
    }

    /**
     * Normalisasi format nomor telepon
     *
     * @param string $phone
     * @return string
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        // Hapus semua karakter non-digit
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Konversi format Indonesia
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '+62')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '62')) {
            // Already in correct format
        } else {
            // Assume Indonesian number without country code
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Validasi format nomor telepon Indonesia
     *
     * @param string $phone
     * @return bool
     */
    protected function validatePhoneNumber(string $phone): bool
    {
        // Format: 628xxxxxxxxxx (minimal 11 digit, maksimal 15 digit)
        return preg_match('/^628\d{8,12}$/', $phone) === 1;
    }

    /**
     * Check rate limiting
     *
     * @return bool
     */
    protected function checkRateLimit(): bool
    {
        if (!config('whatsapp.rate_limit.enabled')) {
            return true;
        }

        $key = 'whatsapp:rate_limit:' . now()->format('Y-m-d-H-i');
        $maxPerMinute = config('whatsapp.rate_limit.max_per_minute');
        $current = Cache::get($key, 0);

        return $current < $maxPerMinute;
    }

    /**
     * Increment rate limit counter
     *
     * @return void
     */
    protected function incrementRateLimitCounter(): void
    {
        if (!config('whatsapp.rate_limit.enabled')) {
            return;
        }

        $key = 'whatsapp:rate_limit:' . now()->format('Y-m-d-H-i');
        $current = Cache::get($key, 0);
        Cache::put($key, $current + 1, 60); // TTL 60 seconds
    }

    /**
     * Test koneksi ke WhatsApp gateway
     *
     * @return array
     */
    public function testConnection(): array
    {
        try {
            // Test dengan nomor dummy (tidak akan terkirim)
            $testPhone = '628123456789';
            $testMessage = 'Test connection from ISP Billing System';

            // Untuk test, kita hanya cek apakah API endpoint accessible
            $response = $this->client->get('/');
            
            return [
                'success' => $response->getStatusCode() === 200,
                'gateway' => $this->gateway,
                'message' => 'Connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'gateway' => $this->gateway,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim pesan bulk ke multiple recipients
     *
     * @param array $recipients Array of ['phone' => '628xxx', 'message' => 'text']
     * @return array Results
     */
    public function sendBulk(array $recipients): array
    {
        $results = [];
        
        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? '';
            $message = $recipient['message'] ?? '';
            
            if (empty($phone) || empty($message)) {
                $results[] = [
                    'phone' => $phone,
                    'success' => false,
                    'error' => 'Missing phone or message',
                ];
                continue;
            }

            $success = $this->sendMessage($phone, $message);
            
            $results[] = [
                'phone' => $phone,
                'success' => $success,
            ];

            // Small delay between messages to avoid rate limiting
            if (count($recipients) > 1) {
                usleep(100000); // 100ms
            }
        }

        return $results;
    }
}

# Task 12.3: Implement Bulk Notification - Summary

## Tanggal Implementasi
29 Januari 2026

## Deskripsi Task
Mengimplementasikan sistem bulk notification yang memproses notifikasi dalam batch untuk menghormati rate limiting WhatsApp Gateway (50 pesan/menit).

## Requirements yang Divalidasi
- **Requirement 7.6**: Bulk notifications processed in batches of 50 to avoid rate limiting

## Komponen yang Diimplementasikan

### 1. SendBulkNotificationJob
**File**: `app/Jobs/SendBulkNotificationJob.php`

**Fitur Utama**:
- Memproses notifikasi dalam batch dengan ukuran yang dapat dikonfigurasi (default: 50)
- Menambahkan delay 60 detik antara batch untuk menghormati rate limiting (50 msg/min)
- Mendukung channel WhatsApp dan Email
- Automatic retry hingga 3 kali dengan exponential backoff (30s, 60s, 120s)
- Comprehensive error handling dan logging
- Menghitung success rate dan throw exception jika < 50%

**Metode Utama**:
```php
public function __construct(
    string $channel,
    array $recipients,
    int $batchSize = 50,
    array $metadata = []
)

public function handle(WhatsAppService $whatsappService): void

protected function sendBulkWhatsApp(WhatsAppService $whatsappService): array

protected function sendBulkEmail(): array
```

**Cara Penggunaan**:
```php
// WhatsApp bulk notification
$recipients = [
    ['phone' => '628123456789', 'message' => 'Pesan 1'],
    ['phone' => '628987654321', 'message' => 'Pesan 2'],
    // ... hingga ratusan recipients
];

SendBulkNotificationJob::dispatch('whatsapp', $recipients);

// Email bulk notification
$recipients = [
    ['email' => 'user@example.com', 'message' => 'Pesan', 'subject' => 'Subjek'],
    // ...
];

SendBulkNotificationJob::dispatch('email', $recipients);

// Custom batch size
SendBulkNotificationJob::dispatch('whatsapp', $recipients, 25);
```

### 2. Unit Tests
**File**: `tests/Unit/Jobs/SendBulkNotificationJobTest.php`

**Test Coverage**:
1. ✅ Memproses WhatsApp bulk notification dalam single batch
2. ✅ Memproses WhatsApp bulk notification dalam multiple batches
3. ✅ Menghormati konfigurasi batch size
4. ✅ Menangani partial batch failures
5. ✅ Throw exception ketika success rate terlalu rendah
6. ✅ Memproses email bulk notification
7. ✅ Menangani data recipient yang tidak lengkap
8. ✅ Throw exception untuk channel yang tidak didukung
9. ✅ Memiliki konfigurasi retry yang benar
10. ✅ Logging informasi batch processing
11. ✅ Validasi Requirement 7.6 (batch size 50)

**Total Tests**: 11 passed (14 assertions)

## Fitur Teknis

### Batch Processing
- Recipients dibagi menjadi batch dengan ukuran yang dapat dikonfigurasi
- Default batch size: 50 (sesuai rate limit WhatsApp)
- Setiap batch diproses secara berurutan
- Delay 60 detik antara batch untuk WhatsApp (menghormati 50 msg/min rate limit)
- Delay 5 detik antara batch untuk Email

### Rate Limiting Strategy
```
Rate Limit: 50 messages/minute
Batch Size: 50 messages
Delay between batches: 60 seconds

Contoh:
- 100 recipients = 2 batches
- Batch 1 (50 msg) → wait 60s → Batch 2 (50 msg)
- Total waktu: ~60 detik untuk 100 pesan
```

### Error Handling
- Setiap batch failure dicatat dalam log
- Partial failures tidak menghentikan proses
- Success rate dihitung: (success / total) * 100
- Exception dilempar jika success rate < 50%
- Automatic retry dengan exponential backoff

### Logging
Job mencatat informasi berikut:
- Total recipients dan batch size
- Progress setiap batch (X/Y completed)
- Success dan failure count per batch
- Overall success rate
- Error details untuk troubleshooting

## Integrasi dengan Sistem

### Dengan WhatsAppService
```php
// SendBulkNotificationJob menggunakan WhatsAppService->sendBulk()
$results = $whatsappService->sendBulk($batch);

// WhatsAppService sudah memiliki:
// - Rate limiting check
// - Retry mechanism
// - Phone number validation
// - Gateway abstraction (Fonnte/Wablas)
```

### Dengan Queue System
```php
// Job configuration
public $tries = 3;
public $backoff = [30, 60, 120];

// Dispatch ke queue
SendBulkNotificationJob::dispatch('whatsapp', $recipients)
    ->onQueue('notifications');

// Dengan delay
SendBulkNotificationJob::dispatch('whatsapp', $recipients)
    ->delay(now()->addMinutes(5));
```

## Use Cases

### 1. Bulk Invoice Notification
```php
// Kirim notifikasi invoice ke semua pelanggan
$recipients = Invoice::where('status', 'unpaid')
    ->with('service.customer')
    ->get()
    ->map(fn($invoice) => [
        'phone' => $invoice->service->customer->phone,
        'message' => "Invoice #{$invoice->id} sebesar Rp " . 
                     number_format($invoice->amount, 0, ',', '.') . 
                     " jatuh tempo pada " . $invoice->due_date->format('d/m/Y')
    ])
    ->toArray();

SendBulkNotificationJob::dispatch('whatsapp', $recipients);
```

### 2. Bulk Reminder Notification
```php
// Kirim reminder ke pelanggan yang invoice-nya akan jatuh tempo
$recipients = Invoice::where('status', 'unpaid')
    ->whereBetween('due_date', [now(), now()->addDays(3)])
    ->with('service.customer')
    ->get()
    ->map(fn($invoice) => [
        'phone' => $invoice->service->customer->phone,
        'message' => "Reminder: Invoice Anda akan jatuh tempo dalam 3 hari. " .
                     "Segera lakukan pembayaran untuk menghindari isolasi."
    ])
    ->toArray();

SendBulkNotificationJob::dispatch('whatsapp', $recipients);
```

### 3. Bulk Announcement
```php
// Kirim pengumuman ke semua pelanggan aktif
$recipients = Customer::whereHas('services', function($q) {
        $q->where('status', 'active');
    })
    ->get()
    ->map(fn($customer) => [
        'phone' => $customer->phone,
        'message' => "Pengumuman: Maintenance jaringan akan dilakukan pada " .
                     "tanggal 1 Februari 2026 pukul 01:00-05:00 WIB."
    ])
    ->toArray();

SendBulkNotificationJob::dispatch('whatsapp', $recipients);
```

## Performance Considerations

### Waktu Eksekusi
```
Untuk N recipients dengan batch size 50:
- Jumlah batch = ceil(N / 50)
- Waktu per batch = ~1-2 detik (tergantung gateway response)
- Delay antar batch = 60 detik
- Total waktu ≈ (jumlah_batch - 1) * 60 + (jumlah_batch * 2) detik

Contoh:
- 100 recipients = 2 batches = ~62 detik
- 500 recipients = 10 batches = ~560 detik (~9 menit)
- 1000 recipients = 20 batches = ~1160 detik (~19 menit)
```

### Queue Worker Configuration
```bash
# Supervisor configuration untuk queue worker
[program:isp-billing-queue-worker]
command=php /path/to/artisan queue:work redis --queue=notifications --tries=3 --timeout=300
autostart=true
autorestart=true
numprocs=3
```

### Memory Usage
- Setiap job menyimpan array recipients dalam memory
- Untuk 1000 recipients: ~100KB memory
- Batch processing mencegah memory overflow
- Recommended: max 5000 recipients per job

## Monitoring dan Troubleshooting

### Log Monitoring
```bash
# Monitor bulk notification logs
tail -f storage/logs/laravel.log | grep "bulk notification"

# Check failed jobs
php artisan queue:failed
```

### Metrics to Monitor
- Success rate per batch
- Average processing time per batch
- Failed job count
- Rate limit violations

### Common Issues

**Issue 1: Success rate too low**
```
Error: "Bulk notification success rate too low: 25%"
Solution: 
- Check WhatsApp gateway status
- Verify phone number formats
- Check rate limiting configuration
```

**Issue 2: Job timeout**
```
Error: Job timeout after 60 seconds
Solution:
- Increase timeout in queue worker config
- Reduce batch size
- Split into multiple jobs
```

**Issue 3: Rate limit exceeded**
```
Error: "WhatsApp rate limit exceeded"
Solution:
- Increase delay between batches
- Reduce batch size
- Check rate limit configuration
```

## Testing

### Run Unit Tests
```bash
php artisan test tests/Unit/Jobs/SendBulkNotificationJobTest.php
```

### Manual Testing
```bash
# Test dengan 5 recipients
php artisan tinker
>>> $recipients = [
...     ['phone' => '628123456789', 'message' => 'Test 1'],
...     ['phone' => '628987654321', 'message' => 'Test 2'],
...     ['phone' => '628111222333', 'message' => 'Test 3'],
...     ['phone' => '628444555666', 'message' => 'Test 4'],
...     ['phone' => '628777888999', 'message' => 'Test 5'],
... ];
>>> App\Jobs\SendBulkNotificationJob::dispatch('whatsapp', $recipients);
```

## Kesimpulan

Task 12.3 berhasil diimplementasikan dengan fitur-fitur berikut:
- ✅ Batch processing dengan ukuran 50 (sesuai Requirement 7.6)
- ✅ Queue bulk notifications untuk background processing
- ✅ Rate limiting compliance (50 msg/min)
- ✅ Comprehensive error handling
- ✅ Extensive logging untuk monitoring
- ✅ Support untuk WhatsApp dan Email channels
- ✅ 11 unit tests dengan 100% pass rate

Sistem bulk notification siap digunakan untuk mengirim notifikasi massal ke ratusan atau ribuan pelanggan dengan menghormati rate limiting dan memastikan reliability.

# WhatsApp Gateway Setup

## Overview

Sistem ini menggunakan WhatsApp gateway untuk mengirim notifikasi ke pelanggan. Sistem mendukung dua gateway populer di Indonesia:
- **Fonnte** (Rekomendasi)
- **Wablas**

## Instalasi

### 1. Install Dependencies

Guzzle HTTP client sudah terinstall sebagai dependency Laravel. Tidak perlu instalasi tambahan.

### 2. Konfigurasi Environment

Tambahkan konfigurasi berikut ke file `.env`:

```env
# WhatsApp Gateway Configuration
WHATSAPP_GATEWAY=fonnte

# Fonnte Configuration
FONNTE_API_KEY=your-fonnte-api-key-here
FONNTE_BASE_URL=https://api.fonnte.com
FONNTE_TIMEOUT=30

# Wablas Configuration (optional)
WABLAS_API_KEY=your-wablas-api-key-here
WABLAS_BASE_URL=https://console.wablas.com
WABLAS_TIMEOUT=30

# WhatsApp Retry Configuration
WHATSAPP_RETRY_MAX_ATTEMPTS=3
WHATSAPP_RETRY_DELAY=5
WHATSAPP_RETRY_MULTIPLIER=2

# WhatsApp Rate Limiting
WHATSAPP_RATE_LIMIT_ENABLED=true
WHATSAPP_RATE_LIMIT_PER_MINUTE=50
```

### 3. Mendapatkan API Key

#### Fonnte
1. Daftar di [https://fonnte.com/](https://fonnte.com/)
2. Login ke dashboard
3. Buka menu **API** atau **Settings**
4. Copy API Key Anda
5. Paste ke `FONNTE_API_KEY` di file `.env`

#### Wablas
1. Daftar di [https://wablas.com/](https://wablas.com/)
2. Login ke dashboard
3. Buka menu **API Settings**
4. Copy API Key Anda
5. Paste ke `WABLAS_API_KEY` di file `.env`

## Penggunaan

### Basic Usage

```php
use App\Services\WhatsAppService;

$whatsapp = new WhatsAppService();

// Kirim pesan single
$success = $whatsapp->sendMessage('08123456789', 'Halo, ini pesan test');

if ($success) {
    echo "Pesan berhasil dikirim";
} else {
    echo "Pesan gagal dikirim";
}
```

### Bulk Messages

```php
$recipients = [
    ['phone' => '08123456789', 'message' => 'Pesan untuk pelanggan 1'],
    ['phone' => '08123456790', 'message' => 'Pesan untuk pelanggan 2'],
    ['phone' => '08123456791', 'message' => 'Pesan untuk pelanggan 3'],
];

$results = $whatsapp->sendBulk($recipients);

foreach ($results as $result) {
    if ($result['success']) {
        echo "Berhasil kirim ke {$result['phone']}\n";
    } else {
        echo "Gagal kirim ke {$result['phone']}: {$result['error']}\n";
    }
}
```

### Test Connection

```php
$result = $whatsapp->testConnection();

if ($result['success']) {
    echo "Koneksi ke {$result['gateway']} berhasil";
} else {
    echo "Koneksi gagal: {$result['message']}";
}
```

## Format Nomor Telepon

Service akan otomatis menormalisasi format nomor telepon. Semua format berikut akan dikonversi ke `628xxx`:

- `08123456789` → `628123456789`
- `+628123456789` → `628123456789`
- `628123456789` → `628123456789` (sudah benar)
- `8123456789` → `628123456789`
- `0812-3456-789` → `628123456789` (karakter non-digit dihapus)

## Fitur

### 1. Retry Mechanism

Service akan otomatis retry hingga 3 kali jika pengiriman gagal, dengan exponential backoff:
- Attempt 1: Langsung
- Attempt 2: Delay 5 detik
- Attempt 3: Delay 10 detik

### 2. Rate Limiting

Untuk menghindari rate limit dari gateway, service membatasi pengiriman maksimal 50 pesan per menit (default). Dapat dikonfigurasi via environment variable.

### 3. Error Handling

Service menangani berbagai error:
- Invalid phone number format
- Connection timeout
- API errors
- Rate limit exceeded

Semua error dicatat di log untuk troubleshooting.

### 4. Logging

Service mencatat semua aktivitas:
- Successful sends
- Failed attempts
- Retry attempts
- Rate limit violations

Log dapat dilihat di `storage/logs/laravel.log`.

## Konfigurasi Lanjutan

### Mengubah Gateway

Untuk mengubah gateway yang digunakan, ubah nilai `WHATSAPP_GATEWAY` di `.env`:

```env
WHATSAPP_GATEWAY=wablas  # Ganti dari fonnte ke wablas
```

### Menyesuaikan Retry Configuration

```env
WHATSAPP_RETRY_MAX_ATTEMPTS=5      # Maksimal 5 kali retry
WHATSAPP_RETRY_DELAY=10            # Delay awal 10 detik
WHATSAPP_RETRY_MULTIPLIER=3        # Delay meningkat 3x setiap retry
```

### Menyesuaikan Rate Limiting

```env
WHATSAPP_RATE_LIMIT_ENABLED=true
WHATSAPP_RATE_LIMIT_PER_MINUTE=100  # Maksimal 100 pesan per menit
```

Atau disable rate limiting:

```env
WHATSAPP_RATE_LIMIT_ENABLED=false
```

### Menyesuaikan Timeout

```env
FONNTE_TIMEOUT=60  # Timeout 60 detik untuk request HTTP
```

## Testing

### Unit Tests

Jalankan unit tests untuk WhatsAppService:

```bash
php artisan test --filter=WhatsAppServiceTest
```

Tests mencakup:
- Phone number normalization
- Phone number validation
- Successful message sending
- Retry mechanism
- Rate limiting
- Bulk messaging
- Connection testing
- Error handling

### Manual Testing

Untuk test manual dengan nomor real:

```php
// Di tinker atau controller
$whatsapp = app(WhatsAppService::class);
$result = $whatsapp->sendMessage('08123456789', 'Test message dari ISP Billing System');

if ($result) {
    echo "Pesan berhasil dikirim! Cek WhatsApp Anda.";
} else {
    echo "Pesan gagal dikirim. Cek log untuk detail error.";
}
```

## Troubleshooting

### Pesan Tidak Terkirim

1. **Cek API Key**: Pastikan API key valid dan aktif
2. **Cek Saldo**: Pastikan saldo gateway mencukupi
3. **Cek Format Nomor**: Pastikan nomor dalam format Indonesia yang valid
4. **Cek Log**: Lihat `storage/logs/laravel.log` untuk detail error
5. **Test Connection**: Gunakan method `testConnection()` untuk cek konektivitas

### Rate Limit Exceeded

Jika terlalu banyak pesan dalam waktu singkat:
1. Tingkatkan `WHATSAPP_RATE_LIMIT_PER_MINUTE`
2. Atau gunakan queue untuk batch processing
3. Atau disable rate limiting (tidak direkomendasikan)

### Connection Timeout

Jika sering timeout:
1. Tingkatkan `FONNTE_TIMEOUT` atau `WABLAS_TIMEOUT`
2. Cek koneksi internet server
3. Cek status gateway di dashboard mereka

## Integrasi dengan Notification Jobs

WhatsAppService akan digunakan oleh notification jobs:

```php
// Di SendIsolationNotificationJob
use App\Services\WhatsAppService;

public function handle(WhatsAppService $whatsapp)
{
    $service = Service::with(['customer', 'package'])->find($this->serviceId);
    
    $message = "Yth. {$service->customer->name},\n\n";
    $message .= "Layanan internet Anda telah diisolir karena tunggakan pembayaran.\n";
    $message .= "Silakan lakukan pembayaran untuk mengaktifkan kembali layanan.\n\n";
    $message .= "Terima kasih.";
    
    $whatsapp->sendMessage($service->customer->phone, $message);
}
```

## Best Practices

1. **Gunakan Queue**: Selalu queue notification jobs untuk menghindari blocking
2. **Batch Processing**: Untuk bulk notifications, proses dalam batch 50 pesan
3. **Error Handling**: Selalu handle error dan log untuk troubleshooting
4. **Rate Limiting**: Aktifkan rate limiting untuk menghindari banned dari gateway
5. **Monitoring**: Monitor log secara berkala untuk deteksi masalah
6. **Testing**: Test dengan nomor real sebelum production deployment

## Referensi API

### Fonnte API Documentation
- [https://fonnte.com/api](https://fonnte.com/api)

### Wablas API Documentation
- [https://wablas.com/api](https://wablas.com/api)

## Support

Untuk pertanyaan atau masalah terkait WhatsApp gateway:
1. Cek dokumentasi gateway yang digunakan
2. Hubungi support gateway (Fonnte/Wablas)
3. Cek log aplikasi untuk detail error
4. Review konfigurasi environment variables

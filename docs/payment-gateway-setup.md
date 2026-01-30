# Payment Gateway Setup Guide

This document provides instructions for setting up and configuring the three supported payment gateways: Midtrans, Xendit, and Tripay.

## Overview

The ISP Billing System supports multiple payment gateways to provide flexible payment options for customers. Each gateway has been integrated with its official PHP library or HTTP client.

### Installed Libraries

- **Midtrans**: `midtrans/midtrans-php` v2.6.2
- **Xendit**: `xendit/xendit-php` v7.0.0
- **Tripay**: Uses Guzzle HTTP client (included with Laravel)

## Configuration

All payment gateway configurations are stored in:
- Config file: `config/payment-gateways.php`
- Environment variables: `.env`

### Default Gateway

Set the default payment gateway in your `.env` file:

```env
PAYMENT_GATEWAY_DEFAULT=midtrans
```

Options: `midtrans`, `xendit`, or `tripay`

## Midtrans Setup

### 1. Create Midtrans Account

1. Visit [https://dashboard.midtrans.com/](https://dashboard.midtrans.com/)
2. Sign up for a new account
3. Complete the verification process

### 2. Get API Credentials

1. Login to Midtrans Dashboard
2. Go to **Settings** → **Access Keys**
3. Copy your credentials:
   - Server Key
   - Client Key
   - Merchant ID

### 3. Configure Environment Variables

Add to your `.env` file:

```env
MIDTRANS_SERVER_KEY=your_server_key_here
MIDTRANS_CLIENT_KEY=your_client_key_here
MIDTRANS_MERCHANT_ID=your_merchant_id_here
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true
```

### 4. Sandbox vs Production

- **Sandbox** (Testing): Set `MIDTRANS_IS_PRODUCTION=false`
- **Production** (Live): Set `MIDTRANS_IS_PRODUCTION=true`

### 5. Webhook Configuration

Configure webhook URL in Midtrans Dashboard:
- **Webhook URL**: `https://yourdomain.com/api/webhooks/midtrans`
- **HTTP Method**: POST

### 6. Test Cards (Sandbox)

Use these test cards in sandbox mode:
- **Success**: 4811 1111 1111 1114
- **Failure**: 4911 1111 1111 1113
- **Challenge**: 4411 1111 1111 1118

## Xendit Setup

### 1. Create Xendit Account

1. Visit [https://dashboard.xendit.co/](https://dashboard.xendit.co/)
2. Sign up for a new account
3. Complete KYC verification

### 2. Get API Credentials

1. Login to Xendit Dashboard
2. Go to **Settings** → **Developers** → **API Keys**
3. Copy your credentials:
   - Secret Key
   - Public Key
   - Webhook Verification Token

### 3. Configure Environment Variables

Add to your `.env` file:

```env
XENDIT_SECRET_KEY=your_secret_key_here
XENDIT_PUBLIC_KEY=your_public_key_here
XENDIT_WEBHOOK_TOKEN=your_webhook_token_here
XENDIT_IS_PRODUCTION=false
```

### 4. Sandbox vs Production

- **Sandbox** (Testing): Set `XENDIT_IS_PRODUCTION=false`
- **Production** (Live): Set `XENDIT_IS_PRODUCTION=true`

### 5. Webhook Configuration

Configure webhook URL in Xendit Dashboard:
- **Webhook URL**: `https://yourdomain.com/api/webhooks/xendit`
- **Events**: Select "Invoice Paid"

### 6. Test Mode

In test mode, use test payment methods provided by Xendit:
- Virtual Account numbers will be test accounts
- E-wallet payments will be simulated

## Tripay Setup

### 1. Create Tripay Account

1. Visit [https://tripay.co.id/](https://tripay.co.id/)
2. Sign up for a merchant account
3. Complete verification process

### 2. Get API Credentials

1. Login to Tripay Dashboard
2. Go to **Merchant** → **API Settings**
3. Copy your credentials:
   - API Key
   - Private Key
   - Merchant Code

### 3. Configure Environment Variables

Add to your `.env` file:

```env
TRIPAY_API_KEY=your_api_key_here
TRIPAY_PRIVATE_KEY=your_private_key_here
TRIPAY_MERCHANT_CODE=your_merchant_code_here
TRIPAY_IS_PRODUCTION=false
```

### 4. Sandbox vs Production

- **Sandbox** (Testing): Set `TRIPAY_IS_PRODUCTION=false`
  - API URL: `https://tripay.co.id/api-sandbox`
- **Production** (Live): Set `TRIPAY_IS_PRODUCTION=true`
  - API URL: `https://tripay.co.id/api`

### 5. Webhook Configuration

Configure webhook URL in Tripay Dashboard:
- **Webhook URL**: `https://yourdomain.com/api/webhooks/tripay`
- **HTTP Method**: POST

### 6. Payment Channels

Tripay supports various payment channels:
- Bank Transfer (BCA, BNI, BRI, Mandiri, etc.)
- E-wallet (OVO, DANA, ShopeePay, LinkAja)
- Retail (Alfamart, Indomaret)
- QRIS

## Webhook Security

All payment gateway webhooks implement signature verification to ensure authenticity:

### Midtrans
- Uses SHA512 hash with server key
- Validates: `order_id`, `status_code`, `gross_amount`, `server_key`

### Xendit
- Uses webhook verification token
- Validates: `X-CALLBACK-TOKEN` header

### Tripay
- Uses HMAC SHA256 signature
- Validates: `X-Callback-Signature` header with private key

## Testing Webhooks Locally

To test webhooks on your local development environment:

### Option 1: ngrok

```bash
# Install ngrok
npm install -g ngrok

# Start ngrok tunnel
ngrok http 8000

# Use the ngrok URL in payment gateway webhook settings
# Example: https://abc123.ngrok.io/api/webhooks/midtrans
```

### Option 2: Laravel Valet Share (Mac)

```bash
valet share
```

### Option 3: Expose (Alternative to ngrok)

```bash
# Install expose
composer global require beyondcode/expose

# Start expose tunnel
expose share http://localhost:8000
```

## Configuration Validation

The system includes configuration validation to ensure credentials are correct before saving:

```php
// Test Midtrans connection
php artisan payment:test midtrans

// Test Xendit connection
php artisan payment:test xendit

// Test Tripay connection
php artisan payment:test tripay
```

## Switching Between Gateways

To switch the active payment gateway:

1. Update `.env`:
   ```env
   PAYMENT_GATEWAY_DEFAULT=xendit
   ```

2. Clear config cache:
   ```bash
   php artisan config:clear
   ```

3. Verify the change:
   ```bash
   php artisan tinker
   >>> config('payment-gateways.default')
   ```

## Production Checklist

Before going live with payment gateways:

- [ ] Switch all gateways to production mode (`IS_PRODUCTION=true`)
- [ ] Use production API credentials (not sandbox)
- [ ] Configure production webhook URLs with HTTPS
- [ ] Test webhook signature verification
- [ ] Enable error logging and monitoring
- [ ] Set up payment reconciliation process
- [ ] Configure proper error handling and retry logic
- [ ] Test all payment methods (bank transfer, e-wallet, etc.)
- [ ] Verify refund and cancellation flows
- [ ] Set up payment notification to customers

## Troubleshooting

### Common Issues

**1. Webhook not receiving callbacks**
- Check firewall settings
- Verify webhook URL is publicly accessible
- Check webhook logs in payment gateway dashboard
- Ensure HTTPS is enabled in production

**2. Signature verification fails**
- Verify API credentials are correct
- Check for extra whitespace in environment variables
- Ensure server time is synchronized (NTP)

**3. Payment link generation fails**
- Verify API credentials
- Check API rate limits
- Review error logs for specific error messages
- Ensure required fields are provided

**4. Connection timeout**
- Check internet connectivity
- Verify payment gateway API status
- Increase timeout settings if needed

### Debug Mode

Enable debug logging for payment gateways:

```env
LOG_LEVEL=debug
PAYMENT_GATEWAY_DEBUG=true
```

Check logs:
```bash
tail -f storage/logs/laravel.log
```

## Support

### Midtrans Support
- Documentation: [https://docs.midtrans.com/](https://docs.midtrans.com/)
- Support: [https://midtrans.com/contact-us](https://midtrans.com/contact-us)

### Xendit Support
- Documentation: [https://developers.xendit.co/](https://developers.xendit.co/)
- Support: [https://help.xendit.co/](https://help.xendit.co/)

### Tripay Support
- Documentation: [https://tripay.co.id/developer](https://tripay.co.id/developer)
- Support: [https://tripay.co.id/contact](https://tripay.co.id/contact)

## Next Steps

After completing the payment gateway setup:

1. Implement payment gateway service classes (Task 10.2)
2. Create webhook handlers (Task 10.3)
3. Write property-based tests (Task 10.4)
4. Test end-to-end payment flow
5. Configure production environment

## References

- [Midtrans PHP Library](https://github.com/Midtrans/midtrans-php)
- [Xendit PHP Library](https://github.com/xendit/xendit-php)
- [Tripay API Documentation](https://tripay.co.id/developer)
- [Laravel HTTP Client](https://laravel.com/docs/12.x/http-client)

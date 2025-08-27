# EmailListVerify PHP SDK

Official PHP SDK for the EmailListVerify API - Email validation and verification service.

## Installation

Install via Composer:

```bash
composer require emaillistverify/emaillistverify-sdk
```

## Usage

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use EmailListVerify\EmailListVerify;

// Initialize client with your API key
$client = new EmailListVerify('your-api-key-here');

// Verify a single email
$result = $client->verifyEmail('test@example.com');
echo $result; // Returns: ok, invalid, invalid_mx, accept_all, ok_for_all, disposable, role, email_disabled, dead_server, or unknown

// Verify multiple emails
$emails = ['test@example.com', 'invalid@domain.com', 'user@gmail.com'];
$results = $client->verifyEmails($emails);

foreach ($results as $email => $status) {
    echo "Email: $email - Status: $status\n";
}
```

### Response Values

The API returns simple text responses:

- `ok` - Valid email address
- `invalid` - Invalid email format or domain
- `invalid_mx` - Domain has no valid MX records
- `accept_all` - Server accepts all emails (catch-all)
- `ok_for_all` - Valid but may be catch-all
- `disposable` - Temporary/disposable email address
- `role` - Role-based email (e.g., admin@, info@)
- `email_disabled` - Email account is disabled
- `dead_server` - Mail server is not responding
- `unknown` - Unable to determine status

### Error Handling

```php
try {
    $result = $client->verifyEmail('test@example.com');
    echo "Result: $result";
} catch (\EmailListVerify\EmailListVerifyException $e) {
    echo "Error: " . $e->getMessage();
}
```

## Requirements

- PHP 7.2 or higher
- cURL extension
- JSON extension

## API Endpoint

This SDK uses the EmailListVerify API endpoint:
- **URL**: https://apps.emaillistverify.com/api/verifyEmail
- **Method**: GET
- **Parameters**: 
  - `secret` (API key)
  - `email` (email address to verify)

## License

MIT License - see LICENSE file for details.

## Support

For API documentation and support, visit [EmailListVerify.com](https://emaillistverify.com)

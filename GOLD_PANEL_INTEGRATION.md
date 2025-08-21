# GOLD PANEL API Integration Documentation

## Overview
This document describes the complete integration of GOLD PANEL API with the Laravel payment system to manage IPTV device subscriptions.

## Completed Features

### 1. Database Schema
Created four new tables to support GOLD PANEL integration:

- **`devices`**: Stores device information linked to customers
  - Device types: MAG, M3U
  - Credentials (MAC address for MAG, username/password for M3U)
  - Subscription details (package, duration, expiry)
  - API integration data (user_id, status)

- **`device_logs`**: Tracks all device-related actions
  - Action types: add, renew, status_change, info_fetch, expiry_reminder
  - Stores API responses for debugging

- **`packages`**: Synced packages from GOLD PANEL API
  - Package ID and name from API

- **`reseller_info`**: Reseller account management
  - Credits tracking
  - API key storage
  - Account status

### 2. Models
Created Eloquent models with relationships:
- `Device` → Customer, Package, DeviceLogs
- `DeviceLog` → Device
- `Package` → Devices
- `ResellerInfo` (singleton pattern for active reseller)

### 3. Service Layer
**`GoldPanelService`** handles all API interactions:
- Create MAG/M3U devices
- Renew subscriptions
- Change device status (enable/disable)
- Fetch device information
- Sync reseller info and packages
- Helper methods for MAC/username/password generation

### 4. Queue Jobs
**`ProcessGoldPanelDevice`** job handles asynchronous device creation:
- Triggered after successful payment
- Creates customer record
- Calls GOLD PANEL API
- Saves device data
- Sends email notification
- Handles retries on failure

### 5. Payment Integration
Modified `ProcessPendingPayment` job:
- Checks for `gold_panel_device` metadata in payment
- Dispatches device creation job after subscription activation

### 6. Controllers
**`DeviceController`** provides web interface:
- Show device details with credentials
- List customer devices
- Download M3U files
- Renew subscriptions
- Toggle device status
- Sync device info from API

### 7. Notifications
- **`DeviceCreated`**: Sent when device is created with credentials
- **`DeviceExpiringReminder`**: Sent 3 days before expiry

### 8. Scheduled Tasks
**`SendDeviceExpiryReminders`** command:
- Runs daily at 10:00 AM
- Sends reminders 3 days and 1 day before expiry
- Logs all reminder activities

### 9. Views
- `devices/show.blade.php`: Device details page
- `devices/index.blade.php`: Customer devices list

### 10. Routes
```php
/devices/{device} - Show device details
/devices/{device}/download-m3u - Download M3U file
/devices/{device}/renew - Renew subscription
/devices/{device}/toggle-status - Enable/disable device
/devices/{device}/sync - Sync device info
/devices/customer/list - List customer devices
```

## Configuration

### Environment Variables
Add to `.env` file:
```env
GOLD_PANEL_BASE_URL=https://your.gold.panel.api/api
GOLD_PANEL_API_KEY=your_api_key_here
GOLD_PANEL_DEFAULT_PACK_ID=1
GOLD_PANEL_DEFAULT_COUNTRY=US
```

## Usage Flow

### 1. Payment Processing
When a payment is successful with GOLD PANEL metadata:
```php
$payment->metadata = [
    'gold_panel_device' => [
        'type' => 'M3U', // or 'MAG'
        'pack_id' => 1,
        'sub_duration' => 3, // months
        'notes' => 'Customer notes',
        'country' => 'US'
    ]
];
```

### 2. Device Creation Flow
1. Payment marked as successful
2. Subscription created
3. `ProcessGoldPanelDevice` job dispatched
4. API call to create device
5. Device saved to database
6. Email sent to customer with credentials

### 3. Customer Access
Customers can:
- View device credentials at `/devices/{id}`
- Download M3U files
- Renew subscriptions
- Check device status

## API Endpoints Used

### Create MAG Device
```
POST /mag_add
{
    "api_key": "xxx",
    "mac": "00:1A:79:XX:XX:XX",
    "pack_id": 1,
    "sub_duration": 3,
    "notes": "Customer notes",
    "country": "US"
}
```

### Create M3U Device
```
POST /line_add
{
    "api_key": "xxx",
    "username": "user123",
    "password": "pass456",
    "pack_id": 1,
    "sub_duration": 3,
    "notes": "Customer notes",
    "country": "US"
}
```

### Renew Device
```
POST /renew
{
    "api_key": "xxx",
    "user_id": 123,
    "sub_duration": 3
}
```

### Change Status
```
POST /enable_disable
{
    "api_key": "xxx",
    "user_id": 123,
    "user_status": "enable" // or "disable"
}
```

## Testing

### Manual Testing
1. Create a test payment with GOLD PANEL metadata
2. Process the payment to trigger device creation
3. Check device details page
4. Test renewal and status toggle

### Command Testing
```bash
# Test expiry reminders
php artisan devices:send-expiry-reminders --days=3

# Process pending payments
php artisan queue:work
```

## Security Considerations

1. **API Key**: Store in `.env` file, never commit
2. **Credentials**: Encrypted in database using Laravel's encryption
3. **Access Control**: Implement authentication for device routes
4. **Rate Limiting**: Add rate limiting to prevent API abuse
5. **Validation**: Validate all input data before API calls

## Future Enhancements

1. **Admin Panel Integration**: Add Filament resources for device management
2. **Bulk Operations**: Support bulk device creation/renewal
3. **Analytics**: Track device usage and subscription metrics
4. **Multi-Provider**: Support multiple IPTV providers
5. **Auto-Renewal**: Implement automatic subscription renewal
6. **Device Transfer**: Allow device transfer between customers
7. **Trial Periods**: Support trial subscriptions
8. **Reseller Management**: Advanced reseller credit management

## Troubleshooting

### Device Creation Failed
- Check API key in `.env`
- Verify reseller credits
- Check Laravel logs: `storage/logs/laravel.log`
- Review device_logs table for API responses

### Email Not Sent
- Verify mail configuration
- Check queue worker is running
- Review failed_jobs table

### Cron Jobs Not Running
- Ensure Laravel scheduler is added to crontab:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Support
For issues or questions about the GOLD PANEL integration, check:
- Laravel logs
- Device logs in database
- API documentation
- Queue job status

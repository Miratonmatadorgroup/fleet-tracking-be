# Loops Freight Fleet Tracking System - Backend

## Overview
Laravel 12 backend for the Loops Freight Fleet Tracking Management System. This system provides real-time GPS tracking, fuel consumption calculation, subscription management, and geofencing capabilities for multi-tenant fleet operations.

## Tech Stack
- **Framework**: Laravel 12
- **Database**: PostgreSQL 15+ with PostGIS extension
- **Cache/Queue**: Redis 7+
- **Real-time**: Pusher / Laravel Echo (WebSocket)
- **Payment**: Stripe + Paystack
- **PHP Version**: 8.2+

## Features
- ✅ Multi-tenant architecture (Super Admin, Office Admin, User roles)
- ✅ Real-time GPS tracking with WebSocket broadcasting
- ✅ Advanced fuel calculation engine (distance + idle + speeding)
- ✅ "Anxiety-driven" subscription retention (blurry screen protocol)
- ✅ Geofencing with PostGIS spatial queries
- ✅ Remote vehicle shutdown with 2-step verification
- ✅ Comprehensive audit logging
- ✅ Payment integration (Stripe + Paystack)
- ✅ Automated subscription expiry management

## Installation

### Prerequisites
```bash
- PHP 8.2+
- Composer 2.6+
- PostgreSQL 15+ with PostGIS extension
- Redis 7+
- Node.js 20+ (for Laravel Mix/Vite)
```

### Step 1: Clone and Install Dependencies
```bash
git clone <repository-url>
cd fleet-tracking-backend
composer install
npm install
```

### Step 2: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your configuration:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fleet_tracking
DB_USERNAME=postgres
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret

STRIPE_SECRET=sk_test_...
PAYSTACK_SECRET_KEY=sk_test_...

GPS_API_URL=https://chinese-partner-api.example.com
GPS_API_KEY=your_gps_api_key
```

### Step 3: Database Setup
```bash
# Create database
createdb fleet_tracking

# Enable PostGIS extension
psql -d fleet_tracking -c "CREATE EXTENSION IF NOT EXISTS postgis;"
psql -d fleet_tracking -c "CREATE EXTENSION IF NOT EXISTS pg_partman;"

# Run migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed
```

### Step 4: Start Services
```bash
# Start Laravel development server
php artisan serve

# Start queue worker (separate terminal)
php artisan queue:work redis --queue=default,gps,notifications

# Start scheduler (separate terminal or add to cron)
php artisan schedule:work
```

## API Documentation

### Authentication
All API endpoints require authentication via Laravel Sanctum tokens.

#### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

Response:
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "role": "office_admin"
  }
}
```

#### Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

### Assets

#### List Assets
```http
GET /api/assets
Authorization: Bearer {token}

Query Parameters:
- status: active|idle|offline|maintenance
- class: A|B|C
- organization_id: integer (Super Admin only)
- page: integer
- per_page: integer (default: 15)

Response:
{
  "data": [
    {
      "id": 1,
      "equipment_id": "CN123456789",
      "asset_type": "truck",
      "class": "B",
      "license_plate": "ABC-123",
      "status": "active",
      "last_known_location": {
        "latitude": 6.5244,
        "longitude": 3.3792,
        "timestamp": "2024-01-21T14:30:00Z"
      },
      "is_online": true
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 50,
    "per_page": 15
  }
}
```

#### Get Asset Details
```http
GET /api/assets/{id}
Authorization: Bearer {token}

Response:
{
  "id": 1,
  "equipment_id": "CN123456789",
  "asset_type": "truck",
  "class": "B",
  "make": "Mercedes-Benz",
  "model": "Actros",
  "year": 2022,
  "license_plate": "ABC-123",
  "driver": {
    "id": 5,
    "name": "John Driver",
    "phone": "+234123456789"
  },
  "subscription": {
    "status": "active",
    "end_date": "2024-12-31",
    "days_until_expiry": 345
  }
}
```

#### Create Asset
```http
POST /api/assets
Authorization: Bearer {token}
Content-Type: application/json

{
  "equipment_id": "CN987654321",
  "asset_type": "truck",
  "class": "B",
  "make": "Mercedes-Benz",
  "model": "Actros",
  "year": 2023,
  "license_plate": "XYZ-789",
  "base_consumption_rate": 0.25,
  "idle_consumption_rate": 2.5,
  "speeding_penalty": 0.15
}
```

### GPS Tracking

#### Get Real-time Location
```http
GET /api/assets/{id}/location
Authorization: Bearer {token}

Response:
{
  "latitude": 6.5244,
  "longitude": 3.3792,
  "speed": 85.5,
  "ignition": true,
  "heading": 270,
  "timestamp": "2024-01-21T14:30:00Z"
}
```

#### Get Historical Route
```http
GET /api/assets/{id}/route
Authorization: Bearer {token}

Query Parameters:
- start_date: YYYY-MM-DD (required)
- end_date: YYYY-MM-DD (required)
- interval: integer (minutes between points, default: 5)

Response:
{
  "points": [
    {
      "latitude": 6.5244,
      "longitude": 3.3792,
      "speed": 85.5,
      "timestamp": "2024-01-21T08:00:00Z"
    },
    ...
  ],
  "summary": {
    "total_distance_km": 245.5,
    "total_duration_hours": 8.5,
    "avg_speed": 28.9,
    "max_speed": 95.2
  }
}
```

#### GPS Webhook (Chinese Partner API)
```http
POST /api/webhooks/gps
Headers:
  X-API-Key: {GPS_API_KEY}
Content-Type: application/json

{
  "device_id": "CN123456789",
  "latitude": 6.5244,
  "longitude": 3.3792,
  "speed": 85.5,
  "ignition": true,
  "heading": 270,
  "altitude": 45,
  "satellites": 12,
  "timestamp": "2024-01-21T14:30:00Z"
}

Response: 202 Accepted
```

### Fuel Reports

#### Get Fuel Report
```http
GET /api/assets/{id}/fuel-reports
Authorization: Bearer {token}

Query Parameters:
- start_date: YYYY-MM-DD
- end_date: YYYY-MM-DD
- page: integer

Response:
{
  "data": [
    {
      "id": 1,
      "trip_start": "2024-01-21T00:00:00Z",
      "trip_end": "2024-01-21T23:59:59Z",
      "distance_km": 245.5,
      "idle_hours": 2.5,
      "speeding_km": 45.2,
      "fuel_consumed_liters": 85.3,
      "breakdown": {
        "base_fuel": 61.4,
        "idle_fuel": 6.3,
        "speeding_fuel": 17.6
      }
    }
  ]
}
```

### Subscriptions

#### Create Subscription
```http
POST /api/subscriptions
Authorization: Bearer {token}
Content-Type: application/json

{
  "asset_id": 1,
  "plan_class": "B",
  "billing_cycle": "yearly",
  "payment_method": "stripe",
  "stripe_payment_method_id": "pm_..."
}

Response:
{
  "id": 1,
  "status": "active",
  "start_date": "2024-01-21",
  "end_date": "2025-01-21",
  "total_amount": 30000,
  "payment": {
    "id": 1,
    "status": "completed",
    "transaction_id": "pi_..."
  }
}
```

#### Renew Subscription
```http
POST /api/subscriptions/{id}/renew
Authorization: Bearer {token}
Content-Type: application/json

{
  "billing_cycle": "quarterly",
  "payment_method": "stripe"
}
```

### Geofencing

#### Create Geofence
```http
POST /api/geofences
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Lagos Office Zone",
  "type": "polygon",
  "coordinates": [
    [6.5244, 3.3792],
    [6.5245, 3.3793],
    [6.5246, 3.3791],
    [6.5244, 3.3792]
  ],
  "alert_on_entry": true,
  "alert_on_exit": true,
  "curfew_start": "00:00:00",
  "curfew_end": "03:00:00"
}
```

#### Get Geofence Breaches
```http
GET /api/assets/{id}/geofence-breaches
Authorization: Bearer {token}

Query Parameters:
- start_date: YYYY-MM-DD
- end_date: YYYY-MM-DD
- breach_type: entry|exit|curfew

Response:
{
  "data": [
    {
      "id": 1,
      "geofence": {
        "id": 1,
        "name": "Lagos Office Zone"
      },
      "breach_type": "exit",
      "latitude": 6.5244,
      "longitude": 3.3792,
      "timestamp": "2024-01-21T14:30:00Z"
    }
  ]
}
```

### Remote Control

#### Request Shutdown Code
```http
POST /api/assets/{id}/remote-shutdown/request
Authorization: Bearer {token}

Response:
{
  "code": "123456",
  "expires_at": "2024-01-21T14:35:00Z"
}
```

#### Execute Shutdown
```http
POST /api/assets/{id}/remote-shutdown/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "123456"
}

Response:
{
  "status": "success",
  "command_id": 1,
  "message": "Shutdown command sent to device"
}
```

## WebSocket Events

### Subscribe to Asset Location Updates
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.PUSHER_APP_KEY,
    cluster: process.env.PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${token}`
        }
    }
});

// Subscribe to asset channel
Echo.private(`asset.${assetId}`)
    .listen('AssetLocationUpdated', (e) => {
        console.log('New location:', e.latitude, e.longitude);
        updateMapMarker(e);
    });

// Subscribe to geofence breaches
Echo.private(`user.${userId}`)
    .listen('GeofenceBreached', (e) => {
        showAlert(`Asset ${e.asset.license_plate} breached ${e.geofence.name}`);
    });
```

## Background Jobs

### GPS Data Processing
```php
// app/Jobs/ProcessGpsData.php
// Processes incoming GPS data asynchronously
// Queue: gps
// Retry: 3 times
```

### Fuel Calculation
```php
// app/Jobs/CalculateDailyFuelConsumption.php
// Runs daily at 2 AM via scheduler
// Calculates fuel consumption for all assets
```

### Subscription Expiry Check
```php
// app/Jobs/CheckExpiringSubscriptions.php
// Runs daily at midnight
// Sends expiry alerts and marks expired subscriptions
```

### Geofence Breach Detection
```php
// app/Listeners/CheckGeofenceBreach.php
// Triggered by GpsDataReceived event
// Checks for geofence violations in real-time
```

## Scheduled Tasks

Add to crontab:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Or run scheduler in foreground (development):
```bash
php artisan schedule:work
```

## Testing

### Run Tests
```bash
# All tests
php artisan test

# Specific test suite
php artisan test --testsuite=Feature

# With coverage
php artisan test --coverage
```

### Example Test
```php
// tests/Feature/AssetTest.php
public function test_user_can_view_own_assets()
{
    $user = User::factory()->create(['role' => 'user']);
    $asset = Asset::factory()->create(['organization_id' => $user->organization_id]);

    $response = $this->actingAs($user)
                     ->getJson('/api/assets');

    $response->assertStatus(200)
             ->assertJsonFragment(['id' => $asset->id]);
}
```

## Deployment

### Docker Setup
```bash
# Build image
docker build -t loops-freight-backend .

# Run container
docker run -d \
  --name fleet-tracking \
  -p 8000:8000 \
  -e DB_HOST=postgres \
  -e REDIS_HOST=redis \
  loops-freight-backend
```

### Docker Compose
```bash
docker-compose up -d
```

### Production Checklist
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure proper database credentials
- [ ] Set up SSL certificates (Let's Encrypt)
- [ ] Configure Redis for caching and queues
- [ ] Set up Supervisor for queue workers
- [ ] Configure backup strategy (pg_dump + WAL archiving)
- [ ] Set up monitoring (Sentry, New Relic)
- [ ] Configure log rotation
- [ ] Set up firewall rules (allow only 80, 443, 22)
- [ ] Enable rate limiting
- [ ] Configure CORS for frontend domain

## Performance Optimization

### Database Optimization
```bash
# Create indexes
php artisan db:index

# Analyze query performance
php artisan telescope:install
```

### Caching
```php
// Cache asset location for 5 minutes
Cache::remember("asset:{$id}:location", 300, function () use ($id) {
    return Asset::find($id)->getLastKnownLocation();
});
```

### Queue Optimization
```bash
# Run multiple queue workers
php artisan queue:work redis --queue=gps --tries=3 &
php artisan queue:work redis --queue=notifications --tries=3 &
```

## Troubleshooting

### GPS Data Not Updating
1. Check queue worker is running: `php artisan queue:work`
2. Verify GPS API key in `.env`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Test webhook manually: `curl -X POST http://localhost:8000/api/webhooks/gps`

### WebSocket Not Connecting
1. Verify Pusher credentials in `.env`
2. Check browser console for errors
3. Test connection: `php artisan pusher:test`

### Subscription Not Expiring
1. Check scheduler is running: `php artisan schedule:work`
2. Verify cron job is configured
3. Manually trigger: `php artisan subscriptions:check-expiry`

## Security

### API Rate Limiting
```php
// config/sanctum.php
'middleware' => [
    'throttle:api', // 60 requests per minute
],
```

### CORS Configuration
```php
// config/cors.php
'allowed_origins' => [
    'https://dashboard.loopsfreight.com',
],
```

### Audit Logging
All critical actions are logged in the `audit_logs` table:
- User login/logout
- Asset creation/deletion
- Remote shutdown commands
- Subscription modifications

## Contributing
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Commit changes: `git commit -am 'Add new feature'`
4. Push to branch: `git push origin feature/new-feature`
5. Submit a pull request

## License
Proprietary - Loops Freight © 2024

## Support
For technical support, contact: dev@loopsfreight.com
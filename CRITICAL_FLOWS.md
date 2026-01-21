# Loops Freight Fleet Tracking System - Critical Flows

## Flow 1: GPS Data Ingestion & Real-time Broadcasting

### Actors
- Chinese Hardware Device
- Chinese Partner API
- Laravel GPS Ingestion Service
- PostgreSQL Database
- Redis Cache
- Pusher (WebSocket)
- Next.js Frontend

### Preconditions
- Asset is registered in the system
- Hardware device is installed and powered on
- Subscription is active

### Flow Steps

#### Step 1: Hardware Sends GPS Data
```
Chinese Device → Chinese Partner API
POST https://partner-api.example.com/gps/push
{
  "device_id": "CN123456789",
  "protocol": "GT06",
  "data": "7878110100035889657045490000000000000D0A"
}
```

#### Step 2: Chinese API Forwards to Our Webhook
```
Chinese Partner API → Laravel Webhook Endpoint
POST https://loops-freight.com/api/webhooks/gps
Headers: X-API-Key: {secret_key}
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
```

#### Step 3: Laravel Validates & Normalizes Data
```php
// app/Http/Controllers/Api/GpsWebhookController.php
public function receive(Request $request)
{
    // 1. Validate API Key
    if ($request->header('X-API-Key') !== config('services.gps.api_key')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // 2. Validate Payload
    $validated = $request->validate([
        'device_id' => 'required|string',
        'latitude' => 'required|numeric|between:-90,90',
        'longitude' => 'required|numeric|between:-180,180',
        'speed' => 'required|numeric|min:0',
        'ignition' => 'required|boolean',
        'timestamp' => 'required|date',
    ]);

    // 3. Find Asset by Equipment ID
    $asset = Asset::where('equipment_id', $validated['device_id'])->first();
    
    if (!$asset) {
        Log::warning('GPS data received for unknown device', ['device_id' => $validated['device_id']]);
        return response()->json(['error' => 'Device not found'], 404);
    }

    // 4. Dispatch Job to Queue (async processing)
    ProcessGpsData::dispatch($asset, $validated);

    return response()->json(['status' => 'accepted'], 202);
}
```

#### Step 4: Queue Worker Processes GPS Data
```php
// app/Jobs/ProcessGpsData.php
public function handle()
{
    DB::transaction(function () {
        // 1. Insert GPS Log
        $gpsLog = GpsLog::create([
            'asset_id' => $this->asset->id,
            'latitude' => $this->data['latitude'],
            'longitude' => $this->data['longitude'],
            'speed' => $this->data['speed'],
            'ignition' => $this->data['ignition'],
            'heading' => $this->data['heading'] ?? null,
            'altitude' => $this->data['altitude'] ?? null,
            'satellites' => $this->data['satellites'] ?? null,
            'timestamp' => $this->data['timestamp'],
        ]);

        // 2. Update Asset Last Known Location
        $this->asset->update([
            'last_known_lat' => $this->data['latitude'],
            'last_known_lng' => $this->data['longitude'],
            'last_ping_at' => $this->data['timestamp'],
            'status' => $this->data['speed'] > 0 ? 'active' : 'idle',
        ]);

        // 3. Update Redis Cache (TTL: 5 minutes)
        Cache::put(
            "asset:{$this->asset->id}:location",
            [
                'lat' => $this->data['latitude'],
                'lng' => $this->data['longitude'],
                'speed' => $this->data['speed'],
                'ignition' => $this->data['ignition'],
                'timestamp' => $this->data['timestamp'],
            ],
            now()->addMinutes(5)
        );

        // 4. Check Geofence Breaches
        event(new GpsDataReceived($this->asset, $gpsLog));

        // 5. Broadcast to WebSocket (Real-time Update)
        broadcast(new AssetLocationUpdated($this->asset, $this->data))->toOthers();
    });
}
```

#### Step 5: Frontend Receives WebSocket Update
```typescript
// frontend/lib/pusher.ts
import Pusher from 'pusher-js';

const pusher = new Pusher(process.env.NEXT_PUBLIC_PUSHER_KEY!, {
  cluster: process.env.NEXT_PUBLIC_PUSHER_CLUSTER!,
  authEndpoint: '/api/pusher/auth',
});

// Subscribe to asset channel
const channel = pusher.subscribe(`asset.${assetId}`);

channel.bind('location-updated', (data: {
  latitude: number;
  longitude: number;
  speed: number;
  ignition: boolean;
  timestamp: string;
}) => {
  // Update map marker position
  updateMarkerPosition(assetId, data.latitude, data.longitude);
  
  // Update speed indicator
  updateSpeedDisplay(assetId, data.speed);
  
  // Update status icon
  updateStatusIcon(assetId, data.speed > 0 ? 'moving' : 'idle');
});
```

### Postconditions
- GPS log stored in database
- Asset last known location updated
- Redis cache refreshed
- Real-time update broadcasted to all connected clients
- Geofence breach check triggered (if applicable)

### Error Handling
- **Invalid API Key**: Return 401 Unauthorized
- **Unknown Device**: Log warning, return 404
- **Database Error**: Retry job 3 times with exponential backoff
- **WebSocket Failure**: Continue processing, log error

### Performance Metrics
- **Target Latency**: < 2 seconds from device to frontend
- **Throughput**: 10,000 GPS messages/minute
- **Database Write**: < 50ms per insert
- **WebSocket Broadcast**: < 100ms

---

## Flow 2: Fuel Consumption Calculation

### Actors
- Laravel Scheduler (Cron)
- Fuel Calculation Service
- GPS Logs Table
- Assets Table
- Fuel Reports Table

### Preconditions
- Asset has GPS logs for the calculation period
- Asset has consumption rates configured

### Flow Steps

#### Step 1: Scheduler Triggers Daily Calculation
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run fuel calculation daily at 2 AM
    $schedule->job(new CalculateDailyFuelConsumption)
             ->dailyAt('02:00')
             ->timezone('Africa/Lagos');
}
```

#### Step 2: Fetch GPS Logs for Yesterday
```php
// app/Jobs/CalculateDailyFuelConsumption.php
public function handle()
{
    $yesterday = now()->subDay()->toDateString();
    
    Asset::with('organization')
        ->whereHas('gpsLogs', function ($query) use ($yesterday) {
            $query->whereDate('timestamp', $yesterday);
        })
        ->chunk(100, function ($assets) use ($yesterday) {
            foreach ($assets as $asset) {
                CalculateAssetFuelConsumption::dispatch($asset, $yesterday);
            }
        });
}
```

#### Step 3: Calculate Fuel Components
```php
// app/Services/FuelCalculationService.php
class FuelCalculationService
{
    public function calculate(Asset $asset, string $date): array
    {
        $gpsLogs = GpsLog::where('asset_id', $asset->id)
            ->whereDate('timestamp', $date)
            ->orderBy('timestamp')
            ->get();

        if ($gpsLogs->count() < 2) {
            return null; // Not enough data
        }

        // 1. Calculate Distance (D)
        $totalDistance = 0;
        for ($i = 1; $i < $gpsLogs->count(); $i++) {
            $prev = $gpsLogs[$i - 1];
            $curr = $gpsLogs[$i];
            
            $totalDistance += $this->haversineDistance(
                $prev->latitude,
                $prev->longitude,
                $curr->latitude,
                $curr->longitude
            );
        }

        // 2. Calculate Idle Time (T_idle)
        $idleHours = 0;
        foreach ($gpsLogs as $log) {
            if ($log->speed == 0 && $log->ignition == true) {
                // Assume each log represents 1 minute (adjust based on hardware frequency)
                $idleHours += 1 / 60; // Convert minutes to hours
            }
        }

        // 3. Calculate Speeding Distance (D_speeding)
        $speedingDistance = 0;
        for ($i = 1; $i < $gpsLogs->count(); $i++) {
            $prev = $gpsLogs[$i - 1];
            $curr = $gpsLogs[$i];
            
            if ($curr->speed > 100) {
                $speedingDistance += $this->haversineDistance(
                    $prev->latitude,
                    $prev->longitude,
                    $curr->latitude,
                    $curr->longitude
                );
            }
        }

        // 4. Apply Master Formula
        $baseFuel = $totalDistance * $asset->base_consumption_rate;
        $idleFuel = $idleHours * $asset->idle_consumption_rate;
        $speedingFuel = $speedingDistance * $asset->base_consumption_rate * $asset->speeding_penalty;
        
        $totalFuel = $baseFuel + $idleFuel + $speedingFuel;

        return [
            'distance_km' => round($totalDistance, 2),
            'idle_hours' => round($idleHours, 2),
            'speeding_km' => round($speedingDistance, 2),
            'base_fuel' => round($baseFuel, 2),
            'idle_fuel' => round($idleFuel, 2),
            'speeding_fuel' => round($speedingFuel, 2),
            'total_fuel' => round($totalFuel, 2),
            'avg_speed' => round($gpsLogs->avg('speed'), 2),
            'max_speed' => round($gpsLogs->max('speed'), 2),
        ];
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
```

#### Step 4: Store Fuel Report
```php
// app/Jobs/CalculateAssetFuelConsumption.php
public function handle(FuelCalculationService $service)
{
    $result = $service->calculate($this->asset, $this->date);

    if (!$result) {
        Log::info('Insufficient GPS data for fuel calculation', [
            'asset_id' => $this->asset->id,
            'date' => $this->date,
        ]);
        return;
    }

    FuelReport::create([
        'asset_id' => $this->asset->id,
        'trip_start' => $this->date . ' 00:00:00',
        'trip_end' => $this->date . ' 23:59:59',
        'distance_km' => $result['distance_km'],
        'idle_hours' => $result['idle_hours'],
        'speeding_km' => $result['speeding_km'],
        'base_fuel' => $result['base_fuel'],
        'idle_fuel' => $result['idle_fuel'],
        'speeding_fuel' => $result['speeding_fuel'],
        'fuel_consumed_liters' => $result['total_fuel'],
        'avg_speed' => $result['avg_speed'],
        'max_speed' => $result['max_speed'],
    ]);

    Log::info('Fuel report generated', [
        'asset_id' => $this->asset->id,
        'date' => $this->date,
        'fuel_consumed' => $result['total_fuel'],
    ]);
}
```

#### Step 5: API Endpoint for Frontend
```php
// app/Http/Controllers/Api/FuelReportController.php
public function index(Request $request, Asset $asset)
{
    $this->authorize('view', $asset);

    $query = FuelReport::where('asset_id', $asset->id);

    // Filter by date range
    if ($request->has('start_date')) {
        $query->where('trip_start', '>=', $request->start_date);
    }
    if ($request->has('end_date')) {
        $query->where('trip_end', '<=', $request->end_date);
    }

    $reports = $query->orderBy('trip_start', 'desc')->paginate(30);

    return response()->json([
        'data' => $reports->items(),
        'meta' => [
            'total' => $reports->total(),
            'per_page' => $reports->perPage(),
            'current_page' => $reports->currentPage(),
        ],
    ]);
}
```

### Postconditions
- Fuel report stored in database
- Available via API for dashboard display

### Error Handling
- **Insufficient GPS Data**: Skip calculation, log info
- **Missing Consumption Rates**: Use default values, log warning
- **Database Error**: Retry job 3 times

### Performance Metrics
- **Calculation Time**: < 5 seconds per asset
- **Daily Batch**: Process 10,000 assets in < 1 hour

---

## Flow 3: Subscription Expiry & "Blurry Screen" Protocol

### Actors
- Laravel Scheduler
- Subscription Service
- Notification Service
- Middleware (Subscription Check)
- Next.js Frontend

### Preconditions
- User has an active or expiring subscription

### Flow Steps

#### Step 1: Daily Expiry Check (Cron)
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Check expiring subscriptions daily at midnight
    $schedule->job(new CheckExpiringSubscriptions)
             ->dailyAt('00:00')
             ->timezone('Africa/Lagos');
}
```

#### Step 2: Identify Expiring Subscriptions
```php
// app/Jobs/CheckExpiringSubscriptions.php
public function handle()
{
    $today = now()->toDateString();

    // 1. Send pre-expiration alerts (7 days before)
    $subscriptionsIn7Days = Subscription::where('status', 'active')
        ->whereDate('end_date', now()->addDays(7)->toDateString())
        ->get();

    foreach ($subscriptionsIn7Days as $subscription) {
        SendSubscriptionExpiryAlert::dispatch($subscription, 7);
    }

    // 2. Send pre-expiration alerts (3 days before)
    $subscriptionsIn3Days = Subscription::where('status', 'active')
        ->whereDate('end_date', now()->addDays(3)->toDateString())
        ->get();

    foreach ($subscriptionsIn3Days as $subscription) {
        SendSubscriptionExpiryAlert::dispatch($subscription, 3);
    }

    // 3. Mark expired subscriptions
    $expiredCount = Subscription::where('status', 'active')
        ->whereDate('end_date', '<', $today)
        ->update(['status' => 'expired']);

    // 4. Send post-expiration alerts
    $expiredSubscriptions = Subscription::where('status', 'expired')
        ->whereDate('end_date', '>=', now()->subDays(30)->toDateString())
        ->get();

    foreach ($expiredSubscriptions as $subscription) {
        SendSubscriptionExpiredAlert::dispatch($subscription);
    }

    Log::info('Subscription expiry check completed', [
        'expired_count' => $expiredCount,
        'alerts_sent' => $subscriptionsIn7Days->count() + $subscriptionsIn3Days->count(),
    ]);
}
```

#### Step 3: Send Notification
```php
// app/Jobs/SendSubscriptionExpiryAlert.php
public function handle()
{
    $user = $this->subscription->user;
    $asset = $this->subscription->asset;

    $message = $this->daysUntilExpiry > 0
        ? "Your subscription for {$asset->license_plate} expires in {$this->daysUntilExpiry} days. Renew now to avoid service interruption."
        : "Your subscription for {$asset->license_plate} has expired. Renew now to restore full access.";

    // 1. Email Notification
    Mail::to($user->email)->send(new SubscriptionExpiryMail($this->subscription, $this->daysUntilExpiry));

    // 2. SMS Notification
    if ($user->phone) {
        SMS::send($user->phone, $message);
    }

    // 3. In-app Notification
    Notification::create([
        'user_id' => $user->id,
        'type' => 'subscription_expiry',
        'title' => 'Subscription Expiring Soon',
        'message' => $message,
        'sent_via' => 'in_app',
        'sent_at' => now(),
    ]);

    // 4. WebSocket Push
    broadcast(new SubscriptionAlert($user, $message));
}
```

#### Step 4: Middleware Enforces "Blurry Screen"
```php
// app/Http/Middleware/CheckSubscriptionStatus.php
public function handle(Request $request, Closure $next)
{
    // Skip for public routes
    if ($request->is('api/auth/*')) {
        return $next($request);
    }

    $user = $request->user();

    // Super Admin bypass
    if ($user->role === 'super_admin') {
        return $next($request);
    }

    // Check if accessing asset data
    if ($request->route('asset')) {
        $asset = $request->route('asset');
        $subscription = Subscription::where('asset_id', $asset->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$subscription || $subscription->status === 'expired') {
            // Return degraded data (blurry screen protocol)
            return response()->json([
                'status' => 'subscription_expired',
                'message' => 'Your subscription has expired. Renew to restore full access.',
                'data' => [
                    'asset_id' => $asset->id,
                    'status' => $asset->status, // Only show active/inactive
                    'last_ping_at' => $asset->last_ping_at,
                    // Hide precise location
                    'location' => null,
                    'speed' => null,
                ],
                'renew_url' => route('subscriptions.renew', $subscription->id),
            ], 402); // 402 Payment Required
        }
    }

    return $next($request);
}
```

#### Step 5: Frontend Applies Blur Effect
```typescript
// frontend/components/MapView.tsx
import { useEffect, useState } from 'react';
import { useAssetLocation } from '@/hooks/useAssetLocation';

export default function MapView({ assetId }: { assetId: number }) {
  const { data, error } = useAssetLocation(assetId);
  const [isBlurred, setIsBlurred] = useState(false);

  useEffect(() => {
    if (error?.status === 402) {
      setIsBlurred(true);
    }
  }, [error]);

  if (isBlurred) {
    return (
      <div className="relative">
        {/* Blurred Map */}
        <div className="filter blur-lg pointer-events-none">
          <MapContainer center={[0, 0]} zoom={2}>
            {/* Show only status dot, no precise location */}
            <Marker position={[0, 0]} icon={getStatusIcon(data?.status)} />
          </MapContainer>
        </div>

        {/* Overlay */}
        <div className="absolute inset-0 flex items-center justify-center bg-black/50">
          <div className="bg-white p-8 rounded-lg shadow-xl text-center max-w-md">
            <AlertTriangle className="w-16 h-16 text-red-500 mx-auto mb-4" />
            <h2 className="text-2xl font-bold mb-2">Subscription Expired</h2>
            <p className="text-gray-600 mb-6">
              Your asset is <span className="font-semibold text-green-500">ACTIVE</span> and moving, 
              but you cannot see its location until you renew your subscription.
            </p>
            <button
              onClick={() => window.location.href = '/subscriptions/renew'}
              className="bg-red-500 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-600"
            >
              Renew Now
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <MapContainer center={[data.latitude, data.longitude]} zoom={15}>
      <Marker position={[data.latitude, data.longitude]} />
    </MapContainer>
  );
}
```

### Postconditions
- Expired subscriptions marked in database
- Notifications sent via email, SMS, in-app
- API returns degraded data for expired subscriptions
- Frontend displays blurred map with renewal prompt

### Error Handling
- **Email Failure**: Log error, continue with SMS
- **SMS Failure**: Log error, continue with in-app notification
- **Database Error**: Retry job 3 times

### Performance Metrics
- **Expiry Check**: < 10 seconds for 10,000 subscriptions
- **Notification Delivery**: < 5 seconds per user

---

## Flow 4: Geofence Breach Detection

### Actors
- GPS Ingestion Service
- Geofence Service
- Notification Service
- WebSocket Broadcaster

### Preconditions
- Asset has active geofences
- GPS data received

### Flow Steps

#### Step 1: Listen to GPS Data Event
```php
// app/Listeners/CheckGeofenceBreach.php
class CheckGeofenceBreach
{
    public function handle(GpsDataReceived $event)
    {
        $asset = $event->asset;
        $gpsLog = $event->gpsLog;

        // Fetch active geofences for asset's organization
        $geofences = Geofence::where('organization_id', $asset->organization_id)
            ->where('is_active', true)
            ->get();

        foreach ($geofences as $geofence) {
            $this->checkBreach($asset, $gpsLog, $geofence);
        }
    }

    private function checkBreach(Asset $asset, GpsLog $gpsLog, Geofence $geofence)
    {
        $isInside = $this->isPointInGeofence(
            $gpsLog->latitude,
            $gpsLog->longitude,
            $geofence
        );

        $wasInside = Cache::get("asset:{$asset->id}:geofence:{$geofence->id}:inside", false);

        // Entry breach
        if ($isInside && !$wasInside && $geofence->alert_on_entry) {
            $this->logBreach($asset, $geofence, $gpsLog, 'entry');
        }

        // Exit breach
        if (!$isInside && $wasInside && $geofence->alert_on_exit) {
            $this->logBreach($asset, $geofence, $gpsLog, 'exit');
        }

        // Curfew breach
        if ($isInside && $this->isCurfewTime($geofence)) {
            $this->logBreach($asset, $geofence, $gpsLog, 'curfew');
        }

        // Update cache
        Cache::put("asset:{$asset->id}:geofence:{$geofence->id}:inside", $isInside, now()->addHours(24));
    }

    private function isPointInGeofence(float $lat, float $lng, Geofence $geofence): bool
    {
        if ($geofence->type === 'circle') {
            $center = json_decode($geofence->coordinates, true);
            $distance = $this->haversineDistance(
                $lat,
                $lng,
                $center['lat'],
                $center['lng']
            );
            return $distance * 1000 <= $geofence->radius_meters; // Convert km to meters
        }

        // Polygon: Use PostGIS
        return DB::selectOne(
            "SELECT ST_Contains(geometry, ST_SetSRID(ST_MakePoint(?, ?), 4326)) as inside FROM geofences WHERE id = ?",
            [$lng, $lat, $geofence->id]
        )->inside;
    }

    private function isCurfewTime(Geofence $geofence): bool
    {
        if (!$geofence->curfew_start || !$geofence->curfew_end) {
            return false;
        }

        $now = now()->format('H:i:s');
        return $now >= $geofence->curfew_start && $now <= $geofence->curfew_end;
    }

    private function logBreach(Asset $asset, Geofence $geofence, GpsLog $gpsLog, string $type)
    {
        GeofenceBreach::create([
            'asset_id' => $asset->id,
            'geofence_id' => $geofence->id,
            'breach_type' => $type,
            'latitude' => $gpsLog->latitude,
            'longitude' => $gpsLog->longitude,
            'timestamp' => $gpsLog->timestamp,
        ]);

        // Send notification
        SendGeofenceAlert::dispatch($asset, $geofence, $type);

        // Broadcast to WebSocket
        broadcast(new GeofenceBreached($asset, $geofence, $type));
    }
}
```

### Postconditions
- Breach logged in database
- Notification sent to asset owner
- Real-time alert broadcasted

### Error Handling
- **PostGIS Error**: Fall back to ray-casting algorithm
- **Notification Failure**: Log error, continue processing

### Performance Metrics
- **Detection Latency**: < 30 seconds from GPS receipt
- **Throughput**: 1,000 breach checks/second

---

## Flow 5: Remote Shutdown with 2-Step Verification

### Actors
- Super Admin / Office Admin
- Laravel API
- Chinese Partner API
- Audit Log Service

### Preconditions
- User has permission to shutdown asset
- Asset is online

### Flow Steps

#### Step 1: Frontend Initiates Shutdown
```typescript
// frontend/components/RemoteShutdownButton.tsx
async function handleShutdown() {
  // Step 1: Request confirmation code
  const { data } = await axios.post(`/api/assets/${assetId}/remote-shutdown/request`);
  
  // Step 2: Show confirmation dialog
  const code = prompt(`Enter confirmation code to shutdown ${assetName}: ${data.code}`);
  
  if (code !== data.code) {
    alert('Invalid confirmation code');
    return;
  }

  // Step 3: Execute shutdown
  await axios.post(`/api/assets/${assetId}/remote-shutdown/execute`, { code });
  
  alert('Shutdown command sent successfully');
}
```

#### Step 2: Backend Generates Confirmation Code
```php
// app/Http/Controllers/Api/RemoteShutdownController.php
public function request(Asset $asset)
{
    $this->authorize('shutdown', $asset);

    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    Cache::put("shutdown:{$asset->id}:code", $code, now()->addMinutes(5));

    return response()->json([
        'code' => $code,
        'expires_at' => now()->addMinutes(5)->toIso8601String(),
    ]);
}
```

#### Step 3: Backend Executes Shutdown
```php
public function execute(Request $request, Asset $asset)
{
    $this->authorize('shutdown', $asset);

    $request->validate(['code' => 'required|string|size:6']);

    $cachedCode = Cache::get("shutdown:{$asset->id}:code");

    if ($request->code !== $cachedCode) {
        return response()->json(['error' => 'Invalid confirmation code'], 400);
    }

    // Call Chinese Partner API
    $response = Http::post(config('services.gps.api_url') . '/shutdown', [
        'device_id' => $asset->equipment_id,
        'command' => 'SHUTDOWN',
    ]);

    $command = RemoteCommand::create([
        'asset_id' => $asset->id,
        'user_id' => auth()->id(),
        'command_type' => 'shutdown',
        'status' => $response->successful() ? 'sent' : 'failed',
        'api_response' => $response->json(),
        'executed_at' => now(),
    ]);

    // Audit log
    AuditLog::create([
        'user_id' => auth()->id(),
        'action' => 'remote_shutdown',
        'entity_type' => 'Asset',
        'entity_id' => $asset->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    // Notification
    SendRemoteShutdownAlert::dispatch($asset, auth()->user());

    Cache::forget("shutdown:{$asset->id}:code");

    return response()->json(['status' => 'success', 'command_id' => $command->id]);
}
```

### Postconditions
- Shutdown command sent to hardware
- Command logged in database
- Audit log created
- Notification sent

### Error Handling
- **Invalid Code**: Return 400 error
- **API Failure**: Mark command as failed, retry
- **Timeout**: Mark as pending, check status later

---

**Document Version**: 1.0  
**Last Updated**: 2024-01-21  
**Total Flows**: 5
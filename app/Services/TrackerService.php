<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrackerService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.tracker.tracker_base_url');
        $this->username = config('services.tracker.tracker_username');
        $this->password = config('services.tracker.tracker_tracker_password');
    }

    /**
     * Get login token (cached for 23 hours)
     */

    public function getToken(): array
    {
        return Cache::remember('china_tracker_token', now()->addHours(23), function () {

            $response = Http::post($this->baseUrl . '?action=login', [
                "type" => "USER",
                "from" => "WEB",
                "username" => $this->username,
                "password" => md5($this->password),
                "browser" => "Chrome/104.0.0.0"
            ]);

            $data = $response->json();

            Log::info('Tracker login response', ['response' => $data]);


            if (($data['status'] ?? -1) !== 0) {
                throw new \Exception($data['cause'] ?? 'China tracker login failed');
            }

            return [
                'token' => $data['token'],
                'serverid' => $data['serverid'] ?? 0
            ];
        });
    }

    /**
     * Add Activate A device
     */
    public function addDevice(string $imei, string $deviceName): array
    {
        $auth = $this->getToken();

        $url = "{$this->baseUrl}?action=adddevice&token={$auth['token']}";

        Log::info('Tracker AddDevice Request', [
            'url' => $url,
            'payload' => [
                "deviceid" => $imei,
                "devicename" => $deviceName,
            ]
        ]);

        $response = Http::asForm()->post($url, [
            "deviceid" => $imei,
            "devicename" => $deviceName,
            "devicetype" => 1,
            "creator" => $this->username,
            "groupid" => 0,
            "calmileageway" => 0,
            "deviceenable" => 1,
            "loginenable" => 1,
            "timezone" => 8
        ]);

        if (!$response->successful()) {

            Log::warning('Tracker AddDevice HTTP failed', [
                'status_code' => $response->status(),
                'body' => $response->body()
            ]);

            throw new \Exception('Tracker HTTP request failed');
        }

        $data = $response->json();

        Log::info('Tracker AddDevice Response', [
            'status_code' => $response->status(),
            'response_body' => $data
        ]);

        $status = $data['status'] ?? null;

        if ($status === 0) {
            return $data;
        }

        if ($status === 1) {
            return $data;
        }

        throw new \Exception($data['cause'] ?? 'Failed to add device');
    }

    // TRACKING
    public function getLastPosition(array $deviceIds)
    {
        return $this->request('lastposition', [
            "username" => $this->username,
            "deviceids" => implode(',', $deviceIds),
            "serverid" => 0,
        ]);
    }

    // GEOFENCING RESTRICTION
    public function addGeofence(string $deviceId, float $lat, float $lon, int $radius)
    {
        $deviceId = str_replace('IMEI', '', $deviceId); // remove IMEI prefix

        return $this->request('addgeorecord', [
            "deviceid" => $deviceId,
            "type" => 1,
            "lat1" => $lat,
            "lon1" => $lon,
            "radius1" => $radius
        ]);
    }

    public function lockVehicle(string $deviceId, int $deviceType = 1)
    {
        // 808 device
        if ($deviceType == 1) {
            return $this->request('sendcmd', [
                "deviceid"   => $deviceId,
                "devicetype" => 1,
                "cmdcode"    => "TYPE_SERVER_LOCK_CAR",
                "params"     => [],
                "cmdpwd"     => "zhuyi"
            ]);
        }

        // GT06 device
        // return $this->request('sendcmd', [
        //     "deviceid" => $deviceId,
        //     "cmdcode"  => "TYPE_SERVER_SET_RELAY_OIL",
        //     "params"   => ["1"], // 1 = cut oil, 0 = restore
        //     "cmdpwd"   => "zhuyi"
        // ]);
        return $this->request('sendcmd', [
            "deviceid"   => $deviceId,
            "devicetype" => 26, // GT06 usually 26 (confirm from deviceinfo)
            "cmdcode"    => "TYPE_SERVER_SET_RELAY_OIL",
            "params"     => ["1"],
            "cmdpwd"     => "zhuyi"
        ]);
    }

    public function unlockVehicle(string $deviceId, int $deviceType = 1)
    {
        $deviceId = preg_replace('/\D/', '', $deviceId);

        if ($deviceType == 1) {
            return $this->request('sendcmd', [
                "deviceid"   => $deviceId,
                "devicetype" => 1,
                "cmdcode"    => "TYPE_SERVER_UNLOCK_CAR",
                "params"     => [],
                "cmdpwd"     => "zhuyi"
            ]);
        }

        return $this->request('sendcmd', [
            "deviceid"   => $deviceId,
            "devicetype" => 26,
            "cmdcode"    => "TYPE_SERVER_SET_RELAY_OIL",
            "params"     => ["0"], // 0 = restore fuel
            "cmdpwd"     => "zhuyi"
        ]);
    }


    // GET VEHICLE DETAILS
    public function getMileageDetail(string $deviceId, string $startDay, string $endDay, int $offset = 8)
    {
        return $this->request('reportmileagedetail', [
            "deviceid" => $deviceId,
            "startday" => $startDay,
            "endday"   => $endDay,
            "offset"   => $offset
        ]);
    }


    public function generateShareUrl($deviceId, $minutes)
    {
        return $this->request('gensharetrackurl', [
            "deviceid" => $deviceId,
            "interval" => $minutes
        ]);
    }

    private function request(string $action, array $payload = [])
    {
        $auth = $this->getToken();

        $url = $this->baseUrl
            . '?action=' . $action
            . '&token=' . $auth['token'];

        Log::info('Tracker Request', [
            'url' => $url,
            'payload' => $payload
        ]);

        $response = Http::asForm()->post($url, $payload);

        Log::info('Tracker Raw Response', [
            'status_code' => $response->status(),
            'body' => $response->body()
        ]);

        return $response->json();
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TrackerService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.tracker.tracker_base_url');
        $this->username = config('services.tracker.tracker_username');
        $this->password = config('services.tracker.tracker_password');
    }

    public function login()
    {
        if (Cache::has('tracker_token')) {
            return Cache::get('tracker_token');
        }

        $response = Http::post($this->baseUrl . '?action=login', [
            "type" => "USER",
            "from" => "WEB",
            "username" => $this->username,
            "password" => md5($this->password),
            "browser" => "Laravel"
        ]);

        $data = $response->json();

        if ($data['status'] != 0) {
            throw new \Exception('Tracker login failed: ' . $data['cause']);
        }

        Cache::put('tracker_token', $data['token'], now()->addHours(23));
        Cache::put('tracker_server_id', $data['servers'], now()->addHours(23));

        return $data['token'];
    }

    public function getLastPosition(array $deviceIds, $lastQueryTime = 0)
    {
        return $this->request('lastposition', [
            "username" => $this->username,
            "deviceids" => $deviceIds,
            "lastquerypositiontime" => $lastQueryTime
        ]);
    }

    public function lockVehicle(string $deviceId, int $deviceType)
    {
        return $this->request('sendcmd', [
            "deviceid" => $deviceId,
            "devicetype" => $deviceType,
            "cmdcode" => "TYPE_SERVER_LOCK_CAR",
            "params" => [],
            "cmdpwd" => "zhuyi"
        ]);
    }


    public function addGeofence($deviceId, $lat, $lon, $radius)
    {
        return $this->request('addgeorecord', [
            "deviceid" => $deviceId,
            "type" => 1,
            "lat1" => $lat,
            "lon1" => $lon,
            "radius1" => $radius
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
        $token = $this->login();

        $response = Http::post(
            $this->baseUrl . '?action=' . $action . '&token=' . $token,
            $payload
        );

        $data = $response->json();

        //HANDLE TOKEN EXPIRED
        if (isset($data['status']) && $data['status'] == 9903) {

            Cache::forget('tracker_token');

            // retry once
            $token = $this->login();

            $response = Http::post(
                $this->baseUrl . '?action=' . $action . '&token=' . $token,
                $payload
            );

            return $response->json();
        }

        return $data;
    }
}

<?php

namespace App\Services;

use Exception;
use ZipArchive;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use GuzzleHttp\Psr7\Utils;

class SmileIdService
{
    protected string $baseUrl;
    protected string $partnerId;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.smile_identity.base_url', 'https://api.smileidentity.com/v1');
        $this->partnerId = config('services.smile_identity.partner_id');
        $this->apiKey = config('services.smile_identity.api_key');
    }

    protected function generateAuthData(): array
    {
        $timestamp = now()->toIso8601String();
        $signature = base64_encode(hash_hmac('sha256', $timestamp . $this->partnerId . 'sid_request', $this->apiKey, true));
        return compact('signature', 'timestamp');
    }

    public function verifyDriverLicenseDocument(User $user, array $data): array
    {
        $jobId = (string) Str::uuid();
        $auth = $this->generateAuthData();

        $uploadPayload = [
            "source_sdk" => "rest_api",
            "source_sdk_version" => "1.0.0",
            "file_name" => "verification.zip",
            "smile_client_id" => $this->partnerId,
            "signature" => $auth['signature'],
            "timestamp" => $auth['timestamp'],
            "partner_params" => [
                "user_id" => (string) $user->id,
                "job_id" => $jobId,
                "job_type" => 6
            ],
            "callback_url" => config('services.smile_identity.callback_url'),
            "model_parameters" => new \stdClass(),
        ];

        $uploadResponse = Http::post("{$this->baseUrl}/upload", $uploadPayload);

        if (! $uploadResponse->ok() || empty($uploadResponse['upload_url'])) {
            Log::error('Smile ID upload URL generation failed', ['response' => $uploadResponse->json()]);
            throw new Exception("Smile ID verification failed: Unable to generate upload URL.");
        }

        $uploadUrl = $uploadResponse['upload_url'];

        $zipFile = $this->createSmileZip(
            $user,
            $data['driver_license_front'],
            $data['selfie_image'],
            $data['driver_license_number']
        );

        try {
            $putResponse = Http::withBody(file_get_contents($zipFile), 'application/zip')->put($uploadUrl);

            if (! $putResponse->successful()) {
                throw new Exception("Smile ID upload failed: " . $putResponse->body());
            }

            sleep(3); // Ideally queue this part in real production

            $statusPayload = [
                "signature" => $auth['signature'],
                "timestamp" => $auth['timestamp'],
                "user_id" => (string) $user->id,
                "job_id" => $jobId,
                "partner_id" => $this->partnerId,
                "image_links" => true,
                "history" => false,
            ];

            $statusResponse = Http::post("{$this->baseUrl}/job_status", $statusPayload);

            if (! $statusResponse->ok()) {
                throw new Exception("Smile ID job status check failed: " . $statusResponse->body());
            }

            return $statusResponse->json();
        } finally {
            // Always clean up
            if (file_exists($zipFile)) {
                unlink($zipFile);
            }
        }
    }

    protected function createSmileZip(User $user, $licenseImage, $selfieImage, string $licenseNumber): string
    {
        $zip = new \ZipArchive();
        $zipPath = storage_path('app/smile/verification_' . Str::uuid() . '.zip');

        if (! file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot create Smile ID zip archive.");
        }

        $zip->addFromString('id_card.jpeg', file_get_contents($licenseImage->getRealPath()));
        $zip->addFromString('selfie.jpeg', file_get_contents($selfieImage->getRealPath()));

        [$firstName, $lastName] = $this->splitFullName($user->name);

        $infoJson = [
            "package_information" => [
                "api_version" => "1.0.0",
                "sdk" => "rest_api",
                "sdk_version" => "1.0.0"
            ],
            "id_info" => [
                "id_type" => "DRIVERS_LICENSE",
                "country" => "NG",
                "id_number" => $licenseNumber,
                "first_name" => $firstName,
                "last_name" => $lastName
            ],
            "images" => [
                ["image_type_id" => "1", "file_name" => "id_card.jpeg"],
                ["image_type_id" => "2", "file_name" => "selfie.jpeg"],
            ]
        ];

        $zip->addFromString('info.json', json_encode($infoJson));
        $zip->close();

        return $zipPath;
    }

    protected function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? ($parts[2] ?? $firstName);
        return [$firstName, $lastName];
    }


    public function generateAuthSignage()
    {
        $api_key = config('services.smile_identity.api_key');
        $partner_id = config('services.smile_identity.partner_id');

        $timestamp = now()->toIso8601String();

        $data = $timestamp . $partner_id . "sid_request";
        $hash = hash_hmac('sha256', $data, $api_key, true);

        $signature = base64_encode($hash);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
            'partner_id' => $partner_id
        ];
    }

    public function submitNin($user, $nin, array $extra = [])
    {
        $auth = $this->generateAuthSignage();
        $partner_id = $auth['partner_id'];
        $signature = $auth['signature'];
        $timestamp = $auth['timestamp'];

        $nameParts = explode(' ', trim($user->name));
        $firstName = $nameParts[0] ?? '';
        $lastName  = end($nameParts) ?? '';
        $middleName = count($nameParts) > 2 ? $nameParts[1] : null;

        $dob = $extra['date_of_birth'] ?? null;
        $gender = $extra['gender'] ?? null;

        $payload = [
            "callback_url" => "example.webhook.com",
            "country" => "NG",
            "id_type" => "NIN_SLIP",
            "id_number" => $nin,

            "first_name" => $firstName,
            "middle_name" => $middleName,
            "last_name" => $lastName,
            "date_of_birth" => $dob,
            "gender" => $gender ? ucfirst(substr($gender, 0, 1)) : null,

            "partner_id" => $partner_id,
            "partner_params" => [
                "job_id" => (string) $user->id,
                "user_id" => (string) $user->id,
            ],
            "phone_number" => $user->phone,
            "signature" => $signature,
            "source_sdk" => "rest_api",
            "source_sdk_version" => "1.0.0",
            "timestamp" => $timestamp,
        ];

        $url = config('services.smile_identity.sid_server') === 'production'
            ? 'https://api.smileidentity.com/v1/id_verification'
            : 'https://testapi.smileidentity.com/v1/id_verification';

        $response = Http::withoutVerifying()->post($url, $payload);
        $result = $response->json();

        $successCodes = ['0810', '1012'];

        return [
            'success' => isset($result['ResultCode']) && in_array($result['ResultCode'], $successCodes),
            'raw' => $result,
            'details' => [
                'full_name' => trim($result['Result']['FullName'] ?? ''),
                'dob' => isset($result['Result']['DateOfBirth'])
                    ? date('Y-m-d', strtotime($result['Result']['DateOfBirth']))
                    : ($result['DOB'] ?? null),
                'gender' => strtolower($result['Result']['Gender'] ?? ''),
            ],
        ];
    }


    public function submitBusinessCAC(array $payload)
    {
        $auth = $this->generateAuthData();
        $jobId = (string) \Illuminate\Support\Str::uuid();

        // Strip RC / BN / IT
        $rawCac = preg_replace('/^(RC|BN|IT)/i', '', trim($payload['cac_number'] ?? ''));
        if (!preg_match('/^0{7}$|^(?![0]+$)[0-9]{1,8}$/', $rawCac)) {
            throw new \Exception('Invalid CAC number format');
        }

        $businessType = strtolower($payload['business_type'] ?? '');
        if (!in_array($businessType, ['co', 'bn', 'it'])) {
            throw new \Exception('Unsupported business type');
        }

        $body = [
            'partner_id' => config('services.smile_identity.partner_id'),
            'source_sdk' => 'rest_api',
            'source_sdk_version' => '1.0.0',
            'signature' => $auth['signature'],
            'timestamp' => $auth['timestamp'],
            'country' => 'NG',

            // 🔥 SYNC requires this
            'id_type' => 'BASIC_BUSINESS_REGISTRATION',
            'id_number' => $rawCac,
            'business_type' => $businessType,

            'partner_params' => [
                'job_type' => 7,
                'job_id' => $jobId,
                'user_id' => (string) $payload['user_id'],
            ],
        ];

        // 🔥 SWITCH TO SYNC ENDPOINT
        $url = config('services.smile_identity.base_url') . '/business_verification';

        $client = new \GuzzleHttp\Client(['verify' => false]);

        $response = $client->post($url, [
            'json' => $body,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $json = json_decode((string) $response->getBody(), true);

        if (!($json['success'] ?? false)) {
            throw new \Exception('Smile ID CAC verification failed: ' . json_encode($json));
        }

        // ✅ SUCCESS — instant result
        return [
            'job_id' => $jobId,
            'result_code' => $json['ResultCode'] ?? null,
            'company_info' => $json['company_information'] ?? null,
            'raw' => $json,
        ];
    }



    // public function submitBusinessCAC(array $payload)
    // {
    //     $auth = $this->generateAuthData();
    //     $partnerId = $this->partnerId;
    //     $signature = $auth['signature'];
    //     $timestamp = $auth['timestamp'];
    //     $jobId = (string) \Illuminate\Support\Str::uuid();

    //     $rawCac = preg_replace('/^(RC|BN|IT)/', '', strtoupper(trim($payload['cac_number'] ?? '')));
    //     if (!ctype_digit($rawCac)) throw new \Exception('Invalid CAC number format');

    //     $businessType = strtolower($payload['business_type'] ?? '');
    //     if (!in_array($businessType, ['co', 'bn', 'it'])) throw new \Exception('Unsupported business type');

    //     $file = $payload['cac_document'] ?? null;
    //     if (!$file) throw new \Exception('CAC document is required');

    //     $cacDocumentPath = $file instanceof \Illuminate\Http\UploadedFile ? $file->getRealPath() : Storage::disk('public')->path($file);
    //     $cacFileName = $file instanceof \Illuminate\Http\UploadedFile ? $file->getClientOriginalName() : basename($cacDocumentPath);
    //     if (!file_exists($cacDocumentPath) || !is_readable($cacDocumentPath)) throw new \Exception("CAC document file not readable");

    //     // Partner params JSON
    //     $partnerParams = [
    //         'job_type' => 7,
    //         'job_id' => $jobId,
    //         'user_id' => (string)$payload['user_id'],
    //     ];
    //     $partnerParamsJson = json_encode($partnerParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    //     $multipart = [
    //         ['name' => 'cac_document', 'contents' => fopen($cacDocumentPath, 'r'), 'filename' => $cacFileName],
    //         ['name' => 'callback_url', 'contents' => config('services.smile_identity.callback_url')],
    //         ['name' => 'country', 'contents' => 'NG'],
    //         ['name' => 'id_type', 'contents' => 'BUSINESS_REGISTRATION'],
    //         ['name' => 'id_number', 'contents' => $rawCac],
    //         ['name' => 'business_type', 'contents' => $businessType],
    //         ['name' => 'partner_id', 'contents' => $partnerId],
    //         // ✅ Send partner_params as a raw stream
    //         ['name' => 'partner_params', 'contents' => Utils::streamFor($partnerParamsJson)],
    //         ['name' => 'signature', 'contents' => $signature],
    //         ['name' => 'source_sdk', 'contents' => 'rest_api'],
    //         ['name' => 'source_sdk_version', 'contents' => '1.0.0'],
    //         ['name' => 'timestamp', 'contents' => $timestamp],
    //     ];

    //     $url = config('services.smile_identity.sid_server') === 'sandbox'
    //         ? 'https://testapi.smileidentity.com/v1/async_business_verification'
    //         : 'https://api.smileidentity.com/v1/async_business_verification';

    //     $client = new \GuzzleHttp\Client(['verify' => false]);

    //     try {
    //         $response = $client->request('POST', $url, [
    //             'multipart' => $multipart,
    //             'headers' => ['Accept' => 'application/json'],
    //             'timeout' => 30,
    //         ]);

    //         $body = (string)$response->getBody();
    //         $json = json_decode($body, true);

    //         if (!$json || !$json['success'] ?? false) {
    //             throw new \Exception("Smile ID CAC verification request failed: $body");
    //         }

    //         return [
    //             'job_id' => $jobId,
    //             'response' => $json,
    //         ];
    //     } catch (\GuzzleHttp\Exception\RequestException $e) {
    //         throw new \Exception('Smile ID CAC verification request failed: ' . $e->getMessage());
    //     }
    // }











    // public function submitBusinessCAC(array $payload)
    // {
    //     $auth = $this->generateAuthData();

    //     $partnerId = $this->partnerId;
    //     $signature = $auth['signature'];
    //     $timestamp = $auth['timestamp'];

    //     $jobId = (string) Str::uuid();

    //     /**
    //      * Normalize CAC number (VERY IMPORTANT)
    //      */
    //     $rawCac = strtoupper(trim($payload['cac_number']));

    //     // Strip CAC prefixes if user supplied them (RC, BN, IT)
    //     $rawCac = preg_replace('/^(RC|BN|IT)/', '', $rawCac);

    //     // Ensure CAC number is digits only
    //     if (! ctype_digit($rawCac)) {
    //         throw new \Exception('Invalid CAC number format');
    //     }

    //     /**
    //      *  Normalize business type
    //      * Smile ID only supports: co | bn | it
    //      */
    //     $businessType = strtolower($payload['business_type']);

    //     if (! in_array($businessType, ['co', 'bn', 'it'])) {
    //         throw new \Exception('Unsupported business type');
    //     }

    //     /**
    //      *  Build Smile ID payload (CORRECT FORMAT)
    //      */
    //     $data = [
    //         "callback_url" => (string) config('services.smile_identity.callback_url'),
    //         "country" => "NG",

    //         "id_type" => "BUSINESS_REGISTRATION",
    //         "id_number" => $rawCac,
    //         "business_type" => $businessType,

    //         // Smile auth
    //         "partner_id" => $partnerId,
    //         "partner_params" => [
    //             "job_type" => 7,
    //             "job_id" => $jobId,
    //             "user_id" => (string) $payload['user_id'],
    //         ],

    //         "signature" => $signature,
    //         "source_sdk" => "rest_api",
    //         "source_sdk_version" => "1.0.0",
    //         "timestamp" => $timestamp,
    //     ];

    //     /**
    //      *  Select environment (Sandbox vs Production)
    //      */
    //     $url = config('services.smile_identity.sid_server') === 'sandbox'
    //         ? 'https://testapi.smileidentity.com/v1/async_business_verification'
    //         : 'https://api.smileidentity.com/v1/async_business_verification';


    //     /**
    //      *  Send request
    //      */
    //     // $response = Http::withoutVerifying()->post($url, $data);
    //     $response = Http::withoutVerifying()
    //         ->attach(
    //             'cac_document',
    //             file_get_contents($payload['cac_document']->getRealPath()),
    //             $payload['cac_document']->getClientOriginalName()
    //         )
    //         ->post($url, $data);


    //     if (! $response->ok()) {
    //         throw new \Exception(
    //             'Smile ID CAC verification request failed: ' . $response->body()
    //         );
    //     }

    //     return [
    //         'job_id' => $jobId,
    //         'response' => $response->json(),
    //     ];
    // }


    // public function submitBusinessCAC(array $payload)
    // {
    //     $auth = $this->generateAuthData();

    //     $partnerId = $this->partnerId;
    //     $signature = $auth['signature'];
    //     $timestamp = $auth['timestamp'];

    //     $jobId = (string) Str::uuid();

    //     $data = [
    //         "callback_url" => config('services.smile_identity.callback_url'),
    //         "country" => "NG",

    //         // CAC-specific
    //         "id_type" => "BUSINESS_REGISTRATION",
    //         "id_number" => $payload['cac_number'],
    //         "business_type" => $payload['business_type'], // bn | co | it

    //         // Smile auth
    //         "partner_id" => $partnerId,
    //         "partner_params" => [
    //             "job_type" => 7,
    //             "job_id" => $jobId,
    //             "user_id" => (string) $payload['user_id'],
    //         ],
    //         "signature" => $signature,
    //         "source_sdk" => "rest_api",
    //         "source_sdk_version" => "1.0.0",
    //         "timestamp" => $timestamp,
    //     ];

    //     $url = config('services.smile_identity.sid_server') == 0
    //         ? 'https://testapi.smileidentity.com/v1/async_business_verification'
    //         : 'https://api.smileidentity.com/v1/async_business_verification';


    //     $response = Http::withoutVerifying()->post($url, $data);

    //     if (! $response->ok()) {
    //         throw new \Exception(
    //             'Smile ID CAC verification request failed: ' . $response->body()
    //         );
    //     }


    //     return [
    //         'job_id' => $jobId,
    //         'response' => $response->json(),
    //     ];
    // }
}

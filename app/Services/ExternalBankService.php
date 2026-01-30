<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;




class ExternalBankService
{
    protected string $authBaseUrl;
    protected string $baseUrl;
    // protected string $debitUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $merchantUserId;
    protected string $username;
    protected string $password;
    protected string $pin;

    protected Client $client;
    protected string $merchantAccountNumber;


    public function __construct()
    {
        $this->authBaseUrl    = config('services.shanono_bank.auth_base_url');
        $this->baseUrl         = config('services.shanono_bank.base_url');
        // $this->debitUrl       = config('services.shanono_bank_debit_url');
        $this->clientId        = config('services.shanono_bank.client_id');
        $this->clientSecret    = config('services.shanono_bank.client_secret');
        $this->merchantUserId  = config('services.shanono_bank.merchant_user_id');
        $this->username        = config('services.shanono_bank.username');
        $this->password        = config('services.shanono_bank.password');
        $this->pin            = config('services.shanono_bank.transaction_pin');
        $this->merchantAccountNumber = config('services.shanono_bank.account_number');
        $this->client = new Client([
            'verify' => false,
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ],
        ]);
    }

    protected function tokenCacheKey(): string
    {
        return 'shanono_bank_token_' . app()->environment();
    }


    /**
     * Get Bearer Token
     */
    protected function getAccessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addYear(), function () {
            Log::info('Shanono token URL', [
                'url' => $this->authBaseUrl . '/client/token',
            ]);

            $response = Http::asForm()->post(
                $this->authBaseUrl . '/client/token',
                [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]
            );

            Log::info('Shanono token response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if ($response->failed()) {
                throw new \Exception(
                    'Failed to authenticate with Shanono Bank: ' . $response->body()
                );
            }

            $data = $response->json();

            if (!isset($data['data']['access_token'])) {
                throw new \Exception('Invalid token response from Shanono Bank');
            }

            return $data['data']['access_token'];
        });
    }

    protected function getLiveAccessToken(): string
    {
        return Cache::remember('shanono_bank_token', now()->addYear(), function () {
            $tokenUrl = 'https://api.myshanonobank.com/api/client/token';

            // Hardcoded credentials for testing
            $clientId = '019bbe4d-c999-7164-9ecb-9635ae87fd5a';
            $clientSecret = '2ybKtTG9Expu1R6GZjplL3J905gEPdAvVqssVpp7';

            Log::info('Shanono token request URL', ['url' => $tokenUrl]);

            // Use form data, not JSON
            $response = Http::asForm()->post($tokenUrl, [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]);

            Log::info('Shanono token response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if ($response->failed()) {
                throw new \Exception(
                    'Failed to authenticate with Shanono Bank: ' . $response->body()
                );
            }

            $data = $response->json();

            if (!isset($data['data']['access_token'])) {
                throw new \Exception('Invalid token response from Shanono Bank');
            }

            return $data['data']['access_token'];
        });
    }

    /**
     * Shared HTTP client with Bearer token
     */
    protected function http()
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->acceptJson()
            ->asForm()
            ->withToken($this->getAccessToken())
            ->withoutVerifying();
    }

    protected function shanonoTokenHttp()
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->acceptJson()
            ->asForm()
            ->withToken($this->getLiveAccessToken())
            ->withoutVerifying();
    }

    protected function postWithRetry(string $url, array $payload)
    {
        $response = $this->http()->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning('Shanono token expired, refreshing token');

            Cache::forget($this->tokenCacheKey());

            $response = $this->http()->post($url, $payload);
        }

        return $response;
    }

    public function createExternalAccountForUser(User $user): array
    {
        [$firstName, $lastName] = $this->splitName($user->name);

        $payload = [
            'merchant_user_id' => $this->merchantUserId,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'account_name'     => "{$firstName} {$lastName} Wallet",
        ];


        $accountProductId = config('services.shanono_bank.account_product_id');

        if (!empty($accountProductId)) {
            $payload['account_product_id'] = $accountProductId;
        }

        Log::info('Shanono create sub-account payload', $payload);

        // $response = $this->http()->post(
        //     $this->baseUrl . '/loopfreight/sub-account',
        //     $payload
        // );

        $response = $this->postWithRetry(
            $this->baseUrl . '/loopfreight/sub-account',
            $payload
        );


        Log::info('Shanono create sub-account response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Failed to create sub-account: ' . $response->body()
            );
        }

        $body = $response->json();

        if (
            !isset($body['data']) ||
            !isset($body['data']['sub_account'])
        ) {
            Log::error('Invalid Shanono sub-account response', [
                'response' => $body,
            ]);

            throw new \Exception(
                'Invalid response from Shanono Bank while creating sub-account'
            );
        }

        return $body['data']['sub_account'];
    }

    private function splitName(?string $name): array
    {
        $parts = explode(' ', trim($name ?? ''));

        return [
            $parts[0] ?? 'User',
            $parts[1] ?? 'Account',
        ];
    }

    public function getAccountBalance(string $accountNumber): array
    {
        $response = $this->http()->get(
            "{$this->baseUrl}/loopfreight/balance",
            [
                'account_number' => $accountNumber,
                'username'       => $this->username,
                'password'       => $this->password,
            ]
        );

        Log::info('FINAL SHANONO BALANCE REQUEST', [
            'url'            => "{$this->baseUrl}/loopfreight/balance",
            'account_number' => $accountNumber,
            'username'       => $this->username,
        ]);

        Log::info('Shanono get balance response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Failed to fetch Shanono account balance: ' . $response->body()
            );
        }

        $data = $response->json('data');

        return [
            'account_id'        => $data['account_id'] ?? null,
            'available_balance' => (float) ($data['available_balance'] ?? 0),
            'book_balance'      => (float) ($data['book_balance'] ?? 0),
            'can_transfer'      => (bool) ($data['can_transfer'] ?? false),
            'can_receive'       => (bool) ($data['can_receive'] ?? false),
            'status'            => $data['status'] ?? null,
        ];
    }

    public function getAccountBalanceCached(string $accountNumber): array
    {
        return Cache::remember(
            "shanono_balance_{$accountNumber}",
            now()->addSeconds(30),
            fn() => $this->getAccountBalance($accountNumber)
        );
    }

    public function getMerchantBalance(): array
    {
        $balance = $this->getAccountBalanceCached(
            $this->merchantAccountNumber
        );

        if (($balance['status'] ?? null) !== 'active') {
            throw new \Exception('Merchant account is not active');
        }

        return $balance;
    }

    //FOR PRODUCTION ENVIRONMENT STARTS HERE
    public function lifeBankNameEnquiry(string $accountNumber, string $bankCode): array
    {
        $url = "https://api.myshanonobank.com/api/integrations/loopfreight/account-name-enquiry";

        $payload = [
            'account_number' => $accountNumber,
            'bank_code'      => $bankCode,
        ];

        Log::info('Shanono LIVE bank name enquiry request', [
            'url'     => $url,
            'payload' => $payload,
        ]);

        $response = $this->shanonoTokenHttp()->post($url, $payload);

        Log::info('Shanono LIVE bank name enquiry response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->status() === 401) {
            Cache::forget('shanono_bank_token');

            // retry once with fresh token
            $response = $this->shanonoTokenHttp()->post($url, $payload);
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                $body['message'] ?? 'Unable to verify account details'
            );
        }

        return $body['data'];
    }

    public function lifeBankListEnquiry(): array
    {
        $url = 'https://api.myshanonobank.com/api/integrations/loopfreight/banks';

        Log::info('Shanono LIVE bank list request', [
            'url' => $url,
        ]);

        $response = $this->shanonoTokenHttp()->get($url);

        Log::info('Shanono LIVE bank list response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        // If token expired, clear cache and retry once
        if ($response->status() === 401) {
            Cache::forget('shanono_bank_token');

            $response = $this->shanonoTokenHttp()->get($url);
        }

        if (!$response->successful()) {
            throw new \Exception('Unable to reach Shanono bank list service');
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                $body['message'] ?? 'Unable to provide bank list'
            );
        }

        return $body['data'] ?? [];
    }

    // FOR PRODUCTION ENVIRONMENT ENDS HERE

    public function bankNameEnquiry(string $accountNumber, string $bankCode): array
    {
        $url = "{$this->baseUrl}/loopfreight/test-account-enquiry";

        $payload = [
            'username'       => $this->username,
            'password'       => $this->password,
            'account_number' => $accountNumber,
            'bank_code'      => $bankCode,
        ];

        Log::info('Shanono bank name enquiry request', [
            'url'     => $url,
            'payload' => [
                'username'       => $this->username,
                'account_number' => $accountNumber,
                'bank_code'      => $bankCode,
            ],
        ]);

        $response = $this->http()->post($url, $payload);

        Log::info('Shanono bank name enquiry response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Failed to verify bank account: ' . $response->body()
            );
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                $body['message'] ?? 'Unable to verify account details'
            );
        }

        return $body['data'];
    }

    public function debitInternalAccount(string $accountId, float $amount, string $reference, string $narration): array
    {
        $payload = [
            'username'   => $this->username,
            'password'   => $this->password,
            'account_id' => $accountId, // sub-account UUID (external_reference)
            'amount'     => number_format($amount, 2, '.', ''),
            'narration'  => substr($narration, 0, 100),
            'reference'  => $reference,
        ];

        Log::info('Shanono debit request', [
            'url'     => "{$this->baseUrl}/loopfreight/accounts/debit",
            'payload' => $payload,
        ]);

        // $response = $this->http()->post(
        //     "{$this->debitUrl}/loopfreight/accounts/debit",
        //     $payload
        // );

        $response = $this->http()->post(
            "https://core.shanonobank.xyz/api/loopfreight/accounts/debit",
            $payload
        );

        Log::info('Shanono debit response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Shanono debit failed: ' . $response->body()
            );
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                'Shanono debit error: ' . ($body['message'] ?? 'Unknown error')
            );
        }

        return $body['data'];
    }

    public function getAccountTransactions(string $accountNumber, int $page = 1, int $perPage = 20): array
    {
        $response = $this->http()->get(
            "{$this->baseUrl}/loopfreight/transactions",
            [
                'username'       => $this->username,
                'password'       => $this->password,
                'account_number' => $accountNumber,
                'page'           => $page,
                'per_page'       => $perPage,
            ]
        );

        Log::info('Shanono transactions request', [
            'account_number' => $accountNumber,
            'page'           => $page,
        ]);

        Log::info('Shanono transactions response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Failed to fetch transactions: ' . $response->body()
            );
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                'Shanono error: ' . ($body['message'] ?? 'Unknown error')
            );
        }

        return $body['data'];
    }

    public function getMerchantTransactions(int $page = 1, int $perPage = 20): array
    {
        return $this->getAccountTransactions(
            $this->merchantAccountNumber,
            $page,
            $perPage
        );
    }

    public function payoutToBank(array $data): array
    {
        $payload = [
            'username' => $this->username,
            'password' => $this->password,
            'account_id'                 => $data['account_id'],
            'beneficiary_account_name'   => $data['beneficiary_account_name'],
            'beneficiary_account_number' => $data['beneficiary_account_number'],
            'beneficiary_bank_name'      => $data['beneficiary_bank_name'],
            'beneficiary_bank_code'      => $data['beneficiary_bank_code'],
            'amount'                     => number_format($data['amount'], 2, '.', ''),
            'narration'                  => substr($data['narration'], 0, 100),
            'idempotency_key'            => $data['idempotency_key'],
        ];

        $url = "{$this->baseUrl}/loopfreight/payout";

        Log::info('Shanono payout request', [
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = $this->client->post($url, [
                'json'            => $payload,
                'timeout'         => 10, // TOTAL request time
                'connect_timeout' => 5,  // TCP handshake
            ]);
        } catch (ConnectException $e) {
            throw $e;
        } catch (RequestException $e) {
            throw new \RuntimeException(
                'Shanono payout request failed: ' . $e->getMessage()
            );
        }

        $body = json_decode((string) $response->getBody(), true);

        Log::info('Shanono payout response', ['body' => $body]);

        if (($body['status'] ?? null) !== 'success') {
            throw new \RuntimeException(
                'Shanono payout error: ' . ($body['message'] ?? 'Unknown error')
            );
        }

        return $body['data']['transaction'];
    }

    // BILL PAYMENT END POINTS STARTS HERE
    // AIRTIME ACTIONS STARTS HERE
    public function getAirtimeProviders(): array
    {
        return Cache::remember(
            'shanono_airtime_providers',
            now()->addHours(12),
            function () {
                $response = $this->http()->get(
                    "{$this->baseUrl}/loopfreight/airtime-providers"
                );

                if ($response->failed()) {
                    throw new \Exception(
                        'Failed to fetch airtime providers from Shanono'
                    );
                }

                $body = $response->json();

                if (($body['status'] ?? null) !== 'success') {
                    throw new \Exception(
                        'Invalid provider response from Shanono'
                    );
                }

                return $body['data']['providers'] ?? [];
            }
        );
    }
    public function purchaseAirtime(array $data): array
    {
        $payload = [
            'username' => $this->username,
            'password' => $this->password,
            'account_number' => $this->merchantAccountNumber,
            'phoneNumber'    => $data['phone'],
            'disco' => strtoupper($data['provider']),
            'amount' => number_format($data['amount'], 2, '.', ''),
            'pin' => config('services.shanono_bank.transaction_pin'),
        ];


        Log::info('Shanono Airtime purchase request', [
            'payload' => Arr::except($payload, ['pin', 'password']),
        ]);

        $response = $this->http()->post(
            "{$this->baseUrl}/loopfreight/airtime-purchase",
            $payload
        );

        Log::info('Shanono airtime purchase response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Shanono airtime purchase failed: ' . $response->body()
            );
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                $body['message'] ?? 'Airtime purchase error'
            );
        }

        return $body['data'];
    }

    public function checkBillsPayStatus(string $reference): string
    {
        $response = $this->http()->post(
            "{$this->baseUrl}/loopfreight/requery-bill-payment",
            [
                'transaction_reference' => $reference,
            ]
        );

        Log::info('Shanono airtime requery response', [
            'reference' => $reference,
            'status'    => $response->status(),
            'body'      => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to requery airtime transaction');
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception('Invalid requery response');
        }

        return match ($body['data']['current_status'] ?? null) {
            'success' => 'success',
            'failed'  => 'failed',
            default   => 'pending',
        };
    }

    // AIRTIME ACTIONS ENDS HERE

    // DATA PURCHASE ACTIONS STARTS HERE
    public function getDataPlans(?string $provider = null): array
    {
        return Cache::remember(
            'shanono_data_plans_' . ($provider ?? 'all'),
            now()->addHours(6),
            function () use ($provider) {
                $query = [];

                if ($provider) {
                    $query['provider'] = strtoupper($provider);
                }

                $response = $this->http()->get(
                    "{$this->baseUrl}/loopfreight/data-plans",
                    $query
                );

                Log::info('Shanono data plans response', [
                    'provider' => $provider,
                    'status'   => $response->status(),
                    'body'     => $response->json(),
                ]);

                $body = $response->json();

                if ($response->failed()) {
                    throw new \Exception(
                        $body['message'] ?? 'Failed to fetch data plans from Shanono'
                    );
                }

                if (!isset($body['status']) || $body['status'] !== 'success') {
                    throw new \Exception(
                        $body['message'] ?? 'Invalid data plan response'
                    );
                }

                return $body['data']['plans'] ?? [];
            }
        );
    }

    public function purchaseData(array $data): array
    {
        $phone = $data['phone'];
        if (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);
        }

        $payload = [
            'username' => $this->username,
            'password' => $this->password,
            'account_number' => $this->merchantAccountNumber,
            'phoneNumber'  => $phone,
            'disco'        => strtoupper($data['provider']),
            'tariffClass'  => $data['tariff_class'],
            'amount'       => (float) $data['amount'],
            'pin'          => $this->pin,
        ];

        Log::info('Shanono data purchase request', [
            'url'     => "{$this->baseUrl}/loopfreight/vend-data",
            'payload' => $payload,
        ]);

        $response = Http::timeout(30)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson() //JSON as docs require
            ->post("{$this->baseUrl}/loopfreight/vend-data", $payload);

        Log::info('Shanono data purchase response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                $response->json()['message'] ?? 'Data purchase failed'
            );
        }

        if (($response->json()['status'] ?? null) !== 'success') {
            throw new \Exception(
                $response->json()['message'] ?? 'Data purchase error'
            );
        }

        return $response->json()['data'];
    }
    // DATA PURCHASE ACTIONS ENDS HERE


    // ELECTRICITY PURCHASE STARTS HERE
    public function getElectricityProviders(): array
    {
        return Cache::remember(
            'shanono_electricity_providers',
            now()->addHours(12),
            function () {
                $response = $this->http()->get(
                    "{$this->baseUrl}/loopfreight/electricity-discos"
                );

                if ($response->failed()) {
                    throw new \Exception(
                        'Failed to fetch electricity providers from Shanono'
                    );
                }

                $body = $response->json();

                if (($body['status'] ?? null) !== 'success') {
                    throw new \Exception(
                        'Invalid provider response from Shanono'
                    );
                }

                return $body['data']['discos'] ?? [];
            }
        );
    }

    public function purchaseElectricity(array $data): array
    {
        // Normalize phone number
        $phone = $data['phone'];
        if (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);
        }

        $payload = [
            'username' => $this->username,
            'password' => $this->password,
            'account_number' => $this->merchantAccountNumber,
            'meter'    => $data['meter'],
            'disco'    => strtoupper($data['disco']),
            'phone'    => $phone,
            'vendType' => $data['vend_type'] ?? 'PREPAID',
            'amount'   => (float) $data['amount'],
            'pin'      => $this->pin,
        ];

        Log::info('Shanono electricity purchase request', [
            'url'     => "{$this->baseUrl}/loopfreight/vend-electricity",
            'payload' => Arr::except($payload, ['pin']),
        ]);

        $response = Http::timeout(30)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/loopfreight/vend-electricity", $payload);

        Log::info('Shanono electricity purchase response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                $response->json()['message'] ?? 'Electricity purchase failed'
            );
        }

        if (($response->json()['status'] ?? null) !== 'success') {
            throw new \Exception(
                $response->json()['message'] ?? 'Electricity purchase error'
            );
        }

        return $response->json()['data'];
    }
    // ELECTRICITY PURCHASE ENDS HERE


    // CABLETV PURCHASE STARTS HERE
    public function getCableTvProviders(): array
    {
        return Cache::remember(
            'shanono_cabletv_providers',
            now()->addHours(12),
            function () {
                $response = $this->http()->get(
                    "{$this->baseUrl}/loopfreight/cable-tv-providers"
                );

                if ($response->failed()) {
                    throw new \Exception(
                        'Failed to fetch cabletv providers from Shanono'
                    );
                }

                $body = $response->json();

                if (($body['status'] ?? null) !== 'success') {
                    throw new \Exception(
                        'Invalid provider response from Shanono'
                    );
                }

                return $body['data']['providers'] ?? [];
            }
        );
    }
    public function purchaseTvSubscription(array $data): array
    {
        $payload = [
            'decoderNumber' => $data['decoder_number'],
            'disco'         => strtoupper($data['disco']), // DSTV, GOTV, STARTIMES
            'tariffClass'   => $data['tariff_class'],      // DSTV-COMPACT
            'amount'        => (float) $data['amount'],
            'pin'           => $this->pin,
        ];

        Log::info('Shanono TV purchase request', [
            'url'     => "{$this->baseUrl}/loopfreight/vend-tv",
            'payload' => Arr::except($payload, ['pin']),
        ]);

        $response = Http::timeout(30)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/loopfreight/vend-tv", $payload);

        Log::info('Shanono TV purchase response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                $response->json()['message'] ?? 'TV purchase failed'
            );
        }

        if (($response->json()['status'] ?? null) !== 'success') {
            throw new \Exception(
                $response->json()['message'] ?? 'TV purchase error'
            );
        }

        return $response->json()['data'];
    }
    // CABLETV PURCHASE ENDS HERE

    // WEBHOOK CONFIGURATION STARTS HERE
    public function configureWebhook(string $webhookUrl, string $secret): array
    {
        $payload = [
            'username'   => $this->username,
            'password'   => $this->password,
            'webhook_url'    => $webhookUrl,
            'webhook_secret' => $secret,
        ];

        Log::info('Configuring Shanono webhook', $payload);

        $response = $this->http()->post(
            "{$this->baseUrl}/loopfreight/webhook/configure",
            $payload
        );

        Log::info('Shanono webhook config response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception('Failed to configure webhook: ' . $response->body());
        }

        return $response->json();
    }

    public function getWebhookConfig(): array
    {
        $response = $this->http()
            ->withQueryParameters([
                'username' => $this->username,
                'password' => $this->password,
            ])
            ->get("{$this->baseUrl}/loopfreight/webhook/config");

        Log::info('Shanono get webhook config response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Failed to fetch webhook config: ' . $response->body()
            );
        }

        return $response->json();
    }

    // GET BILLS PAYMENT COMMISSIONS
    public function getCommissionTransactions(array $filters = []): array
    {
        $query = array_filter([
            'username'           => $this->username,
            'password'           => $this->password,
            'start_date'         => $filters['start_date'] ?? null, // Y-m-d
            'end_date'           => $filters['end_date'] ?? null,   // Y-m-d
            'bill_payment_type'  => $filters['bill_payment_type'] ?? null,
            'page'               => $filters['page'] ?? 1,
            'per_page'           => $filters['per_page'] ?? 20,
        ]);

        $url = "{$this->baseUrl}/loopfreight/commissions";

        Log::info('Shanono commission transactions request', [
            'url'   => $url,
            'query' => Arr::except($query, ['password']),
        ]);

        $response = $this->http()
            ->withQueryParameters($query)
            ->get($url);

        Log::info('Shanono commission transactions response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if ($response->failed()) {
            throw new \Exception(
                'Failed to fetch commission transactions: ' . $response->body()
            );
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'success') {
            throw new \Exception(
                $body['message'] ?? 'Invalid commission response from Shanono'
            );
        }

        return $body['data'];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\ApiClient\BlockApiClientAction;
use App\Actions\Auth\RegisterDeveloperAction;
use App\DTOs\ApiClient\BlockApiClientDTO;
use App\DTOs\Auth\DeveloperRegisterDTO;
use App\Enums\ProductionAccessRequestStatusEnums;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ProductionAccessRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeveloperAuthController extends Controller
{
    public function __construct(
        protected RegisterDeveloperAction $registerDeveloperAction
    ) {}

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'name' => 'required|string|max:255',

            'email' => 'required|email|unique:users,email',

            'password' => [
                'required',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).+$/'
            ],

            'company_name' => 'required|string|max:255',

            'company_website' => 'nullable|url',

            'phone' => 'nullable|string|max:20',

            'callback_url' => 'nullable|url',

        ]);

        if ($validator->fails()) {

            return failureResponse(
                $validator->errors(),
                422,
                'validation_error'
            );
        }

        $dto = DeveloperRegisterDTO::fromRequest($request);

        $data = $this->registerDeveloperAction->execute($dto);

        return successResponse(
            'Verification code sent successfully.',
            [
                'reference' => $data['reference']
            ]
        );
    }
    public function me(Request $request)
    {
        $currentClient = $request->attributes->get('api_client');

        $clients = ApiClient::with(['webhook', 'customer'])
            ->where('customer_id', $currentClient->customer_id)
            ->get();

        return successResponse(
            'Developer profile',
            [
                'name'  => $currentClient->customer->name,
                'email' => $currentClient->customer->email,

                'api_clients' => $clients->map(function ($client) {
                    return [
                        'client_id'        => $client->id,
                        'environment'      => $client->environment,
                        'api_key'          => $client->api_key,
                        'company_name'     => $client->company_name,
                        'company_website'  => $client->company_website,
                        'callback_url'     => $client->callback_url,
                        'webhook'          => optional($client->webhook)->webhook_url,
                        'webhook_secret'   => optional($client->webhook)->webhook_secret,
                    ];
                })->values(),
            ]
        );
    }

    public function rotateApiKey(Request $request)
    {
        $client = $request
            ->attributes
            ->get('api_client');

        $client->update([

            'api_key' => ApiClient::generateApiKey(),

        ]);

        return successResponse(
            'API key rotated successfully',
            [
                'api_key' => $client->api_key
            ]
        );
    }

    public function regenerateWebhookSecret(Request $request)
    {
        $client = $request
            ->attributes
            ->get('api_client');

        $webhook = $client->webhook;

        if (! $webhook) {
            return failureResponse(
                'Webhook not configured.',
                404
            );
        }

        $webhook->update([

            'webhook_secret' => Str::random(64),

        ]);

        return successResponse(
            'Webhook secret regenerated successfully',
            [
                'webhook_secret' => $webhook->webhook_secret
            ]
        );
    }

    public function requestProductionAccess(Request $request)
    {
        $user = $request->user();

        if (!$user->hasRole('dev')) {

            return failureResponse(
                'Unauthorized.',
                403
            );
        }

        $request->validate([

            'app_type' => [
                'required',
                'in:sister,external'
            ],

            'cac_number' => [
                'required_if:app_type,external'
            ],

            'business_type' => [
                'required_if:app_type,external',
                'in:bn,co,it'
            ],

            'cac_document' => [
                'required_if:app_type,external',
                'file',
                'mimes:pdf,jpg,jpeg,png'
            ],

        ]);

        $existing = ProductionAccessRequest::where(

            'user_id',
            $user->id

        )
            ->whereIn('status', [

                'pending',
                'approved',

            ])
            ->exists();

        if ($existing) {

            return failureResponse(

                'A production access request already exists.',

                422

            );
        }

        $path = null;

        if ($request->hasFile('cac_document')) {

            $path = $request
                ->file('cac_document')
                ->store(
                    'cac-documents',
                    'public'
                );
        }

        $productionRequest = ProductionAccessRequest::create([

            'user_id' => $user->id,

            'app_type' => $request->app_type,

            'status' => ProductionAccessRequestStatusEnums::PENDING,

            'cac_number' => $request->cac_number,

            'business_type' => $request->business_type,

            'cac_document_path' => $path,

        ]);

        return successResponse(
            'Production access request submitted successfully.',
            $productionRequest,
            201

        );
    }
    public function approveProductionAccess(ProductionAccessRequest $productionRequest)
    {
        DB::transaction(function () use ($productionRequest) {

            $user = $productionRequest->user;

            // Get the developer's existing sandbox API client
            $sandboxClient = ApiClient::where('customer_id', $user->id)
                ->where('environment', 'sandbox')
                ->first();

            if (! $sandboxClient) {
                throw new \Exception('Sandbox API client not found for this developer.');
            }

            // Mark the production request as approved
            $productionRequest->update([
                'status'      => ProductionAccessRequestStatusEnums::APPROVED,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ]);

            // Create or retrieve the production API client
            $productionClient = ApiClient::firstOrCreate(
                [
                    'customer_id' => $user->id,
                    'environment' => 'production',
                ],
                [
                    'name'             => $sandboxClient->name,
                    'company_name'     => $sandboxClient->company_name,
                    'company_website'  => $sandboxClient->company_website,
                    'callback_url'     => $sandboxClient->callback_url,
                    'active'           => true,
                    'is_blocked'       => false,
                ]
            );

            // Create the webhook configuration if it doesn't already exist
            $productionClient->webhook()->firstOrCreate(
                [],
                [
                    'webhook_url'    => null,
                    'webhook_secret' => Str::random(64),
                    'is_active'      => true,
                ]
            );
        });

        return successResponse(
            'Production access approved.'
        );
    }

    public function productionAccessRequests(Request $request)
    {
        $request->validate([
            'search'    => 'nullable|string',
            'status'    => 'nullable|string',
            'app_type'  => 'nullable|string',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->integer('per_page', 20);

        $search = trim((string) $request->search);

        $query = ProductionAccessRequest::query()

            ->with([
                'user:id,name,email,phone',
                'approvedBy:id,name,email',
                'approvedBy.roles:id,name',
            ])

            ->when($request->filled('status'), function ($q) use ($request) {

                $q->where(
                    'status',
                    $request->status
                );
            })

            ->when($request->filled('app_type'), function ($q) use ($request) {

                $q->where(
                    'app_type',
                    $request->app_type
                );
            })

            ->when($search, function ($query) use ($search) {

                $driver = $query->getConnection()->getDriverName();

                $operator = $driver === 'pgsql'
                    ? 'ILIKE'
                    : 'LIKE';

                $query->where(function ($q) use ($search, $operator) {

                    $q->where('status', $operator, "%{$search}%")

                        ->orWhere('app_type', $operator, "%{$search}%")

                        ->orWhereHas('user', function ($user) use ($search, $operator) {

                            $user->where('first_name', $operator, "%{$search}%")
                                ->orWhere('last_name', $operator, "%{$search}%")
                                ->orWhere('email', $operator, "%{$search}%")
                                ->orWhere('phone', $operator, "%{$search}%");

                            if ($operator === 'ILIKE') {

                                $user->orWhereRaw(
                                    "CONCAT(first_name,' ',last_name) ILIKE ?",
                                    ["%{$search}%"]
                                );
                            } else {

                                $user->orWhereRaw(
                                    "CONCAT(first_name,' ',last_name) LIKE ?",
                                    ["%{$search}%"]
                                );
                            }
                        });
                });
            });

        $requests = $query
            ->latest()
            ->paginate($perPage);

        return successResponse(
            'Production access requests retrieved successfully.',
            [
                'count' => $requests->total(),
                'requests' => $requests,
            ]
        );
    }

    public function developers(Request $request)
    {
        $request->validate([
            'search'   => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $request->integer('per_page', 20);
        $search = trim((string) $request->search);
        $developers = User::query()
            ->role('dev')
            ->when($search, function ($query) use ($search) {
                $driver = $query->getConnection()->getDriverName();
                $operator = $driver === 'pgsql'
                    ? 'ILIKE'
                    : 'LIKE';

                $query->where(function ($q) use ($search, $operator) {
                    $q->where('first_name', $operator, "%{$search}%")
                        ->orWhere('last_name', $operator, "%{$search}%")
                        ->orWhere('email', $operator, "%{$search}%")
                        ->orWhere('phone', $operator, "%{$search}%")

                        ->orWhere(function ($nameQuery) use ($search, $operator) {

                            $nameQuery
                                ->where('first_name', $operator, "%{$search}%")
                                ->where('last_name', $operator, "%{$search}%");
                        })

                        ->orWhereHas('apiClient', function ($api) use ($search, $operator) {

                            $api->where('company_name', $operator, "%{$search}%")
                                ->orWhere('company_website', $operator, "%{$search}%")
                                ->orWhere('name', $operator, "%{$search}%");
                        });
                });
            })
            ->with([
                'roles:id,name',
                'apiClient:id,customer_id,name,company_name,company_website,callback_url,api_key,environment,active,ip_whitelist,is_blocked,created_at,updated_at',
                'apiClient.webhook:id,api_client_id,webhook_secret,webhook_url'
            ])
            ->latest()
            ->paginate($perPage);

        return successResponse(
            'Developers retrieved successfully.',
            [
                'count' => $developers->total(),
                'developers' => $developers,
            ]
        );
    }

    public function block(Request $request, BlockApiClientAction $action)
    {
        $dto = BlockApiClientDTO::fromRequest($request);
        $client = $action->execute($dto);

        $status = $dto->block ? 'blocked' : 'unblocked';

        return successResponse("API client {$status} successfully.", $client);
    }
}

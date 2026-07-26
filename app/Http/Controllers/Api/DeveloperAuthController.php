<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\RegisterDeveloperAction;
use App\DTO\Auth\DeveloperRegisterDTO;
use App\Enums\ProductionAccessRequestStatusEnums;
use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\ProductionAccessRequest;
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

        if (! $user->hasRole('dev')) {

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

    public function approveProductionAccess(ProductionAccessRequest $productionRequest) {
        DB::transaction(function () use ($productionRequest) {

            $user = $productionRequest->user;

            $productionRequest->update([
                'status' => ProductionAccessRequestStatusEnums::APPROVED,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ]);

            ApiClient::firstOrCreate(
                [
                    'customer_id' => $user->id,
                    'environment' => 'production',
                ],

                [

                    'name' => $user->name,
                    'company_name' => $user->company_name,
                    'company_website' => $user->company_website,
                    'callback_url' => $user->callback_url,
                    'active' => true,
                ]

            );
        });

        return successResponse(
            'Production access approved.'
        );
    }
}

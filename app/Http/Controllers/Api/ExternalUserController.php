<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use App\Services\SmileIdService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductionAccessRequest;
use App\Services\UserProvisioningManager;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Admin\ProductionAccessRequestNotification;

class ExternalUserController extends Controller
{
    public function requestProductionAccess(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasRole('dev')) {
                return failureResponse('Unauthorized action', 403);
            }

            // Basic validation
            $request->validate([
                'app_type' => ['required', 'string', 'in:sister,external'],
                'cac_document' => ['required_if:app_type,external', 'file', 'mimes:pdf,jpg,jpeg,png'],
            ]);

            $existing = ProductionAccessRequest::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->first();

            if ($existing) {
                return failureResponse('Production access already requested');
            }

            $data = [
                'user_id' => $user->id,
                'app_type' => $request->app_type,
                'status' => 'pending',
            ];

            /**
             * EXTERNAL APP FLOW
             */
            if ($request->app_type === 'external') {

                $path = $request->file('cac_document')->store('cac-documents', 'public');
                $data['cac_document_path'] = $path;

                app(SmileIdService::class)->submitBusinessCAC([
                    'user_id' => $user->id,
                    'cac_number' => $request->cac_number,
                    'business_type' => $request->business_type, // bn | co | it
                    'cac_document' => $request->file('cac_document'),
                ]);
            }




            $productionRequest = ProductionAccessRequest::create($data);
            /**
             * SISTER APP FLOW
             */
            if ($request->app_type === 'sister') {

                DB::transaction(function () use ($user, $productionRequest) {

                    $productionRequest->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => Auth::id(),
                    ]);

                    $user->update([
                        'production_access_approved_at' => now(),
                    ]);

                    $user->refresh();

                    app(UserProvisioningManager::class)->provision($user);
                });
            }
            $admins = User::role('admin')->get();
            Notification::send(
                $admins,
                new ProductionAccessRequestNotification($user, $productionRequest)
            );

            /**
             * Response changes depending on app type
             */
            if ($request->app_type === 'external') {
                return successResponse(
                    'Production access request submitted. CAC verification pending.',
                    [
                        'request_id' => $productionRequest->id,
                        'next_step' => 'cac_verification',
                    ],
                    201
                );
            }

            return successResponse(
                'Production access request submitted successfully.',
                [
                    'request_id' => $productionRequest->id,
                ],
                201
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to submit production access request',
                400,
                'PRODUCTION_ACCESS_REQUEST_FAILED',
                $th
            );
        }
    }

    public function approveProductionAccess(ProductionAccessRequest $request, UserProvisioningManager $provisioner)
    {
        DB::transaction(function () use ($request, $provisioner) {

            $request->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ]);

            $user = $request->user;

            // Mark user as production-approved
            $user->update([
                'production_access_approved_at' => now(),
            ]);

            //THIS IS THE MOMENT
            $provisioner->provision($user);
        });
    }

    public function credentials(Request $request)
    {
        $user = $request->user();

        $clients = ApiClient::where('customer_id', $user->id)->get();

        if ($clients->isEmpty()) {
            return failureResponse(
                'No API credentials found for this user',
                404,
                'not_found'
            );
        }

        $grouped = $clients->keyBy('environment');

        return successResponse('API credentials fetched successfully', [
            'sandbox' => isset($grouped['sandbox'])
                ? $this->transformClient($grouped['sandbox'])
                : null,

            'production' => isset($grouped['production'])
                ? $this->transformClient($grouped['production'])
                : null,
        ]);
    }

    private function transformClient(ApiClient $client): array
    {
        return [
            'client_id'    => $client->id,
            'customer_id'  => $client->customer_id,
            'name'         => $client->name,
            'api_key'      => $client->api_key,
            'environment'  => $client->environment,
            'active'       => (bool) $client->active,
            'ip_whitelist' => $client->ip_whitelist,
            'is_blocked'   => (bool) $client->is_blocked,
            'created_at'   => $client->created_at,
            'updated_at'   => $client->updated_at,
        ];
    }
}

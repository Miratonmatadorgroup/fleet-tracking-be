<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\Delivery;
use App\Models\ApiClient;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Services\PricingService;
use App\Enums\TransportModeEnums;
use App\Services\GoogleMapsService;
use App\Http\Controllers\Controller;
use App\DTOs\Delivery\CancelDeliveryDTO;
use App\DTOs\ApiClient\BlockApiClientDTO;
use App\Actions\Delivery\CancelDeliveryAction;
use App\DTOs\Delivery\ExternalBookDeliveryDTO;
use App\Actions\ApiClient\BlockApiClientAction;
use App\DTOs\Delivery\ExternalTrackDeliveryDTO;
use App\Actions\Delivery\ExternalBookDeliveryAction;
use App\Actions\Delivery\ExternalTrackDeliveryAction;
use App\DTOs\Sender\ExternalConfirmDeliveryCompletionDTO;
use App\Actions\Sender\ExternalConfirmDeliveryCompletionAction;


class ExternalDeliveryController extends Controller
{
    public function bookDelivery(ApiClient $apiClient, ExternalBookDeliveryAction $action)
    {
        try {
            $request = request();

            //Handle file upload here
            $deliveryPicsPaths = [];
            if ($request->hasFile('delivery_pics')) {
                $files = $request->file('delivery_pics');
                $files = is_array($files) ? $files : [$files];

                foreach ($files as $image) {
                    $path = $image->store('delivery_pics', 'public');
                    $deliveryPicsPaths[] = $path;
                }
            }

            $dto = ExternalBookDeliveryDTO::fromRequest($request, $apiClient, $deliveryPicsPaths);

            $result = $action->execute($dto);

            return successResponse('Delivery & Payment created successfully.', $result);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500, null, $th);
        }
    }


    public function trackDelivery(ApiClient $apiClient, ExternalTrackDeliveryAction $action)
    {
        try {
            $dto = ExternalTrackDeliveryDTO::fromRequest(request(), $apiClient);

            $result = $action->execute($dto);

            return successResponse('Delivery status fetched successfully.', $result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500, null, $th);
        }
    }

    public function customizePreviewPrice(Request $request, PricingService $pricingService)
    {
        try {
            $pickupLocation  = $request->input('pickup_location');
            $dropoffLocation = $request->input('dropoff_location');
            $mode            = TransportModeEnums::from($request->input('mode_of_transportation', 'car'));
            $deliveryType    = $request->input('delivery_type', 'standard');

            if (!$pickupLocation || !$dropoffLocation) {
                return response()->json(['message' => 'pickup_location and dropoff_location are required.'], 422);
            }

            // === Get coordinates ===
            $coordinates = app(GoogleMapsService::class)->getCoordinatesAndDistance(
                pickupAddress: $pickupLocation,
                dropoffAddress: $dropoffLocation,
                mode: $mode
            );

            $pickupInfo = app(GoogleMapsService::class)->reverseGeocode(
                $coordinates['pickup_latitude'],
                $coordinates['pickup_longitude']
            );

            $dropoffInfo = app(GoogleMapsService::class)->reverseGeocode(
                $coordinates['dropoff_latitude'],
                $coordinates['dropoff_longitude']
            );

            $pricing = $pricingService->calculatePriceAndETA(
                [
                    'lat' => $coordinates['pickup_latitude'],
                    'lng' => $coordinates['pickup_longitude'],
                    'country' => $pickupInfo['country'] ?? null,
                ],
                [
                    'lat' => $coordinates['dropoff_latitude'],
                    'lng' => $coordinates['dropoff_longitude'],
                    'country' => $dropoffInfo['country'] ?? null,
                ],
                $mode,
                $deliveryType
            );

            return response()->json([
                'success' => true,
                'pricing' => $pricing,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    public function cancel(string $id, CancelDeliveryAction $action)
    {
        try {
            $dto = CancelDeliveryDTO::fromRequest($id);

            $action->execute($dto);

            return successResponse('Delivery cancelled successfully.');
        } catch (\Throwable $th) {
            return failureResponse('Failed to cancel delivery.', 500, 'cancel_error', $th);
        }
    }

    public function listDeliveries(Request $request, ApiClient $apiClient)
    {
        try {
            $query = Delivery::query()
                ->where('api_client_id', $apiClient->id);

            // === Global search ===
            if ($request->filled('search')) {
                $searchTerm = $request->search;

                $query->where(function ($q) use ($searchTerm) {
                    $q->where('customer_name', 'LIKE', "%$searchTerm%")
                        ->orWhere('receiver_name', 'LIKE', "%$searchTerm%")
                        ->orWhere('status', 'LIKE', "%$searchTerm%")
                        ->orWhere('pickup_location', 'LIKE', "%$searchTerm%")
                        ->orWhere('dropoff_location', 'LIKE', "%$searchTerm%")
                        ->orWhere('tracking_number', 'LIKE', "%$searchTerm%");
                });
            }

            // === Pagination ===
            $perPage = (int) $request->input('per_page', 10);

            $deliveries = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return successResponse('Deliveries fetched successfully.', [
                'data' => $deliveries->items(),
                'meta' => [
                    'current_page' => $deliveries->currentPage(),
                    'per_page'     => $deliveries->perPage(),
                    'total'        => $deliveries->total(),
                    'last_page'    => $deliveries->lastPage(),
                ]
            ]);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500, null, $th);
        }
    }




    public function externalConfirmDelivery(
        Request $request,
        TwilioService $twilio,
        TermiiService $termii,
        ApiClient $apiClient
    ) {
        $dto = new ExternalConfirmDeliveryCompletionDTO(
            trackingNumber: $request->input('tracking_number')
        );

        $delivery = app(ExternalConfirmDeliveryCompletionAction::class)
            ->execute($dto, $apiClient, $twilio, $termii);

        return successResponse('External delivery confirmed successfully.', $delivery);
    }


    public function block(Request $request, BlockApiClientAction $action)
    {
        $dto = BlockApiClientDTO::fromRequest($request);
        $client = $action->execute($dto);

        $status = $dto->block ? 'blocked' : 'unblocked';

        return successResponse("API client {$status} successfully.", $client);
    }
}

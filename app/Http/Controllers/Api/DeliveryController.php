<?php

namespace App\Http\Controllers\Api;


use Throwable;
use App\Models\Delivery;
use App\Models\Commission;
use App\Models\DriverRating;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\DeliveryStatusEnums;
use App\Services\PricingServiceOld;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\DTOs\Delivery\BookDeliveryDTO;
use App\DTOs\Delivery\ShowTrackingDTO;
use App\Events\Delivery\DeliveryViewed;
use App\DTOs\Delivery\CancelDeliveryDTO;
use App\DTOs\Delivery\UpdateDeliveryDTO;
use App\DTOs\Delivery\AdminBookDeliveryDTO;
use App\Actions\Delivery\BookDeliveryAction;
use App\DTOs\Delivery\GetDeliveryDetailsDTO;
use App\DTOs\Delivery\AdminUpdateDeliveryDTO;
use App\Actions\Delivery\CancelDeliveryAction;
use App\Actions\Delivery\UpdateDeliveryAction;
use App\DTOs\Delivery\DeliveryAssignmentLogsDTO;
use App\Services\AdminDeliveryCompletionService;
use App\Actions\Delivery\AdminBookDeliveryAction;
use App\Actions\Delivery\GetUserDeliveriesAction;
use App\Actions\Delivery\AdminTrackDeliveryAction;
use App\Actions\Delivery\GetDeliveryDetailsAction;
use App\Actions\Delivery\AdminUpdateDeliveryAction;
use App\Actions\Delivery\AdminGetAllDeliveriesAction;
use App\Actions\Delivery\AssignDeliveryToDriverAction;
use App\Actions\Delivery\FetchDeliveryAssignmentLogsAction;
use App\Actions\Delivery\ShowDeliveryByTrackingNumberAction;
use App\DTOs\Delivery\ConfirmInAppDeliveryCompletionAsAdminDTO;
use App\Actions\Delivery\ConfirmInAppDeliveryCompletionAsAdminAction;

class DeliveryController extends Controller
{
    protected PricingServiceOld $pricingService;
    protected AdminBookDeliveryAction $adminBookDeliveryAction;

    protected AdminUpdateDeliveryAction $adminUpdateDeliveryAction;

    protected AssignDeliveryToDriverAction $assignDeliveryToDriverAction;

    public function __construct(
        PricingServiceOld $pricingService,
        AdminBookDeliveryAction $adminBookDeliveryAction,
        AdminUpdateDeliveryAction $adminUpdateDeliveryAction,
        AssignDeliveryToDriverAction $assignDeliveryToDriverAction
    ) {
        $this->pricingService = $pricingService;
        $this->adminBookDeliveryAction = $adminBookDeliveryAction;
        $this->adminUpdateDeliveryAction =  $adminUpdateDeliveryAction;
        $this->assignDeliveryToDriverAction = $assignDeliveryToDriverAction;
    }

    public function bookDelivery(Request $request)
    {
        try {
            $user = Auth::user();
            $dto = BookDeliveryDTO::fromRequest($request, $user->hasRole('admin'));

            $delivery = app(BookDeliveryAction::class)->execute($dto, $user);

            return successResponse('Delivery created. Proceed to payment.', $delivery);
        } catch (\Exception $e) {
            return failureResponse(
                $e->getMessage(),
                422,
                'booking_failed'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to create delivery.',
                500,
                'schedule_error',
                $th
            );
        }
    }


    public function updateBooking(Request $request, Delivery $delivery)
    {
        try {
            $dto = UpdateDeliveryDTO::fromRequest($request);

            $updatedDelivery = app(UpdateDeliveryAction::class)->execute($dto, $delivery);

            return successResponse('Delivery updated successfully.', $updatedDelivery);
        } catch (\Throwable $th) {
            $message = $th->getMessage() === 'This delivery has already been booked and cannot be updated.'
                ? $th->getMessage()
                : 'Failed to update delivery.';

            $code = $th->getCode() ?: 500;
            $type = $code === 403 ? 'delivery_locked' : 'update_error';

            return failureResponse($message, $code, $type, $th);
        }
    }


    public function adminBookDelivery(Request $request)
    {
        try {
            $dto = AdminBookDeliveryDTO::fromRequest($request);
            $delivery = $this->adminBookDeliveryAction->execute($dto);

            $message = $dto->customer
                ? 'Delivery created for existing user. Proceed to payment.'
                : 'Delivery created for guest user. Proceed to payment.';

            return successResponse($message, $delivery);
        } catch (\Throwable $th) {
            return failureResponse('Failed to create delivery.', 500, 'schedule_error', $th);
        }
    }

    public function adminUpdateBooking(Request $request, Delivery $delivery)
    {
        try {
            $dto = AdminUpdateDeliveryDTO::fromRequest($request);
            $updated = $this->adminUpdateDeliveryAction->execute($dto, $delivery);

            return successResponse('Delivery updated successfully.', $updated);
        } catch (\Throwable $th) {
            $message = $th->getMessage() === 'This delivery has already been booked and cannot be updated.'
                ? $th->getMessage()
                : 'Failed to update delivery.';
            $code = $th->getCode() ?: 500;
            $type = $code === 403 ? 'delivery_locked' : 'update_error';

            return failureResponse($message, $code, $type, $th);
        }
    }

    public function myDeliveries(Request $request, GetUserDeliveriesAction $action)
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 10);
        $search = $request->query('search');
        $deliveries = $action->execute($user, $perPage, $search);
        return successResponse('Your deliveries retrieved successfully.', $deliveries);
    }

    public function myDeliveryStats()
    {
        $user = Auth::user();

        $statuses = [
            DeliveryStatusEnums::BOOKED,
            DeliveryStatusEnums::IN_TRANSIT,
            DeliveryStatusEnums::DELIVERED,
            DeliveryStatusEnums::COMPLETED,
        ];

        $stats = Delivery::where('customer_id', $user->id)
            ->whereIn('status', array_map(fn($status) => $status->value, $statuses))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $result = [];
        foreach ($statuses as $status) {
            $result[$status->value] = $stats[$status->value] ?? 0;
        }

        return successResponse('Delivery statistics retrieved successfully.', $result);
    }

    public function adminGetAllDeliveries(Request $request, AdminGetAllDeliveriesAction $action)
    {
        $user = Auth::user();

        if (!$user->hasRole('admin')) {
            return failureResponse('Unauthorized access. Only admins can view all deliveries.', 403);
        }

        try {
            $deliveries = $action->execute($request);
            return successResponse('All deliveries fetched successfully.', $deliveries);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch deliveries.', 500, 'fetch_error', $th);
        }
    }

    public function showByTrackingNumber(ShowTrackingDTO $dto, ShowDeliveryByTrackingNumberAction $action)
    {
        $limit = $dto->input('history_limit', 20);

        $delivery = $action->execute($dto->tracking_number, null, $limit);

        if (!$delivery) {
            return failureResponse("No delivery found with the provided tracking number.", 404);
        }

        return successResponse("Delivery details retrieved successfully.", $delivery);
    }

    public function adminTrackDeliveryByTrackingNumber(Request $request, AdminTrackDeliveryAction $action)
    {
        try {
            $user = Auth::user();

            if (!$user->hasRole('admin')) {
                return failureResponse('Unauthorized. Only admins can track deliveries here.', 403);
            }

            $validated = $request->validate([
                'tracking_number' => 'required|string',
            ]);

            $delivery = $action->execute($validated['tracking_number']);

            if (!$delivery) {
                return failureResponse("No delivery found with the provided tracking number.", 404);
            }

            return successResponse("Delivery details retrieved successfully.", $delivery);
        } catch (\Throwable $th) {
            return failureResponse("Failed to retrieve delivery details.", 500, 'admin_tracking_error', $th);
        }
    }

    public function adminAssignDeliveryToDriver(Request $request)
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string|exists:deliveries,tracking_number',
            'identifier' => 'required|string',
        ]);

        try {
            $delivery = $this->assignDeliveryToDriverAction->execute(
                $validated['tracking_number'],
                $validated['identifier']
            );

            return successResponse('Driver assigned successfully.', $delivery);
        } catch (\Throwable $th) {
            return failureResponse('Driver assignment failed.', 500, 'driver_assignment_error', $th);
        }
    }

    public function getDeliveryDetails($delivery_id)
    {
        try {
            $dto = new GetDeliveryDetailsDTO();
            $dto->deliveryId = $delivery_id;

            $delivery = app(GetDeliveryDetailsAction::class)
                ->execute($dto);

            event(new DeliveryViewed($delivery));

            return successResponse('Delivery details retrieved.', $delivery);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return failureResponse('Delivery not found.', 404, 'not_found');
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch delivery details.', 500, 'fetch_error', $th);
        }
    }

    public function cancelDelivery(string $id)
    {
        try {
            $dto = CancelDeliveryDTO::fromRequest($id);

            app(CancelDeliveryAction::class)->execute($dto);

            return successResponse('Delivery cancelled successfully.');
        } catch (\Throwable $th) {
            return failureResponse('Failed to cancel delivery.', 500, 'cancel_error', $th);
        }
    }


    // public function adminMarkDeliveriesCompleted()
    // {
    //     $result = AdminDeliveryCompletionService::completeDeliveriesWithBankReference();

    //     return successResponse($result['message'], $result['count']);
    // }

    public function adminMarkExternalDeliveriesCompleted(Request $request)
    {
        $request->validate([
            'api_client_id' => 'required|uuid|exists:api_clients,id',
        ]);

        $result = AdminDeliveryCompletionService::completeDeliveriesSingleExternalUser($request->api_client_id);

        return successResponse($result['message'], $result['count']);
    }

    public function adminMarkInAppDeliveryAsCompleted($id, ConfirmInAppDeliveryCompletionAsAdminAction $action, TwilioService $twilio, TermiiService $termii)
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('admin')) {
            return failureResponse("Unauthorized. Only admins can perform this action.", 403);
        }

        try {
            $dto = new ConfirmInAppDeliveryCompletionAsAdminDTO(deliveryId: $id);

            $action->execute($dto, $twilio, $termii);

            return successResponse("Delivery has been marked as completed by admin.");
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 400);
        }
    }



    public function viewDeliveryAssignmentLogs(Request $request, FetchDeliveryAssignmentLogsAction $action)
    {
        try {
            $dto = DeliveryAssignmentLogsDTO::fromRequest($request);
            $logs = $action->execute($dto);

            return successResponse('Delivery assignment logs fetched successfully.', $logs);
        } catch (\Throwable $e) {
            return failureResponse('Failed to fetch delivery assignment logs.', 500, $e->getMessage());
        }
    }


    public function internalDeliveryRevenue(Request $request)
    {
        $query = Delivery::query()
            ->whereNull('api_client_id')
            ->where('status', DeliveryStatusEnums::COMPLETED);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $totalRevenue = (float) $query->sum('total_price');

        if ($totalRevenue <= 0) {
            return successResponse('No internal delivery revenue yet', [
                'total_revenue' => 0,
                'breakdown' => [],
                'deliveries_count' => 0,
            ]);
        }

        // Commission percentages
        $driverPercent   = Commission::where('role', 'driver')->latest()->value('percentage') ?? 10;
        $partnerPercent  = Commission::where('role', 'partner')->latest()->value('percentage') ?? 10;
        $investorPercent = Commission::where('role', 'investor')->latest()->value('percentage') ?? 30;

        $driverTotal   = 0;
        $partnerTotal  = 0;
        $investorTotal = 0;
        $platformTotal = 0;

        $deliveries = $query
            ->with(['transportMode.partner'])
            ->get();

        foreach ($deliveries as $delivery) {
            $amount = (float) $delivery->total_price;

            $driver = $amount * ($driverPercent / 100);

            if ($delivery->transportMode?->partner) {
                $partner  = $amount * ($partnerPercent / 100);
                $investor = 0;
            } else {
                $partner  = 0;
                $investor = $amount * ($investorPercent / 100);
            }

            $platform = $amount - ($driver + $partner + $investor);

            $driverTotal   += $driver;
            $partnerTotal  += $partner;
            $investorTotal += $investor;
            $platformTotal += $platform;
        }

        return successResponse('Internal delivery revenue summary', [
            'total_revenue' => round($totalRevenue, 2),
            'breakdown' => [
                'driver_commission'   => round($driverTotal, 2),
                'partner_commission'  => round($partnerTotal, 2),
                'investor_commission' => round($investorTotal, 2),
                'platform_commission' => round($platformTotal, 2),
            ],
            'deliveries_count' => $deliveries->count(),
        ]);
    }


    public function externalDeliveryRevenue(Request $request)
    {
        $query = Delivery::query()
            ->whereNotNull('api_client_id')
            ->where('status', DeliveryStatusEnums::COMPLETED);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $totalRevenue = (float) $query->sum('total_price');

        if ($totalRevenue <= 0) {
            return successResponse('No external delivery revenue yet', [
                'total_revenue' => 0,
                'breakdown' => [],
                'deliveries_count' => 0,
            ]);
        }

        // Same commission logic
        $driverPercent   = Commission::where('role', 'driver')->latest()->value('percentage') ?? 10;
        $partnerPercent  = Commission::where('role', 'partner')->latest()->value('percentage') ?? 10;
        $investorPercent = Commission::where('role', 'investor')->latest()->value('percentage') ?? 30;

        $driverTotal   = 0;
        $partnerTotal  = 0;
        $investorTotal = 0;
        $platformTotal = 0;

        $deliveries = $query
            ->with(['transportMode.partner', 'apiClient'])
            ->get();

        foreach ($deliveries as $delivery) {
            $amount = (float) $delivery->total_price;

            $driver = $amount * ($driverPercent / 100);

            if ($delivery->transportMode?->partner) {
                $partner  = $amount * ($partnerPercent / 100);
                $investor = 0;
            } else {
                $partner  = 0;
                $investor = $amount * ($investorPercent / 100);
            }

            $platform = $amount - ($driver + $partner + $investor);

            $driverTotal   += $driver;
            $partnerTotal  += $partner;
            $investorTotal += $investor;
            $platformTotal += $platform;
        }

        return successResponse('External user delivery revenue summary', [
            'total_revenue' => round($totalRevenue, 2),
            'breakdown' => [
                'driver_commission'   => round($driverTotal, 2),
                'partner_commission'  => round($partnerTotal, 2),
                'investor_commission' => round($investorTotal, 2),
                'platform_commission' => round($platformTotal, 2),
            ],
            'deliveries_count' => $deliveries->count(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\Delivery;
use App\Models\RidePool;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Enums\RidePoolStatusEnums;
use App\DTOs\Ratings\RateDriverDTO;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Actions\Ratings\RateDriverAction;
use App\DTOs\BookRide\RateRidePoolDriverDTO;
use App\DTOs\Sender\ConfirmDeliveryCompletionDTO;
use App\Actions\BookRide\RateRidePoolDriverAction;
use App\Actions\Sender\ConfirmDeliveryCompletionAction;

class UserController extends Controller
{
    public function myNotifications(Request $request)
    {
        try {
            $notifications = Auth::user()->notifications()->latest()->get();
            return successResponse('Notifications fetched successfully', $notifications);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch notifications', 500, 'FETCH_NOTIFICATIONS_ERROR', $th);
        }
    }


    public function confirmAsCompleted(
        $id,
        TwilioService $twilio,
        TermiiService $termii,
        ConfirmDeliveryCompletionAction $action
    ) {
        $user = Auth::user();

        if (!$user) {
            return failureResponse("Unauthorized. You must be logged in to confirm delivery.", 403);
        }

        try {
            $dto = new ConfirmDeliveryCompletionDTO(
                deliveryId: $id,
                customerId: $user->id
            );

            $action->execute($dto, $twilio, $termii);

            return successResponse("Delivery is completed.");
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 404);
        }
    }


    public function rateDriver(Request $request, RateDriverAction $action)
    {
        $user = Auth::user();

        if (!$user) {
            return failureResponse("Unauthorized. You must be logged in.", 403);
        }

        $validated = $request->validate([
            'delivery_id' => 'required|exists:deliveries,id',
            'rating'      => 'required|integer|min:1|max:5',
            'comment'     => 'nullable|string|max:500',
        ]);

        try {
            $delivery = Delivery::findOrFail($validated['delivery_id']);
            $driverUserId = optional($delivery->driver)->user_id;

            if ($user->id === $driverUserId) {
                return failureResponse("You cannot rate yourself.", 403);
            }
            $dto = new RateDriverDTO(
                deliveryId: $delivery->id,
                customerId: $user->id,
                driverId: $driverUserId,
                rating: $validated['rating'],
                comment: $validated['comment'] ?? null,
            );

            $rating = $action->execute($dto);

            return successResponse("Driver rated successfully.", $rating);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 400);
        }
    }

    public function rateRidePoolDriver(Request $request, RateRidePoolDriverAction $action)
    {
        $user = Auth::user();

        if (!$user) {
            return failureResponse("Unauthorized. You must be logged in.", 403);
        }

        $validated = $request->validate([
            'ride_pool_id' => 'required|exists:ride_pools,id',
            'rating'       => 'required|integer|min:1|max:5',
            'comment'      => 'nullable|string|max:500',
        ]);

        try {
            $ride = \App\Models\RidePool::findOrFail($validated['ride_pool_id']);
            $driverUserId = optional($ride->driver)->user_id;

            if ($user->id === $driverUserId) {
                return failureResponse("You cannot rate yourself.", 403);
            }

            $dto = new RateRidePoolDriverDTO(
                ridePoolId: $ride->id,
                customerId: $user->id,
                driverId: $driverUserId,
                rating: $validated['rating'],
                comment: $validated['comment'] ?? null,
            );

            $rating = $action->execute($dto);

            return successResponse("Driver rated successfully.", $rating);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 400);
        }
    }


    public function dismissRatingPrompt(Request $request)
    {
        $validated = $request->validate([
            'ride_pool_id' => 'required|exists:ride_pools,id',
        ]);

        $ride = RidePool::where('id', $validated['ride_pool_id'])
            ->where('user_id', Auth::id())
            ->first();

        if (!$ride) {
            return failureResponse("Ride not found.", 404);
        }

        // mark dismissed
        $ride->update(['rated' => false]);

        return successResponse("Dismissed successfully.");
    }

    public function getLastRideNeedingRating()
    {
        $user = Auth::user();

        $ride = RidePool::where('user_id', $user->id)
            ->whereIn('status', [RidePoolStatusEnums::COMPLETED->value, RidePoolStatusEnums::RIDE_ENDED->value])
            ->whereNull('rated')
            ->latest('end_time')
            ->first();

        return successResponse("Ride check done.", $ride);
    }
}

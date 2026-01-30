<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\RidePool;
use App\Models\Commission;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\WalletService;
use App\Enums\DriverStatusEnums;
use App\Services\PricingService;
use App\Enums\TransportModeEnums;
use App\Models\WalletTransaction;
use App\DTOs\BookRide\BookRideDTO;
use App\Enums\RidePoolStatusEnums;
use Illuminate\Support\Facades\DB;
use App\Services\GoogleMapsService;
use App\Services\RideSearchService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Actions\Driver\EndRideAction;
use App\Actions\Driver\StartRideAction;
use App\Actions\BookRide\BookRideAction;
use App\Actions\Driver\AcceptRideAction;
use App\Actions\Driver\RejectRideAction;
use App\Enums\RidePoolPaymentStatusEnums;
use App\Enums\WalletTransactionTypeEnums;
use App\Enums\WalletTransactionMethodEnums;
use App\Enums\WalletTransactionStatusEnums;
use App\DTOs\Driver\FetchDriverBookedRidesDTO;
use App\Actions\Driver\FetchDriverBookedRidesAction;
use App\Notifications\User\RideCancelledNotification;
use App\Notifications\User\DriverRideCancelledNotification;
use App\Notifications\User\RideCancelledByAdminNotification;

class RideBookingController extends Controller
{
    protected BookRideAction $bookRide;
    protected GoogleMapsService $service;


    public function __construct(BookRideAction $bookRide,  GoogleMapsService $service)
    {
        $this->bookRide = $bookRide;
        $this->service = $service;
    }

    public function search(Request $request, RideSearchService $service)
    {
        try {
            $validated = $request->validate([
                'pickup'             => 'nullable|string',
                'dropoff'            => 'nullable|string',
                'transport_mode'     => 'nullable|string|exists:transport_modes,type',
                'usage_hours'        => 'nullable|numeric|min:1',
                'ride_pool_category' => 'nullable|string',
                'driver_name'        => 'nullable|string',
                'page'               => 'nullable|integer|min:1',
                'per_page'           => 'nullable|integer|min:1|max:100',
            ]);

            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 10;

            if (empty($validated['dropoff']) && empty($validated['usage_hours'])) {
                return failureResponse(
                    "Either dropoff or usage_hours must be provided.",
                    422
                );
            }

            $results = $service->search($validated);

            $ridesQuery = \App\Models\RidePool::query()
                ->where('status', '!=', \App\Enums\RidePoolStatusEnums::CANCELLED->value)
                ->where('is_flagged', false)
                ->with(['driver', 'transportMode']);

            if (!empty($validated['pickup'])) {
                $ridesQuery->whereJsonContains('pickup_location->address', $validated['pickup']);
            }

            if (!empty($validated['dropoff'])) {
                $ridesQuery->whereJsonContains('dropoff_location->address', $validated['dropoff']);
            }

            if (!empty($validated['transport_mode'])) {
                $ridesQuery->whereHas('transportMode', function ($q) use ($validated) {
                    $q->where('type', strtolower($validated['transport_mode']));
                });
            }

            if (!empty($validated['driver_name'])) {
                $ridesQuery->whereHas('driver', function ($q) use ($validated) {
                    $q->where('name', 'ILIKE', "%{$validated['driver_name']}%");
                    $q->orWhere('name', 'LIKE', "%{$validated['driver_name']}%");
                });
            }

            if (!empty($validated['ride_pool_category'])) {
                $ridesQuery->where('ride_pool_category', $validated['ride_pool_category']);
            }

            // Paginate the results
            $paginated = $ridesQuery->orderBy('ride_date', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $priceService = app(\App\Services\PricingService::class);

            $ridesData = $paginated->map(function ($ride) use ($priceService) {
                $pricing = $priceService->calculateRidePoolingPrice(
                    pickup: $ride->pickup_location['address'] ?? null,
                    dropoff: $ride->dropoff_location['address'] ?? null,
                    transportMode: $ride->transportMode->type ?? null,
                    ridePoolingCategory: $ride->ride_pool_category,
                    usageHours: $ride->duration
                );

                return [
                    'ride' => $ride,
                    'estimated_cost' => $pricing['total'] ?? null,
                    'pricing_breakdown' => $pricing,
                ];
            });
            $priceService = app(\App\Services\PricingService::class);

            $pricing = $priceService->calculateRidePoolingPrice(
                pickup: $validated['pickup'] ?? null,
                dropoff: $validated['dropoff'] ?? null,
                transportMode: $validated['transport_mode'] ?? null,
                ridePoolingCategory: $validated['ride_pool_category'] ?? null,
                usageHours: $validated['usage_hours'] ?? null
            );

            // Build canonical token for this user
            $userId = Auth::id();
            $token  = "ride_estimate_{$userId}_" . Str::uuid();

            // Duration can come from usage_hours or pricing response
            $duration = $pricing['duration'] ?? ($validated['usage_hours'] ?? null);

            // Save important values in cache for booking endpoint
            cache()->put("{$token}_cost", $pricing['total'] ?? null, 300);
            cache()->put("{$token}_pickup", $validated['pickup'] ?? null, 300);
            cache()->put("{$token}_dropoff", $validated['dropoff'] ?? null, 300);
            cache()->put("{$token}_duration", $duration, 300);

            return successResponse('Ride search completed.', [
                'available_drivers' => $results['drivers'],
                'drivers_found'     => $results['drivers_found'],
                'maps'              => $results['maps'],
                'rides'             => $ridesData,
                'estimate_token'    => $token,                   // <-- NEW
                'estimated_cost'    => $pricing['total'] ?? null, // <-- NEW
                'pricing_breakdown' => $pricing,                 // <-- NEW
                'pagination'        => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return failureResponse($ve->errors(), 422, 'validation_error', $ve);
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                500,
                'ride_search_error',
                $th
            );
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'driver_id'          => 'required|uuid|exists:drivers,id',
                'transport_mode_id'  => 'required|uuid|exists:transport_modes,id',
                'estimate_token'     => 'nullable|string',
            ]);

            $validated['user_id'] = Auth::id();

            $dto = BookRideDTO::fromArray($validated);

            $bookingResult = $this->bookRide->execute($dto);

            $ride = $bookingResult['ride'];
            $wasRecentlyCreated = $bookingResult['was_recently_created'];

            $message = $wasRecentlyCreated ? 'Ride booked successfully.' : 'Ride already booked.';
            $statusCode = $wasRecentlyCreated ? 201 : 200;

            return successResponse(
                $message,
                $ride,
                $statusCode
            );
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return failureResponse($ve->errors(), 422, 'validation_error', $ve);
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                500,
                'ride_booking_error',
                $th
            );
        }
    }

    public function activeRide()
    {
        try {
            $user = Auth::user();

            $activeRides = RidePool::with([
                'driver.transportModeDetails' // only vehicle info
            ])
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    RidePoolStatusEnums::BOOKED->value,
                    RidePoolStatusEnums::IN_TRANSIT->value,
                    RidePoolStatusEnums::RIDE_STARTED->value,
                ])
                ->latest('ride_date')
                ->get();

            if ($activeRides->isEmpty()) {
                return successResponse('No active rides found', [
                    'count' => 0,
                    'rides' => []
                ], 200);
            }

            $rides = $activeRides->map(function ($ride) {

                $latestLocation = \App\Models\DriverLocation::where('driver_id', $ride->driver_id)
                    ->latest()
                    ->first();

                $driver = $ride->driver;

                return [
                    'ride' => [
                        'id' => $ride->id,
                        'pickup_location' => $ride->pickup_location,
                        'dropoff_location' => $ride->dropoff_location,
                        'ride_date' => $ride->ride_date,
                        'status' => $ride->status,
                        'payment_status' => $ride->payment_status,
                        'estimated_cost' => $ride->estimated_cost,
                    ],
                    'driver' => [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'email' => $driver->email,
                        'phone' => $driver->phone,
                        'status' => $driver->status,

                        'vehicle' => $driver->transportModeDetails ? [
                            'id' => $driver->transportModeDetails->id,
                            'type' => $driver->transportModeDetails->type,
                            'manufacturer' => $driver->transportModeDetails->manufacturer,
                            'model' => $driver->transportModeDetails->model,
                            'registration_number' => $driver->transportModeDetails->registration_number,
                            'color' => $driver->transportModeDetails->color,
                            'passenger_capacity' => $driver->transportModeDetails->passenger_capacity,
                            'photo_url' => $driver->transportModeDetails->photo_url,
                            'registration_document_url' => $driver->transportModeDetails->registration_document_url,
                        ] : null,

                        'live_tracking' => $latestLocation ? [
                            'lat' => $latestLocation->latitude,
                            'lng' => $latestLocation->longitude,
                            'last_updated' => $latestLocation->updated_at,
                        ] : null,
                    ],
                ];
            });

            return successResponse('Active rides retrieved', [
                'count' => $rides->count(),
                'rides' => $rides,
            ], 200);
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                500,
                'active_ride_error',
                $th
            );
        }
    }

    public function cancelRide(RidePool $ride)
    {
        $rideStatus = $ride->status instanceof RidePoolStatusEnums
            ? $ride->status->value
            : $ride->status;

        $allowedStatuses = [
            RidePoolStatusEnums::BOOKED->value,
            RidePoolStatusEnums::IN_TRANSIT->value,
        ];

        if (!in_array($rideStatus, $allowedStatuses)) {
            $rideStartedStatus = RidePoolStatusEnums::RIDE_STARTED->value;
            if ($rideStatus === $rideStartedStatus) {
                return failureResponse(
                    "Ride has already started. You cannot cancel this ride.",
                    403
                );
            }

            return failureResponse(
                "Ride cannot be cancelled at this stage.",
                403
            );
        }


        $user   = $ride->user;
        $wallet = $user->wallet;

        DB::transaction(function () use ($ride, $wallet) {
            // Refund wallet
            if ($wallet) {
                $wallet->pending_balance -= $ride->estimated_cost;
                $wallet->available_balance += $ride->estimated_cost;
                $wallet->total_balance += $ride->estimated_cost;

                $wallet->save();
            }

            WalletTransaction::create([
                'wallet_id'   => $wallet->id,
                'user_id'     => $ride->user_id,
                'amount'      => $ride->estimated_cost,
                'type'        => WalletTransactionTypeEnums::CREDIT,
                'status'      => WalletTransactionStatusEnums::REVERSED,
                'method'      => WalletTransactionMethodEnums::WALLET,
                'description' => 'Ride cancellation refund',
                'reference'   => WalletService::generateTransactionReference(),
            ]);



            // Release driver if assigned
            if ($ride->driver_id) {
                $driver = $ride->driver;
                if ($driver) {
                    $driver->status = DriverStatusEnums::AVAILABLE->value;
                    $driver->save();

                    // Notify driver about cancellation
                    $driver->notify(new DriverRideCancelledNotification($ride));
                }
            }

            // Update ride status and payment status
            $ride->status = RidePoolStatusEnums::CANCELLED->value;
            $ride->payment_status = RidePoolPaymentStatusEnums::CANCELLED->value;
            $ride->save();

            // Notify user about cancellation
            $ride->user->notify(new RideCancelledNotification($ride));
        });

        return successResponse("Ride cancelled successfully.");
    }

    public function accept(Request $request, AcceptRideAction $acceptRide)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'ride_id' => 'required|uuid|exists:ride_pools,id',
            ]);

            // Get the authenticated user
            $user = Auth::user();

            // Retrieve the Driver model linked to the user
            $driver = $user?->driver;
            if (!$driver) {
                return response()->json([
                    'success' => false,
                    'message' => 'Driver profile not found for this user.',
                ], 403);
            }

            // Execute the ride acceptance
            $ride = $acceptRide->execute($driver, $request->ride_id);

            return successResponse(
                'Ride accepted successfully.',
                [
                    'ride'          => $ride,
                    'eta_minutes'   => $ride->eta_minutes,
                    'eta_timestamp' => $ride->eta_timestamp,
                ]
            );
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return failureResponse($ve->errors(), 422);
        } catch (\Exception $e) {
            return failureResponse($e->getMessage(), 400, null, $e);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500, null, $th);
        }
    }

    public function reject(Request $request, RejectRideAction $rejectRide)
    {
        try {
            $request->validate([
                'ride_id' => 'required|uuid|exists:ride_pools,id',
            ]);

            $user = Auth::user();
            $driver = $user?->driver;

            if (!$driver) {
                return failureResponse("Driver profile not found for this user.", 403);
            }

            $ride = $rejectRide->execute($driver, $request->ride_id);

            return successResponse(
                'Ride rejected successfully.',
                ['ride' => $ride]
            );
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return failureResponse($ve->errors(), 422);
        } catch (\Exception $e) {
            return failureResponse($e->getMessage(), 400);
        } catch (\Throwable $th) {
            return failureResponse($th->getMessage(), 500);
        }
    }

    public function driverActiveRides()
    {
        try {
            $driver = Auth::user()->driver;

            if (!$driver) {
                return failureResponse('Driver not found', 404, 'driver_not_found');
            }

            $activeRides = RidePool::with('user')
                ->where('driver_id', $driver->id)
                ->whereIn('status', [
                    RidePoolStatusEnums::RIDE_STARTED->value,
                    RidePoolStatusEnums::IN_TRANSIT->value,
                    RidePoolStatusEnums::ARRIVED->value,

                ])
                ->latest('ride_date')
                ->get();

            if ($activeRides->isEmpty()) {
                return successResponse('No active rides found', [
                    'count' => 0,
                    'ride' => []
                ], 200);
            }

            $rides = $activeRides->map(function ($ride) {
                return [
                    'ride' => [
                        'id' => $ride->id,
                        'pickup_location' => $ride->pickup_location,
                        'dropoff_location' => $ride->dropoff_location,
                        'ride_date' => $ride->ride_date,
                        'status' => $ride->status,
                        'payment_status' => $ride->payment_status,
                        'estimated_cost' => $ride->estimated_cost,
                    ],
                    'user' => $ride->user ? [
                        'id' => $ride->user->id,
                        'name' => $ride->user->name,
                        'email' => $ride->user->email,
                        'phone' => $ride->user->phone,
                    ] : null,
                ];
            });

            return successResponse('Driver active rides retrieved', [
                'count' => $rides->count(),
                'ride' => $rides,
            ], 200);
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                500,
                'driver_active_ride_error',
                $th
            );
        }
    }

    public function startRide(Request $request, StartRideAction $startRide)
    {
        try {
            $request->validate([
                'ride_id' => 'required|uuid|exists:ride_pools,id',
            ]);

            $user = Auth::user();
            $driver = $user?->driver;

            if (!$driver) {
                return failureResponse("Driver profile not found for this user.", 403);
            }

            $ride = $startRide->execute($driver, $request->ride_id);

            return successResponse("Ride started successfully.", $ride);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return failureResponse($ve->errors(), 422, "validation_error");
        } catch (\Exception $e) {
            return failureResponse($e->getMessage(), 400, "general_error");
        } catch (\Throwable $th) {
            return failureResponse("Unexpected error occurred.", 500, "server_error", $th);
        }
    }

    public function endRide(Request $request, EndRideAction $endRide)
    {
        try {
            $request->validate([
                'ride_id' => 'required|uuid|exists:ride_pools,id',
            ]);

            $user = Auth::user();
            $driver = $user?->driver;

            if (!$driver) {
                return failureResponse("Driver profile not found for this user.", 403);
            }

            $ride = $endRide->execute($driver, $request->ride_id);

            return successResponse("Ride ended successfully.", $ride);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return failureResponse($ve->errors(), 422, "validation_error");
        } catch (\Exception $e) {
            return failureResponse($e->getMessage(), 400, "general_error");
        } catch (\Throwable $th) {
            return failureResponse("Unexpected error occurred.", 500, "server_error", $th);
        }
    }

    public function bookedRides(Request $request, FetchDriverBookedRidesAction $action)
    {
        try {
            $driver = Auth::user()->driver;

            if (!$driver) {
                return failureResponse('Driver profile not found.', 404, 'not_driver');
            }

            $dto = FetchDriverBookedRidesDTO::fromRequest($request);

            $rides = $action->execute($driver->id, $dto);

            return successResponse('Booked rides fetched successfully.', $rides);
        } catch (\Throwable $e) {
            return failureResponse('Failed to fetch booked rides.', 500, 'booked_rides_error', $e);
        }
    }

    public function adminActiveRides(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $search  = $request->get('search');
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query = RidePool::with(['user', 'driver', 'transportMode'])
                ->whereIn('status', [
                    RidePoolStatusEnums::BOOKED,
                    RidePoolStatusEnums::RIDE_STARTED,
                    RidePoolStatusEnums::IN_TRANSIT,
                ]);

            if ($search) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->whereHas('user', function ($u) use ($search, $operator) {
                        $u->where('first_name', $operator, "%{$search}%")
                            ->orWhere('last_name', $operator, "%{$search}%")
                            ->orWhere('email', $operator, "%{$search}%");
                    })
                        ->orWhereHas('driver', function ($d) use ($search, $operator) {
                            $d->where('full_name', $operator, "%{$search}%")
                                ->orWhere('email', $operator, "%{$search}%");
                        })
                        ->orWhere('pickup_location', $operator, "%{$search}%")
                        ->orWhere('dropoff_location', $operator, "%{$search}%");
                });
            }

            if ($request->driver_id) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->transport_mode_id) {
                $query->where('transport_mode_id', $request->transport_mode_id);
            }

            $rides = $query->orderBy('ride_date', 'desc')->paginate($perPage);

            return successResponse("Active rides fetched successfully", $rides);
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch rides", 400, 'active_ride_error', $th);
        }
    }

    public function adminCancelRide(Request $request, RidePool $ride)
    {
        try {
            $admin = Auth::user();

            // Ensure only admins can do this
            if (!$admin || !$admin->hasRole('admin')) {
                return failureResponse("Unauthorized access. Admin role required.", 403);
            }

            $rideStatus = $ride->status instanceof RidePoolStatusEnums
                ? $ride->status->value
                : $ride->status;

            // ❗ Non-cancellable statuses
            $nonCancelableStatuses = [
                RidePoolStatusEnums::COMPLETED->value,
                RidePoolStatusEnums::CANCELLED->value,
            ];

            if (in_array($rideStatus, $nonCancelableStatuses)) {
                return failureResponse("Ride cannot be cancelled at this stage.", 403);
            }

            $user   = $ride->user;
            $wallet = $user?->wallet;

            DB::transaction(function () use ($ride, $wallet, $admin) {

                // Refund wallet including discount if applied
                if ($wallet) {
                    $refundAmount = $ride->estimated_cost + ($ride->discount_cost ?? 0);

                    $wallet->pending_balance -= $refundAmount;
                    $wallet->available_balance += $refundAmount;
                    $wallet->total_balance += $refundAmount;
                    $wallet->save();
                }

                // Release driver if assigned
                if ($ride->driver_id) {
                    $driver = $ride->driver;

                    if ($driver) {
                        $driver->status = DriverStatusEnums::AVAILABLE->value;
                        $driver->save();

                        $driver->notify(new DriverRideCancelledNotification($ride));
                    }
                }

                // Update status & payment
                $ride->status = RidePoolStatusEnums::CANCELLED->value;
                $ride->payment_status = RidePoolPaymentStatusEnums::CANCELLED->value;
                $ride->cancelled_by = "admin";
                $ride->cancelled_by_admin_id = $admin->id;
                $ride->save();

                // Notify user
                $ride->user->notify(new RideCancelledByAdminNotification($ride));
            });

            return successResponse("Ride cancelled successfully by admin.");
        } catch (\Throwable $th) {
            return failureResponse("Failed to cancel ride", 400, "admin_cancel_error", $th);
        }
    }

    public function rideList(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $ridePools = RidePool::query()
            ->with(['user', 'driver', 'transportMode'])

            // 🔍 Filters
            ->when(
                $request->status,
                fn($q) =>
                $q->where('status', $request->status)
            )

            ->when(
                $request->payment_status,
                fn($q) =>
                $q->where('payment_status', $request->payment_status)
            )

            ->when(
                $request->user_id,
                fn($q) =>
                $q->where('user_id', $request->user_id)
            )

            ->when(
                $request->driver_id,
                fn($q) =>
                $q->where('driver_id', $request->driver_id)
            )

            ->when(
                $request->from_date && $request->to_date,
                fn($q) =>
                $q->whereBetween('ride_date', [
                    $request->from_date,
                    $request->to_date
                ])
            )

            ->when(
                $request->search,
                fn($q) =>
                $q->where(function ($qq) use ($request) {
                    $qq->where('pickup_location', 'like', "%{$request->search}%")
                        ->orWhere('dropoff_location', 'like', "%{$request->search}%");
                })
            )

            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Ride pools fetched successfully.',
            'data' => $ridePools
        ]);
    }

    public function ridePoolRevenue(Request $request)
    {
        $query = RidePool::query()
            ->where('status', RidePoolStatusEnums::RIDE_ENDED)
            ->where('payment_status', RidePoolPaymentStatusEnums::PAID);

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $totalRevenue = (float) $query->sum('estimated_cost');

        if ($totalRevenue <= 0) {
            return successResponse('No revenue yet', [
                'total_revenue' => 0,
                'breakdown' => [],
            ]);
        }

        // Commission percentages
        $driverPercent   = Commission::where('role', 'driver')->latest()->value('percentage') ?? 10;
        $partnerPercent  = Commission::where('role', 'partner')->latest()->value('percentage') ?? 10;
        $investorPercent = Commission::where('role', 'investor')->latest()->value('percentage') ?? 30;

        // Totals
        $driverCommissionTotal   = 0;
        $partnerCommissionTotal  = 0;
        $investorCommissionTotal = 0;
        $platformCommissionTotal = 0;

        $rides = $query
            ->with(['transportMode.partner'])
            ->get();

        foreach ($rides as $ride) {
            $amount = (float) $ride->estimated_cost;

            $driver = $amount * ($driverPercent / 100);

            if ($ride->transportMode?->partner) {
                $partner  = $amount * ($partnerPercent / 100);
                $investor = 0;
            } else {
                $partner  = 0;
                $investor = $amount * ($investorPercent / 100);
            }

            $platform = $amount - ($driver + $partner + $investor);

            $driverCommissionTotal   += $driver;
            $partnerCommissionTotal  += $partner;
            $investorCommissionTotal += $investor;
            $platformCommissionTotal += $platform;
        }

        return successResponse('Ride pool revenue summary', [
            'total_revenue' => round($totalRevenue, 2),
            'breakdown' => [
                'driver_commission'   => round($driverCommissionTotal, 2),
                'partner_commission'  => round($partnerCommissionTotal, 2),
                'investor_commission' => round($investorCommissionTotal, 2),
                'platform_commission' => round($platformCommissionTotal, 2),
            ],
            'rides_count' => $rides->count(),
        ]);
    }
}

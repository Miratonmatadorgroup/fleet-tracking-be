<?php

namespace App\Http\Controllers\Api;



use App\Models\User;
use App\Models\Driver;
use App\Models\Commission;
use Illuminate\Http\Request;
use App\Services\NubapiService;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Services\VfdBankService;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\FlutterWaveService;
use App\Services\MonnifyBankService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Services\ExternalBankService;
use App\DTOs\Driver\AcceptDeliveryDTO;
use App\DTOs\Driver\AppliedDriversDTO;
use App\DTOs\Driver\DriverEarningsDTO;
use App\DTOs\Driver\ShowDirectionsDTO;
use App\Services\NigerianBanksService;
use App\DTOs\Driver\ApprovedDriversDTO;
use App\DTOs\Driver\MarkAsDeliveredDTO;
use App\Mail\DriverApplicationReceived;
use App\DTOs\Driver\DriverApplicationDTO;
use App\DTOs\Ratings\GetDriverRatingsDTO;
use App\Services\BankAccountNameResolver;
use App\DTOs\Driver\AssignedDeliveriesDTO;
use App\Enums\DriverApplicationStatusEnums;
use App\Actions\Driver\AcceptDeliveryAction;
use App\Actions\Driver\ShowDirectionsAction;
use App\DTOs\Driver\ApplyAsDriverByAdminDTO;
use App\DTOs\Driver\DriverDeliveryCountsDTO;
use App\DTOs\Driver\GetDriversByCategoryDTO;
use App\Events\Driver\DriverEarningsFetched;
use Illuminate\Support\Facades\Notification;
use App\Actions\Driver\AdminListDriversAction;
use App\Actions\Driver\GetDriverEarningsAction;
use App\Actions\Ratings\GetDriverRatingsAction;
use App\DTOs\Driver\ConfirmDeliveryByWaybillDTO;
use App\Events\Driver\DriverRequestedDirections;
use App\Actions\Driver\FetchAppliedDriversAction;
use App\Actions\Driver\ApplyAsDriverByAdminAction;
use App\Actions\Driver\FetchApprovedDriversAction;
use App\Actions\Driver\GetDriversByCategoryAction;
use App\Actions\Ratings\GetAllDriverRatingsAction;
use App\DTOs\Driver\AdminApproveOrRejectDriverDTO;
use App\Actions\Driver\GetAssignedDeliveriesAction;
use App\Actions\Driver\GetDriverDeliveryCountsAction;
use App\Actions\Driver\MarkDeliveryAsDeliveredAction;
use App\Actions\Driver\SubmitDriverApplicationAction;
use App\Actions\Driver\ConfirmDeliveryByWaybillAction;
use App\Actions\Driver\AdminApproveOrRejectDriverAction;
use App\Notifications\Admin\NewDriverApplicationNotification;

class DriverController extends Controller
{
    public function driverApplicationForm(Request $request, SubmitDriverApplicationAction $action, TwilioService $twilio, TermiiService $termii)
    {
        $user = Auth::user();

        if (Driver::where('user_id', $user->id)->exists()) {
            return failureResponse('You have already submitted a driver application.', 403);
        }

        if (empty($user->phone)) {
            return failureResponse('Please kindly update your profile with your phone number before applying.', 422);
        }

        $dto = DriverApplicationDTO::fromRequest($request);
        $driver = $action->execute($dto, $user);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        //In-app Notification for user
        $user->notify(new \App\Notifications\User\DriverApplicationReceivedNotification($driver->name));


        // Notify user
        if ($driver->email) {
            try {
                Mail::to($driver->email)->send(new DriverApplicationReceived($driver));
            } catch (\Throwable $e) {
                logError("Driver mail failed", $e);
            }
        }

        // Notify admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new NewDriverApplicationNotification($driver));

        // Send SMS and WhatsApp
        $message = "Hi {$driver->name}, your LoopFreight driver application has been received. We’ll review and get back to you shortly.";

        if ($driver->phone) {
            try {
                $termii->sendSms($driver->phone, $message);
            } catch (\Throwable $e) {
                logError("Termii SMS failed", $e);
            }

            try {
                $twilio->sendWhatsAppMessage($driver->whatsapp_number, $message);
            } catch (\Throwable $e) {
                logError("Twilio WhatsApp failed", $e);
            }
        }

        return successResponse('Driver application submitted successfully', $driver);
    }

    public function verifyBankAccount(
        Request $request,
        BankAccountNameResolver $resolver
    ) {
        $request->validate([
            'bank_code'      => 'required|string',
            'account_number' => 'required|string|max:20',
        ]);

        $result = $resolver->resolve(
            $request->input('account_number'),
            $request->input('bank_code')
        );

        if (! $result['success'] || empty($result['account_name'])) {
            return failureResponse(
                $result['error'] ?? 'Unable to verify bank account.',
                422
            );
        }

        $accountName = $result['account_name'];
        $user        = $request->user();

        if (! $this->namesLooselyMatch($user->name, $accountName)) {
            return response()->json([
                'status'        => false,
                'message'       => 'Account name does not reasonably match profile name.',
                'account_name'  => $accountName,
                'profile_name'  => $user->name,
            ], 422);
        }

        return successResponse('Bank account verified successfully.', [
            'account_name' => $accountName,
            'provider'     => $result['provider'],
        ]);
    }

    public function listBanks(ExternalBankService $service)
    {
        $banks = $service->lifeBankListEnquiry();

        if (!$banks) {
            return failureResponse('Could not fetch banks.', 500);
        }

        return successResponse(
            'Banks fetched successfully',
            $banks['banks'] ?? []
        );
    }




    public function adminApproveOrRejectDriver(Request $request, AdminApproveOrRejectDriverAction $action)
    {
        $dto = AdminApproveOrRejectDriverDTO::fromRequest($request);

        $driver = $action->execute($dto);

        return successResponse("Driver application has been {$dto->action}ed.", $driver);
    }

    public function assignedDeliveries(
        Request $request,
        GetAssignedDeliveriesAction $deliveriesAction
    ) {
        try {
            $dto = AssignedDeliveriesDTO::fromAuth();
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            $deliveries = $deliveriesAction->execute($dto, $perPage, $search);

            // Hide waybill_number from each delivery
            $deliveries->getCollection()->transform(function ($delivery) {
                unset($delivery->waybill_number);
                return $delivery;
            });

            return successResponse(
                "All deliveries assigned to the driver retrieved.",
                $deliveries
            );
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch assigned deliveries.", 500, 'driver_assigned_deliveries_error', $th);
        }
    }


    public function deliveryCounts(GetDriverDeliveryCountsAction $action)
    {

        try {
            $dto = DriverDeliveryCountsDTO::fromAuth();
            $counts = $action->execute($dto);

            return successResponse(
                "Driver delivery counts retrieved successfully.",
                $counts
            );
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch delivery counts.", 500, 'driver_delivery_counts_error', $th);
        }
    }

    public function acceptDelivery(string $id, AcceptDeliveryAction $action)
    {
        try {
            $dto = AcceptDeliveryDTO::fromId($id);
            $delivery = $action->execute($dto);

            return successResponse("Delivery accepted. Status updated to in transit.", $delivery);
        } catch (\Throwable $th) {
            return failureResponse("Failed to accept delivery", 422, 'accept_delivery_error', $th);
        }
    }

    public function markAsDelivered($id, MarkDeliveryAsDeliveredAction $action)
    {
        try {
            $dto = MarkAsDeliveredDTO::from($id);
            $delivery = $action->execute($dto);

            return successResponse(
                "Delivery has been delivered. Awaiting sender confirmation.",
                $delivery
            );
        } catch (\Exception $e) {
            return failureResponse(
                $e->getMessage(),
                400,
                'mark_as_delivered_error'
            );
        } catch (\Throwable $th) {

            return failureResponse(
                'Failed to mark delivery as delivered.',
                500,
                'server_error',
                $th
            );
        }
    }



    public function adminViewListDrivers(Request $request, AdminListDriversAction $action)
    {
        try {
            $search  = $request->input('search');   // e.g. ?search=mike
            $perPage = $request->input('per_page', 10);

            $drivers = $action->execute($search, $perPage);

            $drivers->getCollection()->transform(function ($driver) {
                return [
                    'id'                    => $driver->id,
                    'user_id'               => $driver->user_id,
                    'name'                  => $driver->name,
                    'email'                 => $driver->email,
                    'phone'                 => $driver->phone,
                    'whatsapp_number'       => $driver->whatsapp_number,
                    'gender'                => $driver->gender,
                    'address'               => $driver->address,
                    'status'                => $driver->status,
                    'application_status'    => $driver->application_status,
                    'transport_mode'        => $driver->transport_mode,
                    'years_of_experience'   => $driver->years_of_experience,
                    'driver_license_number' => $driver->driver_license_number,
                    'license_expiry_date'   => $driver->license_expiry_date,
                    'bank_name'             => $driver->bank_name,
                    'account_name'          => $driver->account_name,
                    'account_number'        => $driver->account_number,
                    'next_of_kin_name'      => $driver->next_of_kin_name,
                    'next_of_kin_phone'     => $driver->next_of_kin_phone,
                    'created_at'            => $driver->created_at,
                    'is_flagged'            => $driver->is_flagged,
                    'flag_reason'          => $driver->flag_reason,
                    'flagged_by'            => $driver->flagged_by,

                    'license_image_url'     => $driver->license_image_url,
                    'national_id_image_url' => $driver->national_id_image_url,
                    'profile_photo_url'     => $driver->profile_photo_url,
                ];
            });

            return successResponse('Driver list fetched successfully', $drivers);
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to fetch drivers.',
                500,
                'driver_fetch_error',
                $th
            );
        }
    }

    public function applyAsDriverByAdmin(Request $request, TwilioService $twilio, TermiiService $termii)
    {
        $dto = ApplyAsDriverByAdminDTO::fromRequest($request);
        $driver = app(ApplyAsDriverByAdminAction::class)->execute($dto, $twilio, $termii);

        return successResponse('Driver application submitted successfully', $driver);
    }

    public function approvedDrivers(Request $request, FetchApprovedDriversAction $action)
    {
        try {
            $dto = ApprovedDriversDTO::fromRequest($request);
            $drivers = $action->execute($dto);

            return successResponse('Approved drivers fetched successfully.', $drivers);
        } catch (\Throwable $e) {
            return failureResponse('Failed to fetch approved drivers.', 500);
        }
    }


    public function appliedDrivers(Request $request, FetchAppliedDriversAction $action)
    {
        try {
            $dto = AppliedDriversDTO::fromRequest($request);
            $search = $request->input('search');

            $drivers = $action->execute($dto, $search);

            return successResponse('Drivers applications fetched successfully.', $drivers);
        } catch (\Throwable $e) {
            return failureResponse('Failed to fetch drivers applications.', 500, 'driver_fetch_error', $e);
        }
    }

    public function driverCount()
    {
        try {
            $count = Driver::where('application_status', DriverApplicationStatusEnums::APPROVED)->count();

            return successResponse('Total number of approved drivers fetched successfully', [
                'total_approved_drivers' => $count
            ]);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch approved driver count', 500, 'count_error', $th);
        }
    }

    public function getDriversByCategory(Request $request)
    {
        $dto = GetDriversByCategoryDTO::fromRequest($request);

        $drivers = GetDriversByCategoryAction::execute($dto);

        return response()->json($drivers);
    }

    public function __invoke(Request $request, GetDriverEarningsAction $action)
    {
        $user = Auth::user();

        if (!$user || !$user->driver) {
            return failureResponse("Unauthorized. Driver account required.", 403);
        }

        $driverCommission = Commission::where('role', 'driver')->value('percentage') ?? 0;

        $dto = new DriverEarningsDTO(
            driverId: $user->driver->id,
            commissionPercentage: $driverCommission
        );

        $earnings = $action->execute($dto);

        event(new DriverEarningsFetched($dto->driverId, $earnings));

        return successResponse("Driver earnings fetched successfully", $earnings);
    }

    public function showDirections(Request $request, ShowDirectionsAction $action)
    {
        try {
            $dto = ShowDirectionsDTO::fromRequest($request->only('delivery_id', 'pickup_location'));
            $data = $action->execute($dto);

            event(new DriverRequestedDirections($dto->user, $dto->pickupLocation));

            return successResponse("Directions fetched successfully.", $data);
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch directions", 422, 'directions_error', $th);
        }
    }

    public function getDriverRatings(Request $request, GetDriverRatingsAction $action)
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('driver')) {
            return failureResponse("Unauthorized. Only drivers can view ratings.", 403);
        }
        try {
            $dto = new GetDriverRatingsDTO(
                driverId: $user->id,
                perPage: $request->get('per_page', 10),
            );

            $ratings = $action->execute($dto);

            return successResponse("Driver ratings retrieved successfully.", $ratings);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 400);
        }
    }
    public function getAllDriverRatings(Request $request, GetAllDriverRatingsAction $action)
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('admin')) {
            return failureResponse("Unauthorized. Only admins can view all driver ratings.", 403);
        }

        try {
            $ratings = $action->execute(
                perPage: $request->get('per_page', 10),
                search: $request->query('search') // Pass search to action
            );

            return successResponse("All driver ratings retrieved successfully.", $ratings);
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 400);
        }
    }

    public function confirmDeliveryByDriver(Request $request, TwilioService $twilio, TermiiService $termii, ConfirmDeliveryByWaybillAction $action)
    {
        $driver = Auth::user();

        if (!$driver || !$driver->hasRole('driver')) {
            return failureResponse("Unauthorized. You must be logged in as a driver.", 403);
        }

        $request->validate([
            'waybill_number' => 'required|string',
        ]);

        try {
            $dto = new ConfirmDeliveryByWaybillDTO(
                deliveryId: $request->route('id'),
                driverId: $driver->driver->id,
                waybillNumber: $request->input('waybill_number')
            );

            $action->execute($dto, $twilio, $termii);

            return successResponse("Delivery confirmed successfully.");
        } catch (\Throwable $e) {
            return failureResponse($e->getMessage(), 400);
        }
    }

    public function verifyAccountName(
        Request $request,
        BankAccountNameResolver $resolver
    ) {
        $validated = $request->validate([
            'account_number' => 'required|string|digits:10',
            'bank_code'      => 'required|string',
        ]);

        try {
            $result = $resolver->resolve(
                $validated['account_number'],
                $validated['bank_code']
            );

            if (! $result['success'] || empty($result['account_name'])) {
                throw new \Exception(
                    $result['error'] ?? 'Unable to verify account name'
                );
            }

            return successResponse(
                'Account verified successfully',
                [
                    'account_name' => $result['account_name'],
                    'provider'     => $result['provider'],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Bank name enquiry failed', [
                'error' => $e->getMessage(),
            ]);

            return failureResponse(
                $e->getMessage(),
                422,
                'bank_name_enquiry_failed'
            );
        }
    }

    private function namesLooselyMatch(string $profileName, string $bankName): bool
    {
        $normalize = function ($name) {
            $name = strtoupper($name);

            // Remove multiple spaces
            $name = preg_replace('/\s+/', ' ', $name);

            // Remove special characters
            $name = preg_replace('/[^A-Z\s]/', '', $name);

            return trim($name);
        };

        $profile = $normalize($profileName);
        $bank    = $normalize($bankName);

        $profileParts = explode(' ', $profile);
        $bankParts    = explode(' ', $bank);

        // Must match at least FIRST + LAST name
        $matches = array_intersect($profileParts, $bankParts);

        return count($matches) >= 2;
    }
}

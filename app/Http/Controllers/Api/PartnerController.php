<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Partner;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Validation\Rule;
use App\Enums\DriverStatusEnums;
use App\Services\SmileIdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\DTOs\Partner\PartnerIndexDTO;
use App\Mail\PartnerApplicationStatus;
use App\DTOs\Partner\AddFleetMemberDTO;
use App\Mail\DriverApplicationDecision;
use App\Mail\DriverApplicationReceived;
use Illuminate\Support\Facades\Storage;
use App\DTOs\Partner\AppliedPartnersDTO;
use App\Mail\PartnerApplicationReceived;
use App\DTOs\Partner\ApprovedPartnersDTO;
use App\Enums\TransportModeCategoryEnums;
use App\Mail\FleetApplicationUnderReview;
use App\Mail\PartnerLinkDriverToTransMode;
use App\DTOs\Partner\PartnerApplicationDTO;
use App\Enums\DriverApplicationStatusEnums;
use App\Enums\PartnerApplicationStatusEnums;
use Illuminate\Support\Facades\Notification;
use App\Actions\Partner\AddFleetMemberAction;
use App\Actions\Partner\GetPartnerListAction;
use App\Events\Partner\PartnerEarningsViewed;
use App\DTOs\Partner\ViewPartnerDeliveriesDTO;
use Illuminate\Validation\ValidationException;
use App\Events\Partner\PartnerListFetchedEvent;
use App\Actions\Partner\GetPartnerDriversAction;
use App\Actions\Partner\GetPartnerEarningsAction;
use App\Actions\Partner\PartnerApplicationAction;
use App\DTOs\Partner\ReviewPartnerApplicationDTO;
use App\DTOs\Driver\AdminApproveOrRejectDriverDTO;
use App\Actions\Partner\FetchAppliedPartnersAction;
use App\Actions\Partner\FetchApprovedPartnersAction;
use App\Actions\Partner\ViewPartnerDeliveriesAction;
use App\Actions\Partner\FleetApplicationDecisionAction;
use App\Actions\Partner\GetPartnerTransportModesAction;
use App\Actions\Partner\ReviewPartnerApplicationAction;
use App\Events\Partner\PartnerApplicationReviewedEvent;
use App\Notifications\User\PartnerLinkDriverNotification;
use App\Notifications\Admin\NewFleetApplicationNotification;
use App\Notifications\Admin\NewDriverApplicationNotification;
use App\Notifications\User\FleetAdditionReceivedNotification;
use App\Notifications\Admin\NewPartnerApplicationNotification;
use App\Notifications\User\DriverApplicationDecisionNotification;
use App\Notifications\User\PartnerApplicationReceivedNotification;


class PartnerController extends Controller
{

    // NORMAL FLOW WITHOUT REAL DRIVER LICESNE VERIFICATION OR NIN
    public function partnerApplicationForm(Request $request, PartnerApplicationAction $action, TwilioService $twilio, TermiiService $termii): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return failureResponse('Unauthorized. Please login to continue.', 403);
        }

        $request->validate([
            'partner_info.bank_code'             => 'required|string',
            'partner_info.account_number'        => 'required|string',
            'transport_info.image'               => 'required|file',
            'transport_info.registration_document' => 'required|file',
            'transport_info.category'            => ['required', Rule::in(array_column(TransportModeCategoryEnums::cases(), 'value'))],
            'driver_identifier'                  => 'required|string',
        ]);

        $partner = $user->partner;
        if ($partner && $partner->application_status === PartnerApplicationStatusEnums::REVIEW) {
            return failureResponse('You already have an ongoing application under review.', 422);
        }

        //DTO no longer contains full driverInfo except identifier
        $dto = new PartnerApplicationDTO(
            $user,
            array_merge(
                $request->input('partner_info', []),
                $request->file('partner_info', [])
            ),
            array_merge(
                $request->input('transport_info', []),
                $request->file('transport_info', [])
            ),
            ['identifier' => $request->input('driver_identifier')]
        );

        $result = $action->execute($dto, $user);

        $partner  = $result['partner'];
        $driver   = $result['driver'];
        $transport = $result['transport'];

        /** @var \App\Models\User $user */
        $user = Auth::user();

        //In-app Notification for user as a partner
        $user->notify(new PartnerApplicationReceivedNotification($user->name));

        // In-app Notification for user as a driver
        $driverUser = $driver->user;
        $driverUser->notify(new PartnerLinkDriverNotification($driverUser->name));

        // Emails
        try {
            if ($user->email) Mail::to($user->email)->send(new PartnerApplicationReceived($partner));
            if ($driverUser->email) Mail::to($driverUser->email)->send(new PartnerLinkDriverToTransMode($driver));
        } catch (\Throwable $e) {
            logError("Email notification failed", $e);
        }

        // Notify all admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new NewPartnerApplicationNotification($partner));

        // SMS/WhatsApp notifications
        $driverMsg  = "Hi {$driverUser->name}, a new LoopFreight partner has linked you to a transport mode.";
        $partnerMsg = "Hi {$user->name}, your LoopFreight partner application has been received. We’ll review and get back to you shortly.";

        foreach (['phone', 'whatsapp_number'] as $field) {
            if ($driverUser->{$field}) {
                try {
                    $termii->sendSms($driverUser->{$field}, $driverMsg);
                } catch (\Throwable $e) {
                }
                try {
                    $twilio->sendWhatsAppMessage($driverUser->{$field}, $driverMsg);
                } catch (\Throwable $e) {
                }
            }
            if ($user->{$field}) {
                try {
                    $termii->sendSms($user->{$field}, $partnerMsg);
                } catch (\Throwable $e) {
                }
                try {
                    $twilio->sendWhatsAppMessage($user->{$field}, $partnerMsg);
                } catch (\Throwable $e) {
                }
            }
        }

        return successResponse('Partner application submitted successfully', [
            'partner'        => $partner,
            'driver'         => $driver,
            'transport_mode' => $transport,
        ]);
    }

    public function checkPartnerApplicationStatus(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return failureResponse('Unauthorized. Please login to continue.', 403);
        }

        $partner = $user->partner;

        if (! $partner) {
            return successResponse('No partner application found.', [
                'has_applied' => false,
                'application_status' => null,
            ]);
        }

        return successResponse('Partner application found.', [
            'has_applied' => true,
            'application_status' => $partner->application_status,
            'status_meaning' => match ($partner->application_status) {
                PartnerApplicationStatusEnums::REVIEW => 'Application under review',
                PartnerApplicationStatusEnums::APPROVED => 'Application approved',
                PartnerApplicationStatusEnums::REJECTED => 'Application rejected',
                default => 'Unknown status',
            },
        ]);
    }

    // NORMAL FLOW WITHOUT REAL DRIVERS LICENSE AND NIN
    public function addFleetMember(Request $request, TwilioService $twilio, TermiiService $termii, AddFleetMemberAction $action ) {
        $user = Auth::user();

        if (!$user) {
            return failureResponse('Unauthorized. Please login to continue.', 403);
        }

        if (!$user->hasRole('partner')) {
            return failureResponse('Only partners can access this resource.', 403);
        }

        $partner = $user->partner;

        if (!$partner || $partner->application_status !== PartnerApplicationStatusEnums::APPROVED) {
            return failureResponse('Only approved partners can add new fleet members.', 403);
        }

        $request->validate([
            'transport_info.image'                 => 'required|file',
            'transport_info.registration_document' => 'required|file',
            'transport_info.category'              => ['required', Rule::in(array_column(TransportModeCategoryEnums::cases(), 'value'))],
            'driver_identifier'                    => 'required|string',
        ]);

        try {
            $dto = AddFleetMemberDTO::fromRequest(
                $user,
                array_merge($request->input('transport_info', []), $request->file('transport_info', [])),
                ['identifier' => $request->input('driver_identifier')]
            );

            $result = $action->execute($dto, $user);
        } catch (\Throwable $e) {
            logError('Failed to add fleet member', $e);
            return failureResponse($e->getMessage(), 422);
        }

        $driver    = $result['driver'];
        $transport = $result['transport'];

        /** @var \App\Models\User $user */
        //In-app Notification for user as a partner
        $user->notify(new FleetAdditionReceivedNotification($user->name));

        if ($driver->user) {
            $driverUser = $driver->user;
            $driverUser->notify(new PartnerLinkDriverNotification($driverUser->name));

            if ($driverUser->email) {
                Mail::to($driverUser->email)->send(new PartnerLinkDriverToTransMode($driverUser, $transport));
            }
        }

        if ($user->email) {
            Mail::to($user->email)->send(new FleetApplicationUnderReview($driver, $transport));
        }

        // Notify admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new \App\Notifications\Admin\NewFleetApplicationNotification($driver, $transport, $user));

        // SMS/WhatsApp
        $driverMsg = "Hi {$driver->name}, you have been linked to a new LoopFreight transport fleet under {$user->name}.";
        $partnerMsg = "Hi {$user->name}, your new LoopFreight fleet member ({$driver->name}) has been added and is pending approval.";

        foreach (['phone', 'whatsapp_number'] as $field) {
            if ($driver->user?->{$field}) {
                try {
                    $termii->sendSms($driver->user->{$field}, $driverMsg);
                    $twilio->sendWhatsAppMessage($driver->user->{$field}, $driverMsg);
                } catch (\Throwable $e) {
                    logError("Failed to notify driver via Twilio or Termii", $e);
                }
            }
            if ($user->{$field}) {
                try {
                    $termii->sendSms($user->{$field}, $partnerMsg);
                    $twilio->sendWhatsAppMessage($user->{$field}, $partnerMsg);
                } catch (\Throwable $e) {
                    logError("Failed to notify partner via Twilio or Termii", $e);
                }
            }
        }

        return successResponse('Fleet member added successfully', [
            'driver'    => $driver,
            'transport' => $transport,
        ]);
    }

    public function approveFleetApplication(Request $request, FleetApplicationDecisionAction $action)
    {
        $dto = AdminApproveOrRejectDriverDTO::fromRequest($request);

        try {
            $result = $action->execute($dto);

            return successResponse(
                $dto->action === 'approve'
                    ? 'Fleet application approved successfully'
                    : 'Fleet application rejected',
                [
                    'driver' => $result['driver'],
                    'transport' => $result['transport'] ?? null
                ]
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Fleet application approval failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return failureResponse('An error occurred during fleet application approval.', 500);
        }
    }

    public function reviewPartnerApplication(Request $request, ReviewPartnerApplicationAction $action, TwilioService $twilio, TermiiService $termii)
    {
        try {
            $dto = ReviewPartnerApplicationDTO::fromRequest($request);

            $partner = $action->execute($dto);

            $this->handlePartnerApplicationReviewedEvent(
                new PartnerApplicationReviewedEvent($partner, $dto->applicationStatus === PartnerApplicationStatusEnums::APPROVED),
                $twilio, $termii
            );

            return successResponse('Partner application has been ' . strtolower($dto->applicationStatus->value) . '.');
        } catch (\Exception $e) {
            return failureResponse($e->getMessage(), 403, 'review_denied');
        } catch (\Throwable $th) {
            return failureResponse('Failed to review partner application.', 500, 'review_error', $th);
        }
    }

    public function index(Request $request, GetPartnerListAction $action)
    {
        try {
            $dto = PartnerIndexDTO::fromRequest($request);
            $search = $request->input('search');

            $partners = $action->execute($dto, $search);

            $this->handlePartnerListFetchedEvent(
                new PartnerListFetchedEvent(
                    $partners,
                    $partners->total()
                )
            );

            return successResponse('Partner list fetched successfully', $partners);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch partners', 500, 'fetch_error', $th);
        }
    }

    public function approvedPartners(Request $request, FetchApprovedPartnersAction $action)
    {
        try {
            $dto = ApprovedPartnersDTO::fromRequest($request);
            $partners = $action->execute($dto);

            return successResponse('Approved partners fetched successfully.', $partners);
        } catch (\Throwable $e) {
            return failureResponse('Failed to fetch approved partners.', 500);
        }
    }

    public function appliedPartners(Request $request, FetchAppliedPartnersAction $action)
    {
        try {
            $dto = AppliedPartnersDTO::fromRequest($request);
            $search = $request->input('search');

            $partners = $action->execute($dto, $search);

            return successResponse('Applied partners fetched successfully.', $partners);
        } catch (\Throwable $e) {
            return failureResponse('Failed to fetch applied partners.', 500, 'partner_fetch_error', $e);
        }
    }

    public function count()
    {
        try {
            $count = Partner::where('application_status', PartnerApplicationStatusEnums::APPROVED)->count();

            return successResponse('Total number of partners fetched successfully', [
                'total_partners' => $count
            ]);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch partner count', 500, 'count_error', $th);
        }
    }

    public function partnerDeliveries(ViewPartnerDeliveriesAction $action)
    {
        try {
            $dto = ViewPartnerDeliveriesDTO::fromAuth();
            $search = request()->query('search');
            $deliveries = $action->execute($dto, $search);

            return successResponse('Deliveries fetched successfully.', $deliveries);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch partner deliveries.', 500, 'partner_deliveries_error', $th);
        }
    }

    public function getPartnerDrivers(Request $request, GetPartnerDriversAction $action)
    {
        $user = Auth::user();

        if (!$user) {
            return failureResponse('Unauthorized. Please login to continue.', 403);
        }

        if (!$user->hasRole('partner')) {
            return failureResponse('Only partners can access this resource.', 403);
        }

        $partner = $user->partner;

        if (!$partner) {
            return failureResponse('Partner record not found.', 404);
        }

        $perPage = $request->get('per_page', 10);
        $search  = $request->get('search');

        $dto = $action->execute($partner, $perPage, $search);

        return successResponse('Partner drivers retrieved successfully', [
            'total_drivers' => $dto->totalDrivers,
            'data'          => $dto->drivers,
            'pagination'    => $dto->pagination,
        ]);
    }

    public function getPartnerTransportModes(Request $request, GetPartnerTransportModesAction $action)
    {
        $user = Auth::user();

        if (!$user) {
            return failureResponse('Unauthorized. Please login to continue.', 403);
        }

        if (!$user->hasRole('partner')) {
            return failureResponse('Only partners can access this resource.', 403);
        }

        $partner = $user->partner;

        if (!$partner) {
            return failureResponse('Partner record not found.', 404);
        }

        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');

        $dto = $action->execute($partner, $perPage, $search);

        return successResponse('Partner transport modes retrieved successfully', [
            'total_transport_modes' => $dto->totalTransportModes,
            'data' => $dto->transportModes,
            'pagination' => $dto->pagination,
        ]);
    }

    protected function handlePartnerListFetchedEvent(PartnerListFetchedEvent $event)
    {

        logger()->info('Partner list fetched', [
            'total' => $event->total,
            'page' => $event->partners->currentPage()
        ]);
    }

    protected function handlePartnerApplicationReviewedEvent(PartnerApplicationReviewedEvent $event, TwilioService $twilio, TermiiService $termii)
    {
        $partner = $event->partner;
        $approved = $event->approved;

        $partnerMessage = $approved
            ? "Hi {$partner->full_name}, your LoopFreight Partner application has been APPROVED. You’re now active."
            : "Hi {$partner->full_name}, we’re sorry—your LoopFreight Partner application has been REJECTED. Contact support for more info.";

        //Notify Partner
        // In-app Notification
        if ($partner->user) {
            $partner->user->notify(
                new \App\Notifications\User\PartnerApplicationDecisionNotification($partner, $approved)
            );
        }

        try {
            Mail::to($partner->email)->send(new PartnerApplicationStatus($partner, $approved));
        } catch (\Throwable $e) {
            logError("Failed to send email to partner", $e);
        }

        try {
            if (!empty($partner->phone)) {
                $termii->sendSms($partner->phone, $partnerMessage);
            }
        } catch (\Throwable $e) {
            logError("Failed to send SMS to partner", $e);
        }

        try {
            if (!empty($partner->whatsapp_number)) {
                $twilio->sendWhatsAppMessage($partner->whatsapp_number, $partnerMessage);
            }
        } catch (\Throwable $e) {
            logError("Failed to send WhatsApp to partner", $e);
        }

        //Always check drivers (whether approved or rejected)
        foreach ($partner->transportModes as $mode) {
            $driver = $mode->driver ?? null;

            if ($driver) {
                $driver->application_status = $approved
                    ? DriverApplicationStatusEnums::APPROVED
                    : DriverApplicationStatusEnums::REJECTED;

                $driver->status = $approved
                    ? DriverStatusEnums::ACTIVE
                    : DriverStatusEnums::INACTIVE;

                $driver->save();

                $driverMessage = $approved
                    ? "Hi {$driver->full_name}, your LoopFreight driver application has been APPROVED. You're now active and assigned."
                    : "Hi {$driver->full_name}, we’re sorry—your LoopFreight driver application was REJECTED because the partner’s application was also rejected.";

                // Notify Driver - Email

                $action = $approved ? 'approve' : 'reject';
                //in-app Notification
                if ($driver->user) {
                    $driver->user->notify(new DriverApplicationDecisionNotification($driver, $action));
                }

                try {
                    Mail::to($driver->email)->send(
                        new DriverApplicationDecision($driver, $approved ? 'approve' : 'reject')
                    );
                } catch (\Throwable $e) {
                    logError("Failed to send driver application email", $e);
                }

                // SMS
                try {
                    if (!empty($driver->phone)) {
                        $termii->sendSms($driver->phone, $driverMessage);
                    }
                } catch (\Throwable $e) {
                    logError("Failed to send SMS to driver", $e);
                }

                // WhatsApp
                try {
                    if (!empty($driver->whatsapp_number)) {
                        $twilio->sendWhatsAppMessage($driver->whatsapp_number, $driverMessage);
                    }
                } catch (\Throwable $e) {
                    logError("Failed to send WhatsApp to driver", $e);
                }
            }
        }
    }

    public function partnerEarnings(Request $request, GetPartnerEarningsAction $action)
    {
        try {
            $partner = $request->user();

            $dto = $action->execute($partner);

            event(new PartnerEarningsViewed($partner));

            return successResponse("Partner earnings fetched successfully", $dto->toArray());
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch partner earnings", 500, "PartnerEarningsError", $th);
        }
    }
}

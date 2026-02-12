<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function adminNotifications(Request $request)
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->hasRole('admin')) {
            return failureResponse(
                'Unauthorized access. Only admins can view these notifications.',
                403,
                'unauthorized'
            );
        }

        $perPage = $request->input('per_page', 10);
        $onlyUnread = filter_var($request->input('unread'), FILTER_VALIDATE_BOOLEAN);

        $notificationsQuery = $admin->notifications()
            ->whereIn('type', [
                \App\Notifications\Admin\NewDriverApplicationNotification::class,
                \App\Notifications\Admin\NewPartnerApplicationNotification::class,
                \App\Notifications\Admin\NoAvailableDriverNotification::class,
                \App\Notifications\Admin\DeliveryMarkedCompletedNotification::class,
                \App\Notifications\Admin\InvestorWithdrawalNotification::class,
                \App\Notifications\Admin\ProductionAccessRequestNotification::class,


            ])
            ->latest();

        if ($onlyUnread) {
            $notificationsQuery->whereNull('read_at');
        }

        $notifications = $notificationsQuery->paginate($perPage);

        return successResponse(
            'Admin notifications fetched successfully.',
            $notifications
        );
    }


    public function userNotifications(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return failureResponse(
                'Unauthorized access. Please log in to view notifications.',
                403,
                'unauthorized'
            );
        }

        $perPage = $request->input('per_page', 10);
        $onlyUnread = filter_var($request->input('unread'), FILTER_VALIDATE_BOOLEAN);

        $notificationsQuery = $user->notifications()
            ->whereIn('type', [
                \App\Notifications\User\PendingWalletDebitNotification::class,
                \App\Notifications\User\UserWalletCreditedNotification::class,
                \App\Notifications\User\UserWalletDebitedNotification::class,
                \App\Notifications\User\DriverApplicationReceivedNotification::class,
                \App\Notifications\User\PartnerApplicationReceivedNotification::class,
                \App\Notifications\User\InvestorApplicationReceivedNotification::class,
                \App\Notifications\User\DisputeSubmittedNotification::class,
                \App\Notifications\User\DisputeStatusUpdatedNotification::class,
                \App\Notifications\User\DriverAssignedNotification::class,
                \App\Notifications\User\CustomerAssignedDriverNotification::class,
                \App\Notifications\User\SubscriptionPaymentNotification::class,
                \App\Notifications\User\DeliveryMarkedAsDeliveredNotification::class,
                \App\Notifications\User\DeliveryCompletedInAppNotification::class,
                \App\Notifications\User\InvestmentPaymentSuccessfulNotification::class,
                \App\Notifications\User\DriverApplicationDecisionNotification::class,
                \App\Notifications\User\DriverAssignedToTransportNotification::class,
                \App\Notifications\User\DriverUnassignedFromTransportNotification::class,
                \App\Notifications\User\DriverAssignedToDeliveryNotification::class,
                \App\Notifications\User\DriverUnassignedFromDeliveryNotification::class,
                \App\Notifications\User\DeliveryAssignedToCustomerNotification::class,
                \App\Notifications\User\DeliveryReassignedToCustomerNotification::class,
                \App\Notifications\User\FleetAdditionReceivedNotification::class,
                \App\Notifications\User\FleetApplicationDecisionNotification::class,
                \App\Notifications\User\PartnerApplicationDecisionNotification::class,
                \App\Notifications\User\InvestorApplicationDecisionNotification::class,
                \App\Notifications\User\RideBookedNotification::class,
                \App\Notifications\User\PartnerLinkDriverNotification::class,
                \App\Notifications\User\RideAcceptedNotification::class,
                \App\Notifications\User\RideCancelledNotification::class,
                \App\Notifications\User\DriverRideCancelledNotification::class,
                \App\Notifications\User\DriverRideAssignedNotification::class,
                \App\Notifications\User\RideStartedNotification::class,
                \App\Notifications\User\RideDurationTimeoutNotification::class,
                \App\Notifications\User\RidePoolDriverRatedNotification::class,
                \App\Notifications\User\RideEndedNotification::class,
                \App\Notifications\User\RideRejectedByDriverNotification::class,
                \App\Notifications\User\DriverRejectedRideNotification::class,
                \App\Notifications\User\RideCancelledByAdminNotification::class,
                \App\Notifications\User\AirtimePurchaseNotification::class,
                \App\Notifications\User\DataPurchaseNotification::class,
                \App\Notifications\User\ElectricityPurchaseNotification::class,
                \App\Notifications\User\CableTvPurchaseNotification::class,
                \App\Notifications\User\PayoutFailedNotification::class,
                \App\Notifications\User\PayoutCompletedNotification::class,
                \App\Notifications\WalletDebitNotification::class,
                \App\Notifications\DriverRatedNotification::class,
                \App\Notifications\PayoutInitiatedNotification::class,
                \App\Notifications\RewardAvailableNotification::class,
                \App\Notifications\RewardPaidNotification::class,
                \App\Notifications\AdminBroadcastNotification::class,
                \App\Notifications\WalletCreditedNotification::class,
                \App\Notifications\ReInvestmentPaymentSuccessfulNotification::class,
                \App\Notifications\InvestmentRefundedNotification::class,

            ])
            ->latest();

        if ($onlyUnread) {
            $notificationsQuery->whereNull('read_at');
        }

        $notifications = $notificationsQuery->paginate($perPage);

        return successResponse('User notifications fetched successfully.', $notifications);
    }


    public function markAsRead(Request $request, $notificationId = null)
    {
        $user = Auth::user();

        if (! $user) {
            return failureResponse('Unauthorized access.', 403, 'unauthorized');
        }

        // Single notification
        if ($notificationId) {
            $notification = $user->notifications()->where('id', $notificationId)->first();

            if (! $notification) {
                return failureResponse('Notification not found.', 404, 'not_found');
            }

            $notification->markAsRead();

            return successResponse('Notification marked as read.', $notification);
        }

        // All notifications
        $user->unreadNotifications->markAsRead();

        return successResponse('All notifications marked as read.');
    }

    public function markAsUnread(Request $request, $notificationId)
    {
        $user = Auth::user();

        if (! $user) {
            return failureResponse('Unauthorized access.', 403, 'unauthorized');
        }

        $notification = $user->notifications()->where('id', $notificationId)->first();

        if (! $notification) {
            return failureResponse('Notification not found.', 404, 'not_found');
        }

        $notification->update(['read_at' => null]);

        return successResponse('Notification marked as unread.', $notification);
    }
}

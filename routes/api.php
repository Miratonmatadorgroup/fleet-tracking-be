<?php

use App\Http\Controllers\Api\AdminBroadcastController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ExternalAuthController;
use App\Http\Controllers\Api\FinanceSummaryController;
use App\Http\Controllers\Api\MerchantController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfficeAdminController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\ShanonoSettlementWebhookController;
use App\Http\Controllers\Api\SmileIdWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionPlanController;
use App\Http\Controllers\Api\TrackerController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletPaymentController;
use App\Http\Controllers\Api\WalletTransactionController;
use App\Http\Controllers\Api\WebhookSecretController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;





Route::get('/', function () {
    $message = 'Welcome to FleetManagement App';
    if (config('app.env') === 'production') {
        $message .= '. This API is in production environment';
    } else {
        $message .= '. This API is in development environment.';
    }

    return response()->json([
        'status' => 'success',
        'data'   => ['message' => $message],
    ]);
})->name('index');

Route::post('/external-auth', [ExternalAuthController::class, 'authenticate']);


// Shared route (guest + auth) for user to verify either email,phone or whatsapp_number otp
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
// FOR EXTERNAL USERS
Route::post('/external/verify-otp', [AuthController::class, 'verifyOtp']);

// routes/api.php
Route::middleware('guest')->group(function () {

    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/resend-verification-otp', [AuthController::class, 'resendVerificationOtp']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});



// FOR USERS PAYMENTS STARTS HERE
// Redirect after user finishes on Shanono checkout
Route::get('/payments/callback', [PaymentController::class, 'redirectHandler'])->name('payments.callback');
// Webhook from Shanono (server-to-server)
Route::post('/payments/webhook', [PaymentController::class, 'webhookHandler'])->name('payments.webhook');
Route::post('/payments/verify', [PaymentController::class, 'verify'])->name('payment.verify');
// FOR USERS PAYMENTS ENDS HERE

// FOR USERS  TO CREDIT WALLET STARTS HERE
Route::post('/wallet/pay/webhook', [WalletPaymentController::class, 'webhook'])->name('wallet.webhook');
Route::post('/wallet/pay/verify', [WalletPaymentController::class, 'verify'])->name('wallet.verify');
// FOR USERS TO CREDIT WALLET ENDS HERE

Route::post('/webhooks/shanono/settlement', [ShanonoSettlementWebhookController::class, 'handle']);
Route::post('/webhooks/smile-id', [SmileIdWebhookController::class, 'handle']);

// Subscription verify with shanono payment gateway
Route::post('/sub-payments/verify', [PaymentController::class, 'verifySubscription'])->name('subscription.verify');


Route::middleware(['auth:api', 'update.activity'])->group(function () {
    // TRACKERS ROUTE STARTS HERE
    Route::post('/tracker/inventory', [TrackerController::class, 'storeOrUpdate'])->middleware('permission:take-inventory');
    Route::get('/tracker/inventory', [TrackerController::class, 'index'])->middleware('permission:view-all-trackers');
    Route::delete('/tracker/inventory/{tracker}', [TrackerController::class, 'destroy'])->middleware('permission:delete-a-tracker');
    Route::delete('/bulk-delet/trackers', [TrackerController::class, 'bulkDelete'])->middleware('permission:bulk-delete-trackers');
    Route::post('/assign/activate/trackers', [TrackerController::class, 'assignRange'])->middleware('permission:assign-trackers');
    Route::post('/activate/tracker', [TrackerController::class, 'activate']);
    Route::get('/view/my-trackers', [TrackerController::class, 'myTrackers']);

    // TRACKER ROUTE ENDS HERE

    // MERCHANT ROUTE STARTS HERE
    Route::post('/merchants/{merchant}/suspend', [MerchantController::class, 'suspend'])->middleware('permission:suspend-merchant');
    Route::post('/merchants/{merchant}/unsuspend', [MerchantController::class, 'unsuspend'])->middleware('permission:unsuspend-merchant');
    // MERCHANT ROUTE ENDS HERE

    // FOR USERS TO CREDIT WALLET STARTS HERE
    Route::post('/wallet/pay/initiate', [WalletPaymentController::class, 'initiate'])->name('wallet.initiate');
    Route::get('/wallet/pay/success', [WalletPaymentController::class, 'success'])->name('wallet.success');
    Route::get('/wallet/pay/failed', [WalletPaymentController::class, 'failed'])->name('wallet.failed');

    Route::get('/user-profile', [AuthController::class, 'profile']);
    Route::post('/update/user-profile', [AuthController::class, 'updateProfile']);
    Route::get('/user/bank-details/status', [AuthController::class, 'bankDetailsStatus']);

    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/view/user-role', [UserController::class, 'viewUserRoles']);

    Route::get('/activity/devices', [AuthController::class, 'devices']);
    Route::get('/user/devices/activity', [AuthController::class, 'userDevices'])->middleware('permission:view-user-activity');

    // TRANSACTION PIN
    Route::post('/transaction-pin/create', [AuthController::class, 'createPin']);
    Route::post('/transaction-pin/change', [AuthController::class, 'changePin']);
    Route::post('/transaction-pin/forgot', [AuthController::class, 'requestResetPinOTP']);
    Route::post('/transaction-pin/reset', [AuthController::class, 'resetPin']);
    Route::get('/user/transaction-pin-status', [AuthController::class, 'hasTransactionPin']);

    //NOTIFICATIONS STARTS HERE
    Route::get('/user/notifications', [NotificationController::class, 'userNotifications']);
    Route::post('/user/notifications/mark-read/{id?}', [NotificationController::class, 'markAsRead']);
    Route::post('/user/notifications/mark-unread/{id}', [NotificationController::class, 'markAsUnread']);
    //NOTIFICATIONS ENDS HERE

    // USER VIEW TRANSACTIONS
    Route::get('/user/transactions', [WalletTransactionController::class, 'userTransactions']);
    // WALLET TRANSACTIONS ENDS HERE

    // SUPERADMIN/ADMIN USER MANAGEMENT
    Route::post('/admin/create-user', [AuthController::class, 'adminCreateUser'])
        ->middleware('permission:create-user');
    Route::delete('/admin/delete-user/{userId}', [AuthController::class, 'adminDeleteUser'])
        ->middleware('permission:delete-users');

    Route::post('/bank/verify-account', [DriverController::class, 'verifyAccountName']);

    //USER REPORT DISPUTE ROUTE STARTS
    Route::post('/report-dispute', [DisputeController::class, 'reportDispute']);

    // PAYMENT HADNLER STARTS HERE
    Route::get('/payments/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('/payments/failed', [PaymentController::class, 'failed'])->name('payment.failed');
    // Route::post('/subscriptions/pay-with-wallet', [PaymentController::class, 'payWithWallet']);
    Route::post('/subscriptions/pay-with-wallet', [PaymentController::class, 'paySubscription']);
    Route::post('/payments/initiate', [PaymentController::class, 'paySubscription'])->name('payments.initiate');

    // USER SUBSCRIPTION ROUTE
    Route::patch('/subscriptions/{subscriptionId}/toggle-auto-renew', [SubscriptionController::class, 'toggleAutoRenew']);

    // BYPASS STARTS HERE
    Route::post('/create-roles', [RolePermissionController::class, 'createRoleWithPermissions']);
    Route::post('/assign-role', [RolePermissionController::class, 'assignRole']);
    Route::post('/create-permissions', [RolePermissionController::class, 'createOrUpdatePermissions']);
    Route::post('/{userId}/assign-all-permissions', [RolePermissionController::class, 'assignAllPermissionsToAdmin']);
    // BYPASS ENDS HERE

    Route::post('/internal/webhooks/secret', [WebhookSecretController::class, 'generate'])
        ->middleware('permission:create-secret-key');

    // SUBSCRIPTION PLAN ACTIONS BY SUPER_ADMIN/ADMIN
    Route::get('/new-view/subscription-plans', [SubscriptionPlanController::class, 'index']);
    Route::get('/view/subscription-plans', [SubscriptionPlanController::class, 'userPlans'])->name('subscription.plans');
    Route::post('/create/subscription-plans', [SubscriptionPlanController::class, 'store'])
        ->middleware('permission:create-sub-plans');
    Route::put('/update/subscription-plans/{id}', [SubscriptionPlanController::class, 'update'])
        ->middleware('permission:update-sub-plans');
    Route::delete('/destroy/subscription-plans/{id}', [SubscriptionPlanController::class, 'destroy'])
        ->middleware('permission:delete-sub-plans');

    // FINANCIAL SUMMARY VIEW BY SUPER ADMIN
    Route::get('/view/total-earnings', [FinanceSummaryController::class, 'subscriptionEarnings'])
        ->middleware('permission:view-total-earnings');


    // ROLES AND PERMISSIONS
    Route::post('/admin/create-roles', [RolePermissionController::class, 'adminCreateRoleWithPermissions'])
        ->middleware('permission:create-role');
    Route::get('/view/all-roles-and-permissions', [RolePermissionController::class, 'getAllRolesAndPermissions'])
        ->middleware('permission:view-roles');
    Route::post('/admin/assign-role', [RolePermissionController::class, 'adminAssignRole'])
        ->middleware('permission:assign-role');
    Route::post('/admin/unassign-role', [RolePermissionController::class, 'adminUnAssignRole'])
        ->middleware('permission:unassign-role');
    Route::get('/view/user-role-permissions', [RolePermissionController::class, 'getUserRoleAndPermissions'])
        ->middleware('permission:view-user-roles');
    Route::get('/view/all-users', [RolePermissionController::class, 'getAllUsersWithRolesAndPermissions'])
        ->middleware('permission:view-users');
    Route::post('/assign-permissions-to-role', [RolePermissionController::class, 'assignPermissionsToRole'])
        ->middleware('permission:assign-permissions');
    Route::post('/add-permissions-to-role', [RolePermissionController::class, 'addPermissionsToRole'])
        ->middleware('permission:add-permissions');
    Route::post('/remove-permissions-from-role', [RolePermissionController::class, 'removePermissionsFromRole'])
        ->middleware('permission:remove-permissions');
    Route::put('/admin/edit-role', [RolePermissionController::class, 'adminEditRoleWithPermissions'])
        ->middleware('permission:edit-role');
    Route::post('/admin/create-permissions', [RolePermissionController::class, 'adminCreateOrUpdatePermissions'])
        ->middleware('permission:create-permissions');

    // OFFICE ADMIN ALSO KNOWN AS BUSINESS OPERATOR
    Route::post('/assign/user/fleet-manager', [OfficeAdminController::class, 'assignFleetManager'])
        ->middleware('permission:assign-user-fleet-manager');
    Route::post('/unassign/user/fleet-manager', [OfficeAdminController::class, 'unassignFleetManager'])
        ->middleware('permission:unassign-user-fleet-manager');

    Route::post('/suspend/fleet-manager/{userId}', [OfficeAdminController::class, 'suspendFleetManager'])
        ->middleware('permission:suspend-fleet-manager');
    Route::post('/unsuspend/fleet-manager/{userId}', [OfficeAdminController::class, 'unsuspendFleetManager'])
        ->middleware('permission:unsuspend-fleet-manager');

    Route::get('/view/merchant/trackers', [OfficeAdminController::class, 'viewMyTrackers'])
        ->middleware('permission:view-merhcant-trackers');

    Route::put('/update/merchant/trackers/{trackerId}', [OfficeAdminController::class, 'updateTrackersWithLabel'])
        ->middleware('permission:update-merchent-trackers');

    Route::get('/merchant/fleet-managers', [OfficeAdminController::class, 'myFleetManagers'])
        ->middleware('permission:view-my-fleet-managers');

    Route::middleware('check.subscription')->group(function () {

        Route::get('/fleet/vehicles/tracking', [TrackerController::class, 'tracking'])
            ->middleware('permission:view-vehicle-tracking');

        Route::post('/fleet/vehicles/shutdown', [TrackerController::class, 'remoteShutdown'])
            ->middleware('permission:remote-shutdown');

        Route::get('/fleet/vehicles/geofencing', [TrackerController::class, 'geoFencing'])
            ->middleware('permission:view-geo-fencing');
    });

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////





    // WALLET TRANSACTIONS - BULK
    Route::post('/wallet/bulk-credit', [WalletTransactionController::class, 'bulkCredit'])
        ->middleware('permission:bulk-credit');
    Route::post('/wallet/bulk-debit', [WalletTransactionController::class, 'bulkDebit'])
        ->middleware('permission:bulk-debit');

    // WALLET TRANSACTIONS - SINGLE
    Route::post('/admin-credit-user-walet', [WalletTransactionController::class, 'adminCredit'])
        ->middleware('permission:credit-wallet');
    Route::post('/admin-debit-user-wallet', [WalletTransactionController::class, 'adminDebit'])
        ->middleware('permission:debit-wallet');

    Route::get('/admin/shanono/wallet/transactions', [WalletTransactionController::class, 'shanonoWalletTransactions'])
        ->middleware('permission:shanono-merchant-account-transactions');


    // NOTIFICATIONS
    Route::get('/admin/notifications', [NotificationController::class, 'adminNotifications'])
        ->middleware('permission:view-notifications');

    // PAYMENTS
    Route::get('/admin/view-payment-summary', [PaymentController::class, 'getPaymentSummary'])
        ->middleware('permission:view-payment-summary');

    Route::get('/admin/view-earnings-summary', [PaymentController::class, 'getEarningsSummary'])
        ->middleware('permission:view-earnings-summary');


    Route::get('/admin/view/financial/summary', [FinanceSummaryController::class, 'platformFinancialSummary'])
        ->middleware('permission:view-financial-summary');


    // DISPUTES
    Route::put('/admin/resolve-disputes/{dispute_id}', [DisputeController::class, 'updateStatus'])
        ->middleware('permission:resolve-dispute');

    Route::get('/admin/view-all-disputes', [DisputeController::class, 'viewDisputes'])
        ->middleware('permission:view-disputes');

    Route::post('/admin/broadcast', [AdminBroadcastController::class, 'sendMessage'])
        ->middleware('permission:admin-broadcast-message');


    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

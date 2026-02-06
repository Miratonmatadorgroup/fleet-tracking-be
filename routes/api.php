<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\FlagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\PayoutController;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TrackerController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\InvestorController;
use App\Http\Controllers\Api\ApiClientController;
use App\Http\Controllers\Api\ApiHeaderController;
use App\Http\Controllers\Api\ApiRequestController;
use App\Http\Controllers\Api\ApiEndpointController;
use App\Http\Controllers\Api\ApiResponseController;
use App\Http\Controllers\Api\RideBookingController;
use App\Http\Controllers\Api\DriverStatusController;
use App\Http\Controllers\Api\ExternalAuthController;
use App\Http\Controllers\Api\ExternalUserController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RewardSystemController;
use App\Http\Controllers\Api\TransportModeController;
use App\Http\Controllers\Api\WalletPaymentController;
use App\Http\Controllers\Api\WebhookSecretController;
use App\Http\Controllers\Api\AdminBroadcastController;
use App\Http\Controllers\Api\DriverLocationController;
use App\Http\Controllers\Api\FinanceSummaryController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SmileIdWebhookController;
use App\Http\Controllers\Api\ExternalPaymentController;
use App\Http\Controllers\Api\ExternalDeliveryController;
use App\Http\Controllers\Api\CommissionSettingController;
use App\Http\Controllers\Api\InvestmentPaymentController;
use App\Http\Controllers\Api\ProjectAssignmentController;
use App\Http\Controllers\Api\WalletTransactionController;
use App\Http\Controllers\Api\FundReconciliationController;
use App\Http\Controllers\Api\RidePoolingPricingController;
use App\Http\Controllers\Api\Admin\TransportPricingController;
use App\Http\Controllers\Api\ShanonoSettlementWebhookController;
use App\Http\Controllers\Api\ShanonoBillsPaymentWebhookController;





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
Route::post('/webhooks/shanono/bills-payment', [ShanonoBillsPaymentWebhookController::class, 'handle']);
Route::post('/webhooks/smile-id', [SmileIdWebhookController::class, 'handle']);

Route::middleware(['auth:api', 'update.activity'])->group(function () {
    // TRACKERS ROUTE STARTS HERE
    Route::post('/tracker/inventory', [TrackerController::class, 'storeOrUpdate'])->middleware('permission:take-inventory');
    Route::get('/tracker/inventory', [TrackerController::class, 'index'])->middleware('permission:view-all-trackers');

    // TRACKER ROUTE ENDS HERE

    Route::post('/bank/verify-account', [DriverController::class, 'verifyAccountName']);

    Route::get('/activity/devices', [AuthController::class, 'devices']);
    Route::get('/user/devices/activity', [AuthController::class, 'userDevices'])->middleware('permission:view-user-activity');

    // TRANSACTION PIN
    Route::post('/transaction-pin/create', [AuthController::class, 'createPin']);
    Route::post('/transaction-pin/change', [AuthController::class, 'changePin']);
    Route::post('/transaction-pin/forgot', [AuthController::class, 'requestResetPinOTP']);
    Route::post('/transaction-pin/reset', [AuthController::class, 'resetPin']);
    Route::get('/user/transaction-pin-status', [AuthController::class, 'hasTransactionPin']);

    // FOR EXTERNAL USERS TRANSACTION PIN
    Route::post('/external/transaction-pin/create', [AuthController::class, 'createPin'])
        ->middleware('permission:dev-create-transaction-pin');
    Route::post('/external/transaction-pin/change', [AuthController::class, 'changePin'])
        ->middleware('permission:dev-change-transaction-pin');
    Route::post('/external/transaction-pin/forgot', [AuthController::class, 'requestResetPinOTP'])
        ->middleware('permission:dev-forgot-transaction-pin');
    Route::post('/external/transaction-pin/reset', [AuthController::class, 'resetPin'])
        ->middleware('permission:dev-reset-transaction-pin');
    // FOR EXTERNAL USERS CHANGE PASSWORD,VIEW API CREDENTIALS,VIEW USER PROFILE
    Route::post('/external/change-password', [AuthController::class, 'changePassword'])
        ->middleware('permission:dev-change-password');
    Route::get('/external/view/api-credentials', [ExternalUserController::class, 'credentials'])
        ->middleware('permission:dev-view-api-credentials');
    Route::get('/external/user-profile', [AuthController::class, 'profile'])
        ->middleware('permission:dev-profile');
    // FOR EXTERNAL USER TO REQUEST FOR PRODUTION KEY
    Route::post('/external/request-production-access', [ExternalUserController::class, 'requestProductionAccess'])
        ->middleware('permission:dev-requets-production-access');

    // API CLIENT STARTS HERE
    Route::get('/view/api-clients', [ApiClientController::class, 'show'])->middleware('permission:view-api-clients');
    // API CLIENT ENDS HERE

    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/view/user-role', [UserController::class, 'viewUserRoles']);
    Route::get('/user-profile', [AuthController::class, 'profile']);
    Route::post('/update/user-profile', [AuthController::class, 'updateProfile']);
    Route::get('/user/bank-details/status', [AuthController::class, 'bankDetailsStatus']);

    //USER BOOK RIDE OPERATIONS
    Route::post('/ride/search', [RideBookingController::class, 'search']);

    Route::post('/book/ride', [RideBookingController::class, 'store']);
    Route::post('/cancel/ride/{ride}', [RideBookingController::class, 'cancelRide']);
    Route::get('/user/active-ride', [RideBookingController::class, 'activeRide']);


    //DELIVERY STARTS HERE
    Route::post('/book-delivery', [DeliveryController::class, 'bookDelivery']);
    Route::put('/update-booking/{delivery}', [DeliveryController::class, 'updateBooking']);
    Route::get('/my-deliveries', [DeliveryController::class, 'myDeliveries']);

    Route::get('/deliveries/stats', [DeliveryController::class, 'myDeliveryStats']);
    Route::post('/sender/deliveries/{id}/confirm-completed', [UserController::class, 'confirmAsCompleted']);
    Route::delete('/deliveries/{id}/cancel', [DeliveryController::class, 'cancelDelivery']);
    Route::get('/delivery/details/{delivery_id}', [DeliveryController::class, 'getDeliveryDetails']);
    //DELIVERY ENDS HERE

    //USER REPORT DISPUTE ROUTE STARTS
    Route::post('/report-dispute', [DisputeController::class, 'reportDispute']);

    //USER REPORT DISPUTE ROUTE ENDS HERE

    // PAYMENT HADNLER STARTS HERE
    Route::post('/payments/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');


    Route::get('/payments/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('/payments/failed', [PaymentController::class, 'failed'])->name('payment.failed');

    Route::post('/pay-with-wallet', [PaymentController::class, 'payWithWallet']);

    // PAYMENT HADNLER ENDS HERE

    // WALLET TRANSACTIONS STARTS HERE
    // USER REQUEST PAYOUT TO TRANSFER
    Route::post('/user/transfer', [PayoutController::class, 'requestPayout']);
    //ADMIN RESTRICT AND UNRESTRICT PAYOUT FOR SINGLE USER AND ALL USERS
    Route::post('/users/{user}/restrict-payout', [PayoutController::class, 'restrictPayouts'])
        ->middleware('permission:admin-restrict-payouts');
    Route::post('/users/{user}/unrestrict-payout', [PayoutController::class, 'unrestrictPayouts'])
        ->middleware('permission:admin-unrestrict-payouts');
    // PAYOUT STATUS
    Route::get('/admin/users/{user}/payout-status', [PayoutController::class, 'checkUserPayoutStatus'])
        ->middleware('permission:admin-user-payout-status');
    Route::get('/admin/payouts/global-status', [PayoutController::class, 'checkGlobalPayoutStatus'])
        ->middleware('permission:admin-global-payout-status');
    // VIEW ALL PAYOUTS
    Route::get('/view/payouts', [PayoutController::class, 'index'])->middleware('permission:view-payouts');
    Route::post('/payouts/restrict-all', [PayoutController::class, 'restrictAll'])
        ->middleware('permission:admin-restrictall-payouts');
    Route::post('/payouts/unrestrict-all', [PayoutController::class, 'unrestrictAll'])
        ->middleware('permission:admin-unrestrictall-payouts');

    // FOR AIRTIME PURCHASE
    Route::get('/airtime/providers', [WalletTransactionController::class, 'airtimeProviders']);
    Route::post('/user/buy/airtime', [WalletTransactionController::class, 'buyAirtime']);

    // FOR DATA PURCHASE
    Route::get('/provider/data/plans', [WalletTransactionController::class, 'dataPlans']);
    Route::post('/user/buy/data', [WalletTransactionController::class, 'buyData']);

    // FOR ELECTRICITY PURCHASE
    Route::get('/electricity/providers', [WalletTransactionController::class, 'electricityProviders']);
    Route::post('/user/buy/electricity', [WalletTransactionController::class, 'buyElectricity']);

    // FOR CABLE PURCHASE
    Route::get('/cable-tv/providers', [WalletTransactionController::class, 'cabletvProviders']);
    Route::post('/user/sub/cable/tv', [WalletTransactionController::class, 'buyTvSubscription']);



    // USER VIEW TRANSACTIONS
    Route::get('/user/transactions', [WalletTransactionController::class, 'userTransactions']);
    // WALLET TRANSACTIONS ENDS HERE

    //USER DRIVER RATING & NOTIFICATIONS STARTS HERE
    // Route::get('/notifications', [UserController::class, 'myNotifications']);
    Route::post('/rate/drivers', [UserController::class, 'rateDriver']);
    Route::post('/rate/ride-pool/drivers', [UserController::class, 'rateRidePoolDriver']);
    Route::post('/rating/dismiss', [UserController::class, 'dismissRatingPrompt']);
    Route::get('/rating/last-ride', [UserController::class, 'getLastRideNeedingRating']);

    //USER DRIVER RATING & NOTIFICATIONS ENDS HERE

    //DRIVER APPLICATION STARTS HERE
    Route::post('/driver/apply', [DriverController::class, 'driverApplicationForm']);
    Route::post('/bank/verify', [DriverController::class, 'verifyBankAccount']);
    Route::get('/banks', [DriverController::class, 'listBanks']);
    Route::get('/banks-protected', [DriverController::class, 'protectedBanks']);

    Route::get('/driver/earnings', DriverController::class)->middleware('permission:driver-earnings');
    //DRIVER APPLICATION ENDS HERE

    //PARTNER APPLICATION STARTS HERE
    Route::post('/partner/apply', [PartnerController::class, 'partnerApplicationForm']);
    Route::get('/partner/application-status', [PartnerController::class, 'checkPartnerApplicationStatus']);

    //PARTNER APPLICATION ENDS HERE

    //INVESTOR ROUTE STARTS HERE
    Route::post('/investor/apply', [InvestorController::class, 'investorApplicationForm']);

    Route::get('/investor/check-application', [InvestorController::class, 'checkApplication']);

    Route::post('/investor/pay-from-wallet/{investor}', [InvestmentPaymentController::class, 'investorPayFromWallet']);

    Route::post('/investment/initiate', [InvestmentPaymentController::class, 'initiate'])->name('investment.initiate');

    Route::get('/investment/success', [InvestmentPaymentController::class, 'success'])->name('investment.success');
    Route::get('/investment/failed', [InvestmentPaymentController::class, 'failed'])->name('investment.failed');

    //INVESTOR REINVEST
    Route::post('/reinvest/initiate', [InvestmentPaymentController::class, 'reinvestInitiate'])->name('reinvestment.initiate');
    Route::post('/reinvest/pay-from-wallet', [InvestmentPaymentController::class, 'reinvestPayFromWallet']);


    Route::get('/view/total/invested/funds', [InvestorController::class, 'totalInvestedFunds'])
        ->middleware('permission:view-total-invested-funds');

    //INVESTOR ROUTE ENDS HERE

    // FOR USERS TO CREDIT WALLET STARTS HERE
    Route::post('/wallet/pay/initiate', [WalletPaymentController::class, 'initiate'])->name('wallet.initiate');
    Route::get('/wallet/pay/success', [WalletPaymentController::class, 'success'])->name('wallet.success');
    Route::get('/wallet/pay/failed', [WalletPaymentController::class, 'failed'])->name('wallet.failed');

    // FOR USERS TO CREDIT WALLET ENDS HERE

    // BYPASS STARTS HERE
    Route::post('/create-roles', [RolePermissionController::class, 'createRoleWithPermissions']);
    Route::post('/assign-role', [RolePermissionController::class, 'assignRole']);
    Route::post('/create-permissions', [RolePermissionController::class, 'createOrUpdatePermissions']);
    Route::post('/{userId}/assign-all-permissions', [RolePermissionController::class, 'assignAllPermissionsToAdmin']);
    // BYPASS ENDS HERE

    //NOTIFICATIONS STARTS HERE
    Route::get('/user/notifications', [NotificationController::class, 'userNotifications']);
    Route::post('/user/notifications/mark-read/{id?}', [NotificationController::class, 'markAsRead']);
    Route::post('/user/notifications/mark-unread/{id}', [NotificationController::class, 'markAsUnread']);
    //NOTIFICATIONS ENDS HERE

    // TRANSPORT MODE PASSENGERS STARTS HERE
    Route::get('/user/view-rides', [TransportModeController::class, 'viewRides']);

    // TRANSPORT MODE PASSENGERS ENDS HERE


    // ADMIN ROUTES STARTS HERE///////////////////////////////////////////////////////////////////////////////////////////////////
    Route::post('/internal/webhooks/secret', [WebhookSecretController::class, 'generate'])
        ->middleware('permission:create-secret-key');
    //FUNDS RECONCILIATION FOR SHANONO BANK,TNT DEALS AND ALL EXTERNAL USERS
    Route::get('/view/fund-reconciliations', [FundReconciliationController::class, 'viewAllFunds'])->middleware('permission:view-all-funds-reconciliation');
    Route::get('/debt-owed/by-external-users', [FundReconciliationController::class, 'externalClientsDebtSummary'])->middleware('permission:sum-debt-owed-by-external-partners');
    Route::get('/clients/{clientId}/debt-summary', [FundReconciliationController::class, 'externalClientDebtSummary'])->middleware('permission:sum-debt-owed-by-a-single-external-partner');
    Route::post('/fund-reconciliations/{id}/receive', [FundReconciliationController::class, 'receive'])->middleware('permission:receive-pay-from-shanono');

    //FUNDS RECONCILIATION FOR SHANONO BANK,TNT DEALS AND ALL EXTERNAL USERS ENDS HERE

    Route::post('/admin/block/api-client', [ExternalDeliveryController::class, 'block'])
        ->middleware(['permission:block-external-users']);
    // USER MANAGEMENT
    Route::post('/admin/create-user', [AuthController::class, 'adminCreateUser'])
        ->middleware('permission:create-user');
    Route::delete('/admin/delete-user/{userId}', [AuthController::class, 'adminDeleteUser'])
        ->middleware('permission:delete-users');

    //PAYOUTS


    // ADMIN ON DELIVERY
    Route::post('/admin/book-delivery', [DeliveryController::class, 'adminBookDelivery'])
        ->middleware('permission:book-delivery');
    Route::put('/admin/update-booking/{delivery}', [DeliveryController::class, 'adminUpdateBooking'])
        ->middleware('permission:update-booking');
    Route::get('/admin/view-all-deliveries', [DeliveryController::class, 'adminGetAllDeliveries'])
        ->middleware('permission:view-all-deliveries');
    Route::post('/admin/track-delivery', [DeliveryController::class, 'adminTrackDeliveryByTrackingNumber'])
        ->middleware('permission:track-delivery');
    Route::post('/admin/assign-delivery-to-driver', [DeliveryController::class, 'adminAssignDeliveryToDriver'])
        ->middleware('permission:assign-delivery');
    Route::post('/admin/mark-single-external-delivery-completed', [DeliveryController::class, 'adminMarkExternalDeliveriesCompleted'])
        ->middleware('permission:mark-external-delivery-completed');
    Route::post('/admin/in-app-deliveries/{id}/mark-completed', [DeliveryController::class, 'adminMarkInAppDeliveryAsCompleted'])
        ->middleware('permission:mark-inapp-delivery-completed');

    Route::get('/admin/view-delivery-assignment-logs', [DeliveryController::class, 'viewDeliveryAssignmentLogs'])
        ->middleware('permission:view-delivery-assignment-logs');

    Route::post('/admin/pay-with-wallet', [PaymentController::class, 'adminPayWithWallet'])
        ->middleware('permission:admin-pay-from-wallet');

    Route::get('/internal/deliveries/revenue', [DeliveryController::class, 'internalDeliveryRevenue'])
        ->middleware('permission:admin-view-internal-deliveries-revenue');

    Route::get('/external/deliveries/revenue', [DeliveryController::class, 'externalDeliveryRevenue'])
        ->middleware('permission:admin-view-external-deliveries-revenue');

    // COMMISSIONS
    Route::post('/admin/create-commission', [CommissionSettingController::class, 'updateCommissionSettings'])
        ->middleware('permission:update-commission');

    Route::get('/admin/list-of-commissions', [CommissionSettingController::class, 'listCommissions'])
        ->middleware('permission:list-of-commissions');

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

    // Route::post('/admin/{userId}/assign-all-permissions', [RolePermissionController::class, 'adminAssignAllPermissionsToAdmin'])
    // ->middleware('permission:assign-all-permissions');


    // TRANSPORT PRICING
    Route::post('/admin/transport-pricing', [TransportPricingController::class, 'updateModePricing'])
        ->middleware('permission:update-transport-pricing');
    Route::get('/admin/transport-pricing', [TransportPricingController::class, 'index'])
        ->middleware('permission:view-transport-pricing');
    Route::delete('/admin/transport-pricing/{transportPricing}', [TransportPricingController::class, 'destroy'])
        ->middleware('permission:delete-transport-pricing');

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

    // ADMIN VIEW BILLS COMMISSIONS
    Route::get('/bills/commissions', [WalletTransactionController::class, 'viewBillsCommissions'])
        ->middleware('permission:view-bills-commissions');


    // DRIVERS
    Route::get('/admin/view-list-of-drivers', [DriverController::class, 'adminViewListDrivers'])
        ->middleware('permission:view-drivers');
    Route::post('/admin/driver-application', [DriverController::class, 'applyAsDriverByAdmin'])
        ->middleware('permission:create-driver');
    Route::post('/admin/driver/decision', [DriverController::class, 'adminApproveOrRejectDriver'])
        ->middleware('permission:approve-driver');
    Route::get('/admin/view-approved-drivers', [DriverController::class, 'approvedDrivers'])
        ->middleware('permission:view-approved-drivers');
    Route::get('/admin/view-applied-drivers', [DriverController::class, 'appliedDrivers'])
        ->middleware('permission:view-applied-drivers');
    Route::get('/admin/total-number-drivers', [DriverController::class, 'driverCount'])
        ->middleware('permission:view-driver-count');

    Route::get('/platform/partner/drivers', [DriverController::class, 'getDriversByCategory'])
        ->middleware('permission:view-platform-and-partner-drivers');

    Route::get('/view/driver-ratings', [DriverController::class, 'getDriverRatings'])
        ->middleware('permission:view-drivers-ratings');

    Route::get('/driver/active-ride', [RideBookingController::class, 'driverActiveRides'])
        ->middleware('permission:driver-active-ride');

    Route::get('/admin/activeride', [RideBookingController::class, 'adminActiveRides'])
        ->middleware('permission:admin-active-ride');

    Route::get('/admin/view/ride/list', [RideBookingController::class, 'rideList'])
        ->middleware('permission:admin-view-ride-list');

    Route::get('/admin/view/ride-pools/revenue', [RideBookingController::class, 'ridePoolRevenue'])
        ->middleware('permission:admin-view-ride-pool-revenue');

    Route::post('/admin/ride-pools/{ride}/cancel', [RideBookingController::class, 'adminCancelRide'])
        ->middleware('permission:admin-cancel-ride');

    Route::get('/admin/all-driver-ratings', [DriverController::class, 'getAllDriverRatings'])
        ->middleware('permission:all-driver-ratings');


    // TRANSPORT MODES
    Route::post('/admin/store-transport-mode', [TransportModeController::class, 'adminStoreTransportMode'])
        ->middleware('permission:create-transport-mode');
    Route::delete('/admin/delete-transport-mode/{id}', [TransportModeController::class, 'adminDeleteTransportMode'])
        ->middleware('permission:delete-transport-mode');
    Route::get('/admin/view-list-of-transport-mode', [TransportModeController::class, 'adminViewListTransportMode'])
        ->middleware('permission:view-transport-mode');
    Route::post('/admin/assign-driver-to-transport-mode', [TransportModeController::class, 'adminAssignDriverToTransportMode'])
        ->middleware('permission:assign-driver-to-mode');
    Route::post('/admin/unassign-driver-from-transport-mode', [TransportModeController::class, 'adminUnassignDriverFromTransportMode'])
        ->middleware('permission:unassign-driver-from-mode');
    Route::get('/admin/total-number-transport-mode', [TransportModeController::class, 'transportModeCount'])
        ->middleware('permission:view-transport-mode-count');

    // PARTNERS
    Route::get('/admin/view-list-of-partners', [PartnerController::class, 'index'])
        ->middleware('permission:view-partners');
    Route::get('/admin/view-approved-partners', [PartnerController::class, 'approvedPartners'])
        ->middleware('permission:view-approved-partners');
    Route::get('/admin/view-applied-partners', [PartnerController::class, 'appliedPartners'])
        ->middleware('permission:view-applied-partners');
    Route::get('/admin/total-number-partners', [PartnerController::class, 'count'])
        ->middleware('permission:view-partner-count');
    Route::post('/admin/review-partner-application', [PartnerController::class, 'reviewPartnerApplication'])
        ->middleware('permission:review-partner');
    Route::post('/admin/fleet-applications-decision', [PartnerController::class, 'approveFleetApplication'])
        ->middleware('permission:approve-fleet');

    // INVESTORS
    Route::post('/admin/investor-decide', [InvestorController::class, 'decideInvestorApplication'])
        ->middleware('permission:decide-investor');
    Route::get('/admin/view-list-of-investors', [InvestorController::class, 'index'])
        ->middleware('permission:view-investors');
    Route::get('/admin/view-approved-investors', [InvestorController::class, 'approvedInvestors'])
        ->middleware('permission:view-approved-investors');
    Route::get('/admin/view-applied-investors', [InvestorController::class, 'appliedInvestors'])
        ->middleware('permission:view-applied-investors');
    Route::get('/admin/total-number-investors', [InvestorController::class, 'investorCount'])
        ->middleware('permission:view-investor-count');
    Route::post('/investments/withdrawal', [InvestorController::class, 'withdrawInvestment'])
        ->middleware('permission:investment-withdrawal');
    Route::post('/admin/investors/{investorId}/refund', [InvestorController::class, 'markRefunded'])
        ->middleware('permission:mark-withdrawn-investment-refunded');

    // INVESTMENT PLANS
    Route::post('/create/investment-plans', [InvestorController::class, 'storeInvestmentPlans'])->middleware('permission:store-investment-plan');
    Route::put('/update/investment-plans/{id}', [InvestorController::class, 'updateInvestmentPlans'])->middleware('permission:update-investment-plan');
    Route::delete('/delete/investment-plans/{id}', [InvestorController::class, 'destroyInvestmentPlans'])->middleware('permission:destroy-investment-plan');
    Route::get('/view/investment-plans', [InvestorController::class, 'viewInvestmentPlans']);
    Route::get('/admin/view/investment-plans', [InvestorController::class, 'adminViewInvestmentPlans'])
        ->middleware('permission:view-investment-plan');


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

    // SET RIDE POOLING PRICES
    Route::get('/ride-pooling-pricing', [RidePoolingPricingController::class, 'index']);
    Route::post('/ride-pooling-pricing', [RidePoolingPricingController::class, 'updateOrCreate'])
        ->middleware('permission:update-or-create-ride-pooling-pricing');
    Route::delete('/ride-pooling-pricing/{ridePoolingPricing}', [RidePoolingPricingController::class, 'destroy'])
        ->middleware('permission:delete-ride-pooling-pricing');



    //DISCOUNTS BY ADMIN STARTS HERE//
    Route::get('/view/discounts', [DiscountController::class, 'index'])->middleware('permission:view-discounts-created');
    Route::post('/create/discount', [DiscountController::class, 'store'])->middleware('permission:create-discount');
    Route::put('/update/discount/{id}', [DiscountController::class, 'update'])->middleware('permission:update-discount');
    Route::delete('/delete/discount/{id}', [DiscountController::class, 'destroy'])->middleware('permission:delete-discount');

    // assignment
    Route::post('/assign/discount', [DiscountController::class, 'assignToUser'])->middleware('permission:assign-discount-to-user');
    Route::post('/remove/discount', [DiscountController::class, 'removeFromUser'])->middleware('permission:remove-discount-from-user');

    // status controls
    Route::post('/discount/activate/{id}', [DiscountController::class, 'activate'])->middleware('permission:activate-discount');

    Route::post('/discount/deactivate/{id}', [DiscountController::class, 'deactivate'])->middleware('permission:deactivate-discount');

    // DISCOUNT BY ADMIN ENDS HERE

    // FLAGGING SYSTEM
    Route::post('/flag/ride', [FlagController::class, 'flagRide'])
        ->middleware('permission:flag-ride');

    Route::post('/flag/transport-mode', [FlagController::class, 'flagTransportMode'])
        ->middleware('permission:flag-transport-mode');

    Route::post('/flag/driver', [FlagController::class, 'flagDriver'])
        ->middleware('permission:flag-driver');

    // UNFLAG
    Route::post('/unflag/ride', [FlagController::class, 'unflagRide'])
        ->middleware('permission:unflag-ride');

    Route::post('/unflag/transport-mode', [FlagController::class, 'unflagTransportMode'])
        ->middleware('permission:unflag-transport-mode');

    Route::post('/unflag/driver', [FlagController::class, 'unflagDriver'])
        ->middleware('permission:unflag-driver');


    // ADMIN ROUTES ENDS HERE////////////////////////////////////////////////////////////////////////////////////////////////


    // DRIVER ROUTE STATRTS HERE
    Route::get('/driver/assigned-deliveries', [DriverController::class, 'assignedDeliveries'])
        ->middleware('permission:view-assigned-deliveries');

    Route::get('/driver/delivery-counts', [DriverController::class, 'deliveryCounts'])
        ->middleware('permission:driver-deliveries-count');

    Route::post('/driver/deliveries/accept/{id}', [DriverController::class, 'acceptDelivery'])
        ->middleware('permission:accept-delivery');

    Route::post('/driver/location/update', [DriverLocationController::class, 'update']);

    Route::post('/show/driver-direction', [DriverController::class, 'showDirections']);


    Route::post('/driver/deliveries/{id}/mark-delivered', [DriverController::class, 'markAsDelivered'])
        ->middleware('permission:mark-delivered');

    Route::post('/driver/deliveries/{id}/confirm', [DriverController::class, 'confirmDeliveryByDriver'])
        ->middleware('permission:driver-confirm-delivery-waybill_number');

    // ACCEPT RIDE BOOKIG
    Route::post('/driver/ride/accept', [RideBookingController::class, 'accept'])
        ->middleware('permission:drivers-accept-ride');

    //REJECT RIDE BOOKING
    Route::post('/driver/ride/reject', [RideBookingController::class, 'reject'])
        ->middleware('permission:drivers-reject-ride');

    Route::get('/driver/view/booked-rides', [RideBookingController::class, 'bookedRides'])
        ->middleware('permission:drivers-view-booked-ride');

    Route::post('/start/ride', [RideBookingController::class, 'startRide'])->middleware('permission:driver-start-ride');
    Route::post('/end/ride', [RideBookingController::class, 'endRide'])->middleware('permission:driver-end-ride');


    // DRIVER ROUTES ENDS HERE

    // PARTNER ROUTE STARTS HERE
    Route::post('/partner/add-fleet', [PartnerController::class, 'addFleetMember'])
        ->middleware('permission:add-fleet');

    Route::get('/partner/view-deliveries', [PartnerController::class, 'partnerDeliveries'])
        ->middleware('permission:view-partner-deliveries');

    Route::get('/partner/view-drivers', [PartnerController::class, 'getPartnerDrivers'])
        ->middleware('permission:view-partner-drivers');

    Route::get('/partner/view-transport-modes', [PartnerController::class, 'getPartnerTransportModes'])
        ->middleware('permission:view-partner-transport-modes');

    Route::get('/partner/earnings', [PartnerController::class, 'partnerEarnings'])
        ->middleware('permission:view-partner-earnings');

    // PARTNER ROUTE ENDS HERE

    // INVESTOR ROUTE STARTSS HERE
    Route::get('/investor/earnings', [InvestorController::class, 'investorEarnings'])
        ->middleware('permission:view-investor-earnings');

    Route::post('/investor/reinvest', [InvestorController::class, 'reinvest'])
        ->middleware('permission:investor-reinvest');

    // INVESTOR ROUTE ENDS HERE

    // REWARD SYSTEM STARTS HERE
    Route::post('/create/driver-reward', [RewardSystemController::class, 'store'])->middleware('permission:create-driver-reward-campaign');
    Route::put('/update/reward-campaigns/{id}', [RewardSystemController::class, 'update'])->middleware('permission:update-driver-reward-campaign');
    Route::delete('/delete/reward-campaigns/{id}', [RewardSystemController::class, 'destroy'])->middleware('permission:delete-driver-reward-campaign');
    Route::get('/list/driver-reward', [RewardSystemController::class, 'index'])->middleware('permission:list-driver-reward-campaign');
    Route::post('/reward-campaign/{campaign}/activate', [RewardSystemController::class, 'activate'])->middleware('permission:activate-reward-campaign');
    Route::post('/reward-campaign/{campaign}/suspend', [RewardSystemController::class, 'suspend'])->middleware('permission:suspend-reward-campaign');
    Route::post('/rewards/claim', [RewardSystemController::class, 'claim'])->middleware('permission:claim-reward');
    Route::get('/driver/available/rewards-claim', [RewardSystemController::class, 'driverAvailableReward'])->middleware('permission:driver-available-rewards-claim');

    // REWARD SYSTEM ENDS HERE

    // API DOCS ENDPOINTS STARTS HERE
    // Project Routes
    // GET/docs/projects POST/docs/projects	GET/docs/projects/{project}	PUT/docs/projects/{project}	DELETE/docs/projects/{project}
    Route::apiResource('/docs/projects', ProjectController::class)
        ->middleware(['auth', 'permission:document-projects']);
    // ->middleware('permission:document-projects');

    Route::post('/projects/assign', [ProjectAssignmentController::class, 'assign'])
        ->middleware('permission:assign-projects');
    Route::post('/projects/unassign', [ProjectAssignmentController::class, 'unassign'])
        ->middleware('permission:unassign-projects');

    Route::post('/projects/marked/public', [ProjectAssignmentController::class, 'makePublic'])
        ->middleware('permission:mark-projects-public');
    Route::post('/projects/marked/private', [ProjectAssignmentController::class, 'makePrivate'])
        ->middleware('permission:mark-projects-private');

    // API Endpoint Routes
    // GET/docs/api-endpoints POST/docs/api-endpoints GET/docs/api-endpoints/{api_endpoint} PUT/docs/api-endpoints/{api_endpoint} DELETE/docs/api-endpoints/{api_endpoint}
    Route::apiResource('/docs/api-endpoints', ApiEndpointController::class)
        ->middleware('permission:docu-endpoints');

    // API Request Routes
    // POST/docs/api-requests  DELETE/docs/api-requests/{apiRequest} it also stores and update
    Route::apiResource('/docs/api-requests', ApiRequestController::class)->only(['store', 'destroy'])
        ->middleware('permission:docu-requests');

    // API Header Routes
    //POST/docs/api-headers DELETE/docs/api-headers/{apiHeader}  it also stores and update
    Route::apiResource('/docs/api-headers', ApiHeaderController::class)->only(['store', 'destroy'])
        ->middleware('permission:docu-headers');

    // API Response Routes
    //POST/docs/api-responses DELETE/docs/api-reponses/{apiResponse}  it also stores and update
    Route::apiResource('/docs/api-responses', ApiResponseController::class)->only(['store', 'destroy'])
        ->middleware('permission:docu-responses');

    // API AUTH ROUTE
    //POST/docs/api-auths  it stores and updates
    Route::apiResource('/docs/api-auths', ApiAuthController::class)->only(['store'])
        ->middleware('permission:docu-api-auths');


    // API DOCS ENDPOINTS ENDS HERE




    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

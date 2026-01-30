<?php

namespace App\Http\Controllers\Api;


use Throwable;
use App\Models\User;
use App\Mail\SendOtpMail;
use App\Models\ApiClient;
use App\Models\UserToken;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use App\Services\SmileIdService;
use App\Mail\TransactionPinOtpMail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\DTOs\Authentication\LoginDTO;
use App\Services\ExternalBankService;
use App\Services\TransactionPinService;
use App\DTOs\Authentication\ResendOtpDTO;
use App\DTOs\Authentication\VerifyOtpDTO;
use Illuminate\Support\Facades\Validator;
use App\DTOs\Authentication\DeleteUserDTO;
use App\DTOs\Authentication\RegisterUserDTO;
use App\DTOs\Authentication\ResetPasswordDTO;
use App\DTOs\Authentication\UpdateProfileDTO;
use App\DTOs\Authentication\ChangePasswordDTO;
use App\DTOs\Authentication\ForgotPasswordDTO;
use App\Actions\Authentication\LoginUserAction;
use App\Actions\Authentication\VerifyOtpAction;
use App\Actions\Authentication\DeleteUserAction;
use App\Actions\Authentication\RegisterUserAction;
use App\Actions\Authentication\ResetPasswordAction;
use App\Actions\Authentication\ChangePasswordAction;
use App\Actions\Authentication\FetchUserProfileAction;
use App\Notifications\User\TransactionPinNotification;
use App\Actions\Authentication\UpdateUserProfileAction;
use App\Actions\Authentication\ResendVerificationOtpAction;
use App\Actions\Authentication\SendForgotPasswordOtpAction;

class AuthController extends Controller
{

    protected RegisterUserAction $registerUserAction;
    protected ExternalBankService $externalBankService;

    public function __construct(
        RegisterUserAction $registerUserAction,
        ExternalBankService $externalBankService
    ) {
        $this->registerUserAction = $registerUserAction;
        $this->externalBankService = $externalBankService;
    }

    public function register(Request $request)
    {
        try {
            $messages = [
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (e.g. @, #, $, %, &).',
            ];
            $validator = Validator::make($request->all(), [
                'name'          => 'required|string|max:255',
                'email'         => 'required|email|unique:users,email',
                'operator_type' => 'required|in:individual,business',

                // Business only
                'business_type' => 'required_if:operator_type,business|in:co,bn,it',
                'cac_number'    => 'required_if:operator_type,business',
                'cac_document'  => 'required_if:operator_type,business|file',
                'owner_nin'     => 'required_if:operator_type,business',
                'password'        => [
                    'required',
                    'string',
                    'min:6',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{6,}$/'
                ],
            ], $messages);

            if ($validator->fails()) {
                return failureResponse($validator->errors(), 422, 'validation_error');
            }

            /**
             * BUSINESS VERIFICATION FIRST (NO USER CREATED)
             */
            if ($request->operator_type === 'business') {

                $smile = app(SmileIdService::class);

                // temp user object ONLY for Smile ID name extraction
                $tempUser = new User(['name' => $request->name]);

                //CAC verification
                $smile->submitBusinessCAC([
                    'user_id'       => (string) Str::uuid(),
                    'cac_number'    => $request->cac_number,
                    'business_type' => $request->business_type,
                    'cac_document'  => $request->cac_document,
                ]);

                //NIN verification
                $ninResult = $smile->submitNin($tempUser, $request->owner_nin);

                if (! $ninResult['success']) {
                    return failureResponse('Owner NIN verification failed', 422);
                }

                //Name match (CAC owner ↔ NIN)
                if (
                    strtolower(trim($ninResult['details']['full_name'])) !==
                    strtolower(trim($request->name))
                ) {
                    return failureResponse(
                        'Business owner name does not match NIN records',
                        422
                    );
                }
            }

            /**
             * SAFE TO SEND OTP
             */
            $dto  = RegisterUserDTO::fromRequest($request);
            $data = $this->registerUserAction->execute($dto);

            return successResponse(
                'Verification code sent to your email',
                ['reference' => $data['reference']]
            );
        } catch (\Throwable $e) {
            return failureResponse('Registration failed', 500, 'server_error', $e);
        }
    }

    public function adminCreateUser(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $admin = Auth::user();

            if (!$admin) {
                return failureResponse('Unauthorized. Please log in.', 401, 'unauthorized');
            }

            if (!$admin->hasRole('admin')) {
                return failureResponse('Forbidden. Only admins can perform this action.', 403, 'forbidden');
            }
            $messages = [
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (e.g. @, #, $, %, &).',
            ];
            $validator = Validator::make($request->all(), [
                'name'            => 'required|string|max:255',
                'email'           => 'nullable|email|unique:users,email',
                'phone'           => 'nullable|string|unique:users,phone',
                'whatsapp_number' => 'nullable|string|unique:users,whatsapp_number',
                'password'        => [
                    'required',
                    'string',
                    'min:6',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{6,}$/'
                ],
            ], $messages);

            if (
                !$request->email &&
                !$request->phone &&
                !$request->whatsapp_number
            ) {
                return failureResponse(
                    ['contact' => ['Either email, phone number or WhatsApp number is required']],
                    422,
                    'validation_error'
                );
            }

            if ($validator->fails()) {
                return failureResponse($validator->errors()->toArray(), 422, 'validation_error');
            }

            $dto = RegisterUserDTO::fromRequest($request);
            $data = $this->registerUserAction->execute($dto);

            return successResponse("Verification code sent. User must verify OTP to complete registration.", [
                'reference' => $data['reference'],
            ]);
        } catch (\Throwable $th) {
            return failureResponse("Failed to create user", 500, 'server_error', $th);
        }
    }

    public function adminDeleteUser(Request $request, $userId, DeleteUserAction $action)
    {
        try {
            $dto = new DeleteUserDTO(
                adminId: (string) Auth::id(),
                userId: (string) $userId
            );

            $action->execute($dto);

            return successResponse('User deleted successfully.');
        } catch (\Throwable $th) {
            return failureResponse("Failed to delete user", 500, 'server_error', $th);
        }
    }

    public function resendVerificationOtp(Request $request)
    {
        try {
            $dto = ResendOtpDTO::fromRequest($request);
            $data = ResendVerificationOtpAction::execute($dto);

            return successResponse("Verification OTP resent to {$data['channel']}.", $data);
        } catch (\DomainException $e) {
            return failureResponse(
                $e->getMessage(),
                400,
                'resend_otp_error'
            );
        } catch (\InvalidArgumentException $e) {
            return failureResponse(
                $e->getMessage(),
                422,
                'invalid_request'
            );
        } catch (\Throwable $e) {
            return failureResponse(
                "Failed to resend OTP",
                500,
                'server_error',
                $e
            );
        }
    }


    public function verifyOtp(Request $request)
    {
        try {
            $dto = VerifyOtpDTO::fromRequest($request);
            $result = app(VerifyOtpAction::class)->execute($dto);

            $wallet = $result['wallet'];

            // default external balances
            $externalBalance = [
                'available_balance' => 0,
                'book_balance'      => 0,
            ];

            // only fetch if wallet exists + has external reference
            if ($wallet && $wallet->external_reference) {
                try {
                    $externalBalance = $this->externalBankService
                        ->getAccountBalanceCached($wallet->external_reference);
                } catch (\Throwable $e) {
                    Log::warning('Shanono balance fetch failed', [
                        'wallet_id' => $wallet->id,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            return successResponse("OTP verified successfully", [
                'user'   => $result['user'],
                'wallet' => $wallet
                    ? [
                        ...$wallet->toArray(),
                        'external_available_balance' => $externalBalance['available_balance'],
                        'external_book_balance'      => $externalBalance['book_balance'],
                    ]
                    : null,
            ]);
        } catch (\DomainException $e) {
            return failureResponse($e->getMessage(), 400, 'otp_verification_failed');
        } catch (\InvalidArgumentException $e) {
            return failureResponse($e->getMessage(), 422, 'invalid_request');
        } catch (\Throwable $e) {
            return failureResponse("Unexpected error occurred", 500, 'server_error', $e);
        }
    }

    public function login(Request $request)
    {
        try {
            $dto = LoginDTO::fromRequest($request);

            $result = LoginUserAction::execute($dto);

            if ($result['error']) {
                return failureResponse(
                    $result['message'],
                    $result['status']
                );
            }

            return successResponse('Login successful', [
                'user'  => $result['user'],
                'wallet' => $result['wallet'],
                'role'  => $result['role'],
                'token' => $result['token'],

            ]);
        } catch (\Throwable $e) {

            return failureResponse(
                $e->getMessage(),
                500,
                'server_error',
                $e
            );
        }
    }

    public function devices(Request $request)
    {
        try {
            $devices = UserToken::where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get();

            return successResponse("Device list fetched successfully", $devices);
        } catch (\Throwable $th) {
            return failureResponse("Unable to fetch your devices", 500, "server_error", $th);
        }
    }

    public function userDevices(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);

            $devices = UserToken::with([
                'user:id,name,email,phone,whatsapp_number',
                'user.wallet:id,user_id,account_number,bank_name'
            ])
                ->orderByDesc('last_activity')
                ->paginate($perPage)
                ->through(function ($token) {
                    return [
                        'token_id'      => $token->id,
                        'device_name'   => $token->device_name,
                        'ip_address'    => $token->ip_address,
                        'user_agent'    => $token->user_agent,
                        'last_activity' => $token->last_activity,
                        'expires_at'    => $token->expires_at,

                        'user' => [
                            'id'              => $token->user?->id,
                            'name'            => $token->user?->name,
                            'email'           => $token->user?->email,
                            'phone'           => $token->user?->phone,
                            'whatsapp_number' => $token->user?->whatsapp_number,
                            'account_number'  => $token->user?->wallet?->account_number,
                            'bank_name'       => $token->user?->wallet?->bank_name,
                        ],
                    ];
                });

            return successResponse("User device activity fetched successfully", $devices);
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch device activity", 500, "server_error", $th);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user && $user->token()) {
                $user->token()->revoke();
            }

            return successResponse('Logout successful.');
        } catch (\Exception $e) {
            return failureResponse('Failed to logout user.', 500);
        }
    }

    public function profile()
    {
        try {
            $data = FetchUserProfileAction::execute();
            return successResponse("User profile fetched successfully", $data);
        } catch (Throwable $th) {
            return failureResponse("Failed to fetch user profile", 500, 'profile_fetch_error', $th);
        }
    }

    public function bankDetailsStatus(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return failureResponse(
                'User not authenticated',
                401,
                'UNAUTHENTICATED'
            );
        }

        return successResponse(
            'Bank details status fetched successfully',
            [
                'has_bank_details' => $user->hasBankDetails(),
                'bank_details_updated_at' => $user->bank_details_updated_at
                    ? $user->bank_details_updated_at->toDateTimeString()
                    : null,
            ]
        );
    }

    public function updateProfile(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            $dto = UpdateProfileDTO::fromRequest($request);
            $data = UpdateUserProfileAction::execute($dto, $user);

            return successResponse('Profile updated successfully', $data);
        } catch (\Exception $e) {

            return failureResponse(
                $e->getMessage(),
                400,
                'profile_update_failed'
            );
        } catch (\Throwable $th) {

            return failureResponse(
                'Unexpected error while updating profile.',
                500,
                'server_error',
                $th
            );
        }
    }

    protected function sendOtp(string $identifier)
    {
        $otp = rand(100000, 999999);
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->otp_code = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        $message = "{$otp}";

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            Mail::to($identifier)->send(new SendOtpMail($user, $otp));
        } elseif (preg_match('/^234\d{10}$/', $identifier)) {
            (new TermiiService())->sendSms($identifier, $message);
        } elseif (preg_match('/^\+234\d{10}$/', $identifier)) {
            (new TwilioService())->sendWhatsAppMessage($identifier, $message);
        } else {
            logger()->warning("Unsupported identifier format for OTP: {$identifier}");
        }
    }

    public function showById($userId)
    {
        $user = User::with('wallet')->findOrFail($userId);
        return successResponse('User profile retrieved.', ['user' => $user]);
    }

    public function forgotPassword(Request $request)
    {
        try {
            $dto = ForgotPasswordDTO::fromRequest($request);
            $data = SendForgotPasswordOtpAction::execute($dto);

            return successResponse("OTP sent successfully to {$data['channel']}.", [
                'otp' => $data['otp'],
            ]);
        } catch (\Exception $e) {

            return failureResponse(
                $e->getMessage(),
                400,
                'forgot_password_failed'
            );
        } catch (\Throwable $th) {

            return failureResponse(
                "Failed to send OTP.",
                500,
                'otp_error',
                $th
            );
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $dto = ResetPasswordDTO::fromRequest($request);
            ResetPasswordAction::execute($dto);

            return successResponse("Password has been reset successfully.");
        } catch (\Exception $e) {

            return failureResponse(
                $e->getMessage(),
                400,
                'reset_password_error'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                "Unexpected error occurred",
                500,
                'server_error',
                $th
            );
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $dto = ChangePasswordDTO::fromRequest($request);
            ChangePasswordAction::execute($dto);

            return successResponse("Password changed successfully.");
        } catch (\Exception $e) {
            return failureResponse(
                $e->getMessage(),
                400,
                'password_change_failed'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                "Unexpected error occurred",
                500,
                'server_error',
                $th
            );
        }
    }

    public function createPin(Request $request, TransactionPinService $service)
    {
        try {
            $request->validate(['transaction_pin' => 'required|size:4']);

            /** @var \App\Models\User $user */
            $user = Auth::user();
            $service->createPin($user, $request->transaction_pin);

            $user->notify(new TransactionPinNotification(
                "Transaction PIN Created",
                "You have successfully created your transaction PIN."
            ));

            return successResponse("Transaction PIN created successfully.");
        } catch (\Throwable $th) {
            return failureResponse("Failed to create PIN", 400, "CREATE_PIN_ERROR", $th);
        }
    }

    public function changePin(Request $request, TransactionPinService $service)
    {
        try {
            $request->validate([
                'old_pin' => 'required|size:4',
                'new_pin' => 'required|size:4'
            ]);

            $service->changePin(Auth::user(), $request->old_pin, $request->new_pin);

            return successResponse("PIN changed successfully.");
        } catch (\Throwable $th) {
            return failureResponse("Failed to change PIN", 400, "CHANGE_PIN_ERROR", $th);
        }
    }

    public function requestResetPinOTP(Request $request, TransactionPinService $service, TwilioService $twilio, TermiiService $termii)
    {
        try {

            /** @var \App\Models\User $user */
            $user = Auth::user();

            $otp = $service->generateResetPinOTP($user);

            $sentVia = null;

            //Email (highest priority)
            if ($user->email_verified_at) {
                Mail::to($user->email)
                    ->send(new TransactionPinOtpMail($otp, $user->name));
                $sentVia = 'email';
            } elseif ($user->phone_verified_at) {
                $termii->sendSms($user->phone, "Your LoopFreight PIN reset OTP is: {$otp}");
                $sentVia = 'sms';
            } elseif ($user->whatsapp_verified_at) {
                $twilio->sendWhatsAppMessage(
                    $user->whatsapp_number,
                    "Your LoopFreight PIN reset OTP is: {$otp}"
                );
                $sentVia = 'whatsapp';
            } else {
                throw new \Exception(
                    'No verified contact method available.'
                );
            }

            // In-App notification (always safe)
            $user->notify(new TransactionPinNotification(
                "PIN Reset OTP Sent",
                "Your PIN reset OTP was sent via {$sentVia}."
            ));

            return successResponse("OTP sent successfully via {$sentVia}.");
        } catch (\Throwable $th) {
            return failureResponse(
                "Failed to send OTP",
                400,
                "SEND_OTP_ERROR",
                $th
            );
        }
    }

    public function resetPin(Request $request, TransactionPinService $service)
    {
        try {
            $request->validate([
                'otp' => 'required|size:6',
                'new_pin' => 'required|size:4'
            ]);

            $user = Auth::user();
            $service->validateResetOTP($user, $request->otp);
            $service->resetPin($user, $request->new_pin);

            return successResponse("PIN reset successful.");
        } catch (\Throwable $th) {
            return failureResponse("Failed to reset PIN", 400, "RESET_PIN_ERROR", $th);
        }
    }

    public function hasTransactionPin(Request $request)
    {
        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $hasPin = !empty($user->transaction_pin);

            return successResponse("Transaction PIN status retrieved successfully.", [
                'has_transaction_pin' => $hasPin
            ]);
        } catch (\Throwable $th) {
            return failureResponse("Failed to check PIN status", 400, "PIN_STATUS_ERROR", $th);
        }
    }
}

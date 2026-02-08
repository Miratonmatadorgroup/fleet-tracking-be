<?php

namespace App\Http\Controllers\Api;

use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Enums\MerchantStatusEnums;
use App\Http\Controllers\Controller;

class MerchantController extends Controller
{
    /**
     * Suspend a merchant
     */
    public function suspend(Request $request, Merchant $merchant)
    {
        try {
             $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);
            
            if ($merchant->status === MerchantStatusEnums::SUSPENDED) {
                return failureResponse('Merchant is already suspended', 422);
            }

            $merchant->suspend($request->reason);

            return successResponse('Merchant suspended successfully');
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to suspend merchant',
                500,
                'merchant_suspend_error',
                $th
            );
        }
    }

    /**
     * Unsuspend (restore) a merchant
     */
    public function unsuspend(Merchant $merchant)
    {
        try {
            if ($merchant->status !== MerchantStatusEnums::SUSPENDED) {
                return failureResponse('Merchant is not suspended', 422);
            }

            $merchant->update([
                'status' => MerchantStatusEnums::APPROVED,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            return successResponse('Merchant unsuspended successfully');
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to unsuspend merchant',
                500,
                'merchant_unsuspend_error',
                $th
            );
        }
    }
}

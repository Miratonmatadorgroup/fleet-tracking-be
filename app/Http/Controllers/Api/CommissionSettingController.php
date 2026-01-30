<?php

namespace App\Http\Controllers\Api;

use App\Models\Commission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\DTOs\CommissionSettings\UpdateCommissionSettingsDTO;
use App\Actions\CommissionSettings\UpdateCommissionSettingsAction;

class CommissionSettingController extends Controller
{
    public function updateCommissionSettings(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('admin')) {
            return failureResponse('Unauthorized. Admins only.', 403);
        }

        try {
            $dto = UpdateCommissionSettingsDTO::fromRequest($request);
            $commissions = UpdateCommissionSettingsAction::execute($dto);

            return successResponse('Commission settings updated successfully.', $commissions);
        } catch (\Exception $e) {
            return failureResponse(
                $e->getMessage(),
                400,
                'update_commissions_error'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to update commission settings.',
                500,
                'server_error',
                $th
            );
        }
    }

    public function listCommissions()
    {
        $user = Auth::user();

        if (!$user || !$user->hasRole('admin')) {
            return failureResponse('Unauthorized. Admins only.', 403);
        }

        try {
            $commissions = Commission::all();
            return successResponse('List of commission settings retrieved successfully.', $commissions);
        } catch (\Exception $e) {
            return failureResponse(
                $e->getMessage(),
                400,
                'list_commissions_error'
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to retrieve commission settings.',
                500,
                'server_error',
                $th
            );
        }
    }
}

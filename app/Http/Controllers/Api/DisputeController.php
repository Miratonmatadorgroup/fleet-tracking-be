<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\DisputeStatusEnums;
use App\Http\Controllers\Controller;
use App\DTOs\Dispute\ListDisputesDTO;
use App\DTOs\Dispute\ReportDisputeDTO;
use Illuminate\Support\Facades\Validator;
use App\Actions\Dispute\ListDisputesAction;
use App\Actions\Dispute\ReportDisputeAction;
use App\DTOs\Dispute\UpdateDisputeStatusDTO;
use App\Actions\Dispute\UpdateDisputeStatusAction;

class DisputeController extends Controller
{
    public function reportDispute(Request $request, ReportDisputeAction $action)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'tracking_number' => 'nullable|string|max:100',
                'driver_contact' => 'nullable|string|max:20',
                'attachment' => 'nullable|file|max:5120',
            ]);

            if ($validator->fails()) {
                return failureResponse([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dto = ReportDisputeDTO::fromRequest($request);
            $dispute = $action->execute($dto);

            return successResponse('Dispute reported successfully.', $dispute);
        } catch (Throwable $th) {
            return failureResponse('Failed to report dispute.', 500, 'dispute_error', $th);
        }
    }

    public function updateStatus(Request $request, string $dispute_id, UpdateDisputeStatusAction $action)
    {
        try {
            $request->validate([
                'action' => ['required', Rule::in(DisputeStatusEnums::values())],
            ]);

            $dispute = Dispute::findOrFail($dispute_id);
            $requestedStatus = DisputeStatusEnums::from($request->action);

            if ($dispute->status === DisputeStatusEnums::RESOLVED && $requestedStatus !== DisputeStatusEnums::RESOLVED) {
                return failureResponse("You cannot change the status of a resolved dispute.", 403);
            }

            $dto = UpdateDisputeStatusDTO::fromRequest($request, $dispute_id);
            $updatedDispute = $action->execute($dto);

            return successResponse("Dispute status updated to {$dto->action}.", $updatedDispute);
        } catch (\Throwable $th) {
            return failureResponse("Failed to update dispute.", 500, 'dispute_status_error', $th);
        }
    }

    public function viewDisputes(Request $request, ListDisputesAction $action)
    {
        try {
            $dto = ListDisputesDTO::fromRequest($request);
            $disputes = $action->execute($dto);

            return successResponse("Disputes fetched successfully.", $disputes);
        } catch (\Throwable $th) {
            return failureResponse("Failed to fetch disputes.", 500, 'list_disputes_error', $th);
        }
    }
}

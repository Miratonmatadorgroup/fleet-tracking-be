<?php

namespace App\Http\Controllers\Api;


use Throwable;
use Illuminate\Http\Request;
use App\Models\TransportMode;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Validation\Rule;
use App\Enums\TransportModeEnums;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Enums\TransportModeCategoryEnums;
use App\Actions\TransportMode\ViewRidesAction;
use App\DTOs\TransportMode\DeleteTransportModeDTO;
use App\DTOs\TransportMode\AdminStoreTransportModeDTO;
use App\Actions\TransportMode\DeleteTransportModeAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Actions\TransportMode\AdminStoreTransportModeAction;
use App\Actions\TransportMode\AdminViewTransportModesAction;
use App\DTOs\TransportMode\AdminAssignDriverToTransportModeDTO;
use App\DTOs\TransportMode\AdminUnassignDriverFromTransportModeDTO;
use App\Actions\TransportMode\AdminAssignDriverToTransportModeAction;
use App\Actions\TransportMode\AdminUnassignDriverFromTransportModeAction;

class TransportModeController extends Controller
{
    public function adminStoreTransportMode(Request $request, AdminStoreTransportModeAction $action)
    {
        $request->merge([
            'driver_id' => $request->filled('driver_id') ? $request->input('driver_id') : null,
        ]);

        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
            'type' => ['required', Rule::in(array_column(TransportModeEnums::cases(), 'value'))],
            'category' => ['required', Rule::in(array_column(TransportModeCategoryEnums::cases(), 'value'))],
            'manufacturer' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'registration_number' => 'required|string|max:100|unique:transport_modes,registration_number',
            'year_of_manufacture' => 'nullable|digits:4|integer|min:1900|max:' . now()->year,
            'color' => 'nullable|string|max:100',
            'passenger_capacity' => 'nullable|integer|min:1',
            'max_weight_capacity' => 'nullable|numeric|min:0',
            'photo' => 'nullable|file|max:5120',
            'registration_document' => 'nullable|file|max:5120',
        ]);

        try {
            $dto = new AdminStoreTransportModeDTO($validated);
            $transport = $action->execute($dto);

            return successResponse('Transport mode created successfully', $transport);
        } catch (Throwable $th) {
            return failureResponse(
                'Failed to create transport mode',
                500,
                'transport_mode_creation_error',
                $th
            );
        }
    }

    public function adminDeleteTransportMode(Request $request, string $id, DeleteTransportModeAction $action)
    {
        try {
            $dto = new DeleteTransportModeDTO(
                adminId: Auth::id(),
                transportModeId: $id
            );

            $action->execute($dto);

            return successResponse('Transport mode deleted successfully.');
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage() ?? 'Failed to delete transport mode.',
                $th->getCode() ?: 500,
                'server_error',
                $th
            );
        }
    }

    public function adminViewListTransportMode(Request $request, AdminViewTransportModesAction $action)
    {
        try {
            $search  = $request->input('search');
            $perPage = $request->input('per_page', 10);

            $transportModes = $action->execute($search, $perPage);

            return successResponse(
                'Transport modes retrieved successfully.',
                $transportModes
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to fetch transport modes.',
                500,
                'transport_mode_fetch_error',
                $th
            );
        }
    }


    public function adminAssignDriverToTransportMode(
        Request $request,
        TwilioService $twilio,
        TermiiService $termii,
        AdminAssignDriverToTransportModeAction $action
    ) {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'transport_mode_id' => 'required|exists:transport_modes,id',
        ]);

        try {
            $dto = new AdminAssignDriverToTransportModeDTO($validated);
            $transport = $action->execute($dto, $twilio, $termii);

            return successResponse("Driver assigned and notified successfully.", [
                'transport_mode' => $transport
            ]);
        } catch (ModelNotFoundException $e) {
            return failureResponse($e->getMessage(), 404, 'driver_not_found');
        } catch (\Throwable $th) {
            return failureResponse(
                $th->getMessage(),
                $th->getCode() >= 400 && $th->getCode() < 600 ? $th->getCode() : 500,
                'driver_assignment_error',
                $th
            );
        }
    }

    public function adminUnassignDriverFromTransportMode(
        Request $request,
        TwilioService $twilio,
        TermiiService $termii,
        AdminUnassignDriverFromTransportModeAction $action
    ) {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'transport_mode_id' => 'required|uuid|exists:transport_modes,id',
        ]);

        try {
            $dto = new AdminUnassignDriverFromTransportModeDTO($validated);
            $result = $action->execute($dto, $twilio, $termii);

            return successResponse("Driver has been unassigned from the transport mode successfully.", $result);
        } catch (ModelNotFoundException $e) {
            return failureResponse($e->getMessage(), 404, 'driver_not_found');
        } catch (Throwable $th) {
            $status = $th->getCode() >= 400 && $th->getCode() < 600 ? $th->getCode() : 500;

            return failureResponse(
                $th->getMessage(),
                $status,
                'driver_unassignment_error',
                $th
            );
        }
    }

    public function transportModeCount()
    {
        try {
            $count = TransportMode::count();
            return successResponse('Total number of transport modes fetched successfully', [
                'total_transport_modes' => $count
            ]);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch transport mode count', 500, 'count_error', $th);
        }
    }


    public function viewRides(Request $request, ViewRidesAction $action)
    {
        try {
            $search  = $request->input('search');
            $perPage = $request->input('per_page', 10);

            $transportModes = $action->execute($search, $perPage);

            return successResponse(
                'Passenger transport modes retrieved successfully.',
                $transportModes
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to fetch passenger transport modes.',
                500,
                'passenger_transport_mode_fetch_error',
                $th
            );
        }
    }
}

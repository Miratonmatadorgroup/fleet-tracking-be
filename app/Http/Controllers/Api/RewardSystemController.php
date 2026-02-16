<?php

namespace App\Http\Controllers\Api;

use App\Models\RewardClaim;
use Illuminate\Http\Request;
use App\Models\RewardCampaign;
use Illuminate\Support\Facades\DB;
use App\DTOs\Rewards\ClaimRewardDTO;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Actions\Rewards\ClaimRewardAction;
use App\DTOs\Rewards\RewardCampaignDataDTO;
use App\Actions\Rewards\CreateRewardCampaignAction;
use App\Actions\Rewards\UpdateRewardCampaignAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class RewardSystemController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, CreateRewardCampaignAction $action)
    {
        Auth::user();
        $dto = RewardCampaignDataDTO::fromRequest($request);
        $campaign = $action->execute($dto);

        return successResponse('Campaign created successfully', $campaign);
    }

    public function update(Request $request, string $id, UpdateRewardCampaignAction $action)
    {
        $campaign = RewardCampaign::findOrFail($id);
        $dto = RewardCampaignDataDTO::fromRequest($request);
        $updatedCampaign = $action->execute($campaign, $dto);

        return successResponse('Campaign updated successfully', $updatedCampaign);
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = trim($request->query('search'));
        $query = RewardCampaign::orderBy('created_at', 'desc');

        if (!empty($search)) {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator, $driver) {
                $q->where('name', $likeOperator, "%{$search}%")
                    ->orWhere('id', $likeOperator, "%{$search}%");

                if ($driver === 'pgsql') {
                    $q->orWhereRaw("CAST(reward_amount AS TEXT) {$likeOperator} ?", ["%{$search}%"]);
                } else {
                    $q->orWhereRaw("CAST(reward_amount AS CHAR) {$likeOperator} ?", ["%{$search}%"]);
                }
            });
        }

        $campaigns = $query->paginate($perPage);

        return successResponse('Reward campaigns fetched successfully', $campaigns);
    }


    public function activate(RewardCampaign $campaign)
    {
        Auth::user();

        $campaign->update(['active' => true]);
        return successResponse('Reward Campaign activated', $campaign);
    }

    public function suspend(RewardCampaign $campaign)
    {
        Auth::user();

        $campaign->update(['active' => false]);
        return successResponse('Reward Campaign suspended', $campaign);
    }

    public function claim(Request $request, ClaimRewardAction $action)
    {
        try {
            $dto = ClaimRewardDTO::fromRequest($request);
            $claim = $action->execute($dto);

            return successResponse('Reward successfully claimed.', $claim);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return failureResponse('Reward claim not found or already processed.', 404, 'NOT_FOUND', $e);
        } catch (\Exception $e) {
            return failureResponse($e->getMessage(), 422, 'CLAIM_FAILED', $e);
        } catch (\Throwable $th) {
            return failureResponse('An unexpected error occurred while claiming the reward.', 500, 'SERVER_ERROR', $th);
        }
    }

    public function driverAvailableReward(Request $request)
    {
        $driver = $request->user();

        if (!$driver) {
            return failureResponse('Unauthorized. Please login to continue.', 401);
        }

        $search = $request->query('search');

        $query = RewardClaim::with(['campaign'])
            ->where('driver_id', $driver->id);

        if ($search) {
            $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $operator) {
                $q->where('id', $operator, "%{$search}%")
                    ->orWhere('reward_campaign_id', $operator, "%{$search}%")
                    ->orWhere('driver_id', $operator, "%{$search}%")
                    ->orWhere('amount', $operator, "%{$search}%")
                    ->orWhere('status', $operator, "%{$search}%")
                    ->orWhere('wallet_transaction_id', $operator, "%{$search}%")
                    ->orWhere('notes', $operator, "%{$search}%")
                    ->orWhere('claim_period', $operator, "%{$search}%")
                    ->orWhere('created_at', $operator, "%{$search}%")
                    ->orWhere('updated_at', $operator, "%{$search}%");
            });
        }

        $claims = $query->orderByDesc('created_at')->paginate(10);
        $formatted = [
            'current_page' => $claims->currentPage(),
            'data' => $claims->items(),
            'from' => $claims->firstItem(),
            'last_page' => $claims->lastPage(),
            'per_page' => $claims->perPage(),
            'to' => $claims->lastItem(),
            'total' => $claims->total(),
        ];

        return successResponse(
            'Reward claims retrieved successfully.',
            $formatted
        );
    }


    public function destroy(string $id)
    {
        $campaign = RewardCampaign::findOrFail($id);
        $campaign->delete();

        return successResponse('Campaign deleted successfully');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Payment;
use App\Models\Investor;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\InvestmentPlan;
use App\Enums\PaymentMethodEnums;
use App\Enums\PaymentStatusEnums;
use App\Enums\InvestorStatusEnums;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Enum;
use App\DTOs\Investor\InvestorListDTO;
use App\DTOs\Investor\InvestmentPlanDTO;
use App\DTOs\Investor\AppliedInvestorsDTO;
use App\DTOs\Investor\ApprovedInvestorsDTO;
use App\Events\Investor\InvestorListViewed;
use App\Enums\InvestorWithdrawalStatusEnums;
use App\DTOs\Investor\InvestorApplicationDTO;
use App\Enums\InvestorApplicationStatusEnums;
use App\DTOs\Investor\UpdateInvestmentPlanDTO;
use App\Services\InvestorRefundMessageService;
use App\Actions\Investor\GetInvestorListAction;
use App\Services\InvestorWithdrawalMessageService;
use App\Actions\Investor\GetInvestorEarningsAction;
use App\Actions\Investor\StoreInvestmentPlanAction;
use App\Actions\Investor\DeleteInvestmentPlanAction;
use App\Actions\Investor\UpdateInvestmentPlanAction;
use App\Actions\Investor\FetchAppliedInvestorsAction;
use App\Notifications\InvestmentRefundedNotification;
use App\Actions\Investor\FetchApprovedInvestorsAction;
use App\DTOs\Investor\AdminApproveOrRejectInvestorDTO;
use App\Actions\Investor\StoreInvestorApplicationAction;
use App\Http\Controllers\Api\InvestmentPaymentController;
use App\Actions\Investor\InvestorApplicationDecisionAction;
use App\Notifications\Admin\InvestorWithdrawalNotification;

class InvestorController extends Controller
{
    public function investorApplicationForm(Request $request, StoreInvestorApplicationAction $action)
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return failureResponse('Unauthorized. Please login to apply.', 401, 'UNAUTHORIZED');
            }

            $dto = InvestorApplicationDTO::fromRequest($request->all(), $user);
            $application = $action->execute($dto);

            return successResponse('Investor application successful.', $application);
        } catch (\Throwable $th) {
            return failureResponse(
                'Unable to process investor application.',
                400,
                'INVESTOR_APPLICATION_ERROR',
                $th
            );
        }
    }


    // FOR REINVESTMENT STARTS HERE
    public function reinvest(Request $request)
    {
        $request->validate([
            'investment_plan_id' => 'required|exists:investment_plans,id',
            'payment_method'     => ['nullable', new Enum(PaymentMethodEnums::class)],
        ]);

        $user = Auth::user();
        if (! $user) {
            return failureResponse('Unauthorized.', 401);
        }

        // Ensure investor is approved
        $investor = Investor::where('user_id', $user->id)
            ->where('application_status', InvestorApplicationStatusEnums::APPROVED)
            ->first();

        if (! $investor) {
            return failureResponse('You are not approved as an investor.', 403);
        }

        // Forward to reinvestInitiate
        $initiateRequest = new Request([
            'investor_id'        => $investor->id,
            'investment_plan_id' => $request->investment_plan_id,
            'payment_method'     => $request->payment_method,
        ]);

        $investmentPaymentController = App::make(InvestmentPaymentController::class);
        return $investmentPaymentController->reinvestInitiate($initiateRequest);
    }

    // FOR REINVESMENT ENDS HERE

    public function withdrawInvestment(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return failureResponse('Unauthorized.', 401);
        }

        // Find approved investor
        $investor = Investor::where('user_id', $user->id)
            ->where('application_status', InvestorApplicationStatusEnums::APPROVED)
            ->first();

        if (!$investor) {
            return failureResponse('You are not approved as an investor.', 403);
        }

        if ($investor->status !== InvestorStatusEnums::ACTIVE) {
            return failureResponse('You have no active investments to withdraw.');
        }

        DB::beginTransaction();
        try {
            $investor->status = InvestorStatusEnums::INACTIVE->value;
            $investor->withdrawn_at = now();
            $investor->withdraw_status = InvestorWithdrawalStatusEnums::PROCESSING;
            $investor->save();

            // Notify admins
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new InvestorWithdrawalNotification($investor));
            }

            app(InvestorWithdrawalMessageService::class)->notifyInvestor($investor);


            DB::commit();

            return successResponse('Withdrawal request submitted successfully. Admin will process your refund soon.', [
                'investor_id' => $investor->id,
                'full_name'   => $investor->full_name,
                'status'      => $investor->status,
                'withdraw_status' => $investor->withdraw_status,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error processing withdrawal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return failureResponse('Failed to process your withdrawal request.');
        }
    }

    public function markRefunded(Request $request, $investorId)
    {
        $request->validate([
            'refund_note' => 'nullable|string|max:500',
        ]);

        $admin = Auth::user();
        if (!$admin || !$admin->hasRole('admin')) {
            return failureResponse('Unauthorized. Only admins can perform this action.', 403);
        }

        $investor = Investor::with('user')->find($investorId);
        if (!$investor) {
            return failureResponse('Investor not found.', 404);
        }

        if ($investor->withdraw_status !== InvestorWithdrawalStatusEnums::PROCESSING) {
            return failureResponse('This withdrawal is not processing or has already been refunded.');
        }

        DB::beginTransaction();
        try {
            $investor->withdraw_status = InvestorWithdrawalStatusEnums::REFUNDED;
            $investor->refunded_at     = now();
            $investor->refund_note     = $request->input('refund_note');
            $investor->save();

            $investor->user->notify(new InvestmentRefundedNotification($investor));
            app(InvestorRefundMessageService::class)->notifyInvestor($investor);

            $responseData = [
                'investor_id'    => $investor->id,
                'status'         => $investor->status,
                'withdraw_status' => $investor->withdraw_status,
                'refunded_at'    => $investor->refunded_at,
                'refund_note'    => $investor->refund_note,
            ];

            // Delete the investor profile (retain user account)
            $investor->delete();

            DB::commit();

            return successResponse('Investor refund marked successfully.', $responseData);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error marking investor as refunded', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return failureResponse('Failed to mark investor as refunded.');
        }
    }

    public function checkApplication()
    {
        $user = Auth::user();

        if (! $user) {
            return failureResponse('Unauthorized.', 401, 'UNAUTHORIZED');
        }

        $application = Investor::where('user_id', $user->id)
            ->latest()
            ->first();

        if (! $application) {
            return successResponse('No application found.', [
                'has_application' => false,
                'needs_payment'   => false,
            ]);
        }

        $needsPayment = $application->status !== InvestorStatusEnums::ACTIVE;

        return successResponse('Application status retrieved.', [
            'has_application' => true,
            'needs_payment'   => $needsPayment,
            'application'     => $application,
        ]);
    }

    public function decideInvestorApplication(Request $request, InvestorApplicationDecisionAction $action)
    {
        try {
            $dto = AdminApproveOrRejectInvestorDTO::fromRequest($request);

            $investor = \App\Models\Investor::findOrFail($dto->investorId);

            // Ensure investor is active
            if ($investor->status !== InvestorStatusEnums::ACTIVE) {
                return failureResponse('Only active investors can be approved or rejected.', 403);
            }

            // Prevent changing a final decision
            if (
                ($investor->application_status === InvestorApplicationStatusEnums::APPROVED && $dto->action === 'reject') ||
                ($investor->application_status === InvestorApplicationStatusEnums::REJECTED && $dto->action === 'approve')
            ) {
                return failureResponse('This application has already been decided and cannot be changed.', 403);
            }

            $updatedInvestor = $action->execute($dto);

            return successResponse("Investor application has been {$dto->action}d.", $updatedInvestor);
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to process investor application decision.',
                400,
                'INVESTOR_DECISION_ERROR',
                $th
            );
        }
    }

    public function index(Request $request, GetInvestorListAction $action)
    {
        try {
            $dto = new InvestorListDTO($request->all());
            $search = $request->input('search');

            $investors = $action->execute($dto, $search);

            event(new InvestorListViewed($request->only('status')));

            return successResponse('Investors retrieved successfully', $investors);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch investors', 500, 'fetch_error', $th);
        }
    }



    public function investorCount()
    {
        try {
            $count = Investor::where('application_status', InvestorApplicationStatusEnums::APPROVED)->count();

            return successResponse('Total number of investors fetched successfully', [
                'total_investors' => $count
            ]);
        } catch (\Throwable $th) {
            return failureResponse('Failed to fetch investor count', 500, 'count_error', $th);
        }
    }

    public function approvedInvestors(Request $request, FetchApprovedInvestorsAction $action)
    {
        try {
            $dto = ApprovedInvestorsDTO::fromRequest($request);
            $investors = $action->execute($dto);

            return successResponse('Approved investors fetched successfully.', $investors);
        } catch (\Throwable $e) {
            Log::error('Error fetching approved investors', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return failureResponse('Failed to fetch approved investors.', 500);
        }
    }

    public function appliedInvestors(Request $request, FetchAppliedInvestorsAction $action)
    {
        try {
            $dto = AppliedInvestorsDTO::fromRequest($request);
            $search = $request->input('search');

            $investors = $action->execute($dto, $dto->search);

            return successResponse('Applied investors fetched successfully.', $investors);
        } catch (\Throwable $e) {
            Log::error('Error fetching applied investors', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return failureResponse('Failed to fetch applied investors.', 500);
        }
    }


    public function investorEarnings(Request $request, GetInvestorEarningsAction $action)
    {
        $investor = $request->user();

        $dto = $action->execute($investor);


        return successResponse("Investor earnings fetched successfully", $dto->toArray());
    }

    // INVESTMENT PLANS STARTS HERE
    public function viewInvestmentPlans()
    {
        $plans = InvestmentPlan::orderBy('amount', 'asc')->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'label' => "{$plan->name} – ₦" . number_format($plan->amount, 2),
                'value' => $plan->id,
                'amount' => $plan->amount,
            ];
        });

        return successResponse('Plans fetched', $plans);
    }


    public function adminViewInvestmentPlans(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->query('search');

        $query = InvestmentPlan::orderBy('created_at', 'desc');

        if (!empty($search)) {
            $driver = DB::connection()->getDriverName();
            $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator, $driver) {
                $q->where('name', $likeOperator, "%{$search}%")
                    ->orWhere('id', $likeOperator, "%{$search}%");

                if (is_numeric($search)) {
                    if ($driver === 'pgsql') {
                        $q->orWhereRaw("CAST(amount AS TEXT) = ?", [$search]);
                    } else {
                        $q->orWhereRaw("CAST(amount AS CHAR) = ?", [$search]);
                    }
                }
            });
        }

        $plans = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Plans fetched',
            'data' => $plans->items(),
        ]);
    }

    public function storeInvestmentPlans(Request $request, StoreInvestmentPlanAction $action)
    {
        $dto = InvestmentPlanDTO::fromRequest($request->all());
        $plan = $action->execute($dto);

        return successResponse('Plan created', $plan);
    }

    public function updateInvestmentPlans(Request $request, $id, UpdateInvestmentPlanAction $action)
    {
        $dto = UpdateInvestmentPlanDTO::fromRequest($request->all());
        $plan = $action->execute($dto, $id);

        return successResponse('Plan updated', $plan);
    }

    public function destroyInvestmentPlans($id, DeleteInvestmentPlanAction $action)
    {
        $action->execute($id);
        return successResponse('Plan deleted');
    }

    // INVESTMENT PLANS ENDS HERE

    public function totalInvestedFunds()
    {
        try {
            $driver = DB::connection()->getDriverName();

            $castExpression = match ($driver) {
                'pgsql' => 'SUM(investment_amount::numeric)',
                'mysql' => 'SUM(CAST(investment_amount AS DECIMAL(15,2)))',
                default => 'SUM(investment_amount)',
            };

            $totalInvested = Investor::query()
                ->where('status', InvestorStatusEnums::ACTIVE->value)
                ->where('application_status', InvestorApplicationStatusEnums::APPROVED->value)
                ->select(DB::raw("$castExpression as total"))
                ->value('total');

            return successResponse(
                'Total invested funds retrieved successfully.',
                [
                    'total_invested' => (float) ($totalInvested ?? 0),
                ]
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to calculate total invested funds.',
                500,
                'server_error',
                $th
            );
        }
    }
}

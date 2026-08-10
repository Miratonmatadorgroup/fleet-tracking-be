<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminAnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected SuperAdminAnalyticsService $analyticsService
    ) {}

    public function superAdminAnalytics(Request $request)
    {
        try {
            $analytics = $this->analyticsService->getAnalytics();

            return successResponse(
                'Super admin analytics retrieved successfully',
                $analytics
            );
        } catch (\Throwable $th) {
            return failureResponse(
                'Failed to retrieve super admin analytics',
                500,
                'super_admin_analytics_error',
                $th
            );
        }
    }
}

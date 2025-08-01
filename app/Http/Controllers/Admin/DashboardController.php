<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{

    public function __construct(protected DashboardService $dashboardService) {}

    public function summary(): JsonResponse
    {
        return response()->json($this->dashboardService->getSummary());
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Models\Domain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class DashboardService
{

    public function __construct(
        protected CcmsApiService $ccmsApiService,
        protected JobFinderApiService $jobFinderApiService,
        protected SolucompApiService $solucompApiService,
        protected SamsungApiService $samsungApiService,
    ) {}
    public function getSummary(): array
    {
        $ccmsSummary =  $this->ccmsApiService->fetchSummary();

        $jobFinderSummary =  $this->jobFinderApiService->fetchSummary();

        $solucompSummary =  $this->solucompApiService->fetchSummary();

        $samsungSummary =  $this->samsungApiService->fetchSummary();

        $now = Carbon::now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endOfPreviousMonth = $startOfCurrentMonth->copy()->subSecond();

        // Base counts
        $totalUsers = User::where('role', '!=', 'admin')->count();
        $totalDomains = Domain::count();
        $totalSessions24h = DB::table('sessions')
            ->where('last_activity', '>=', Carbon::now()->subDay()->timestamp)
            ->distinct('id')
            ->count('id');

        // Monthly comparisons
        $previousUsers = User::where('role', '!=', 'admin')->where('created_at', '<', $startOfCurrentMonth)->count();
        $currentUsers = $totalUsers;

        $previousAdmins = User::where('role', 'admin')
            ->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->count();
        $currentAdmins = User::where('role', 'admin')
            ->whereBetween('created_at', [$startOfCurrentMonth, $now])
            ->count();

        $previousDomains = Domain::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $currentDomains = Domain::whereBetween('created_at', [$startOfCurrentMonth, $now])->count();

        // 24-hour session growth
        $startOfPrevious24h = $now->copy()->subDays(2);
        $endOfPrevious24h = $now->copy()->subDay();

        $previousSessions = DB::table('sessions')
            ->whereBetween('last_activity', [$startOfPrevious24h->timestamp, $endOfPrevious24h->timestamp])
            ->count();

        $currentSessions = DB::table('sessions')
            ->where('last_activity', '>=', $endOfPrevious24h->timestamp)
            ->count();

        return [
            'ccms' => $ccmsSummary,
            'job_finder' => $jobFinderSummary,
            'solucomp' => $solucompSummary,
            'samsung' => $samsungSummary,
            'total_users' => $totalUsers,
            'total_domains' => $totalDomains,
            'total_sessions_24h' => $totalSessions24h,

            'user_growth' => [
                'previous_month' => $previousUsers,
                'current_month' => $currentUsers,
                'growth_rate' => $this->calculateGrowthRate($previousUsers, $currentUsers),
            ],

            'admin_growth' => [
                'previous_month' => $previousAdmins,
                'current_month' => $currentAdmins,
                'growth_rate' => $this->calculateGrowthRate($previousAdmins, $currentAdmins),
            ],

            'domain_growth' => [
                'previous_month' => $previousDomains,
                'current_month' => $currentDomains,
                'growth_rate' => $this->calculateGrowthRate($previousDomains, $currentDomains),
            ],

            'session_growth' => [
                'previous_month' => $previousSessions,
                'current_month' => $currentSessions,
                'growth_rate' => $this->calculateGrowthRate($previousSessions, $currentSessions),
            ],
        ];
    }

    private function calculateGrowthRate(int $previous, int $current): ?float
    {
        if ($previous === 0) {
            return null;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }
}

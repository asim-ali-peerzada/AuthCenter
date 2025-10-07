<?php

namespace App\Services;

use App\Models\User;
use App\Models\Domain;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{

    public function __construct(
        protected CcmsApiService $ccmsApiService,
        protected JobFinderApiService $jobFinderApiService,
        protected SolucompApiService $solucompApiService,
        protected SamsungApiService $samsungApiService,
    ) {}
    // Auth Center Dashboard Data
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
        $totalDomains = Domain::where('key', '!=', 'solucomp')->count();
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

        $previousDomains = Domain::where('key', '!=', 'solucomp')->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $currentDomains = Domain::where('key', '!=', 'solucomp')->whereBetween('created_at', [$startOfCurrentMonth, $now])->count();

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

    // Site Access Info Dashboard Data
    public function getDashboardData(): array
    {
        // Site Access Info Users Count (include users where external_role is not 'Admin' or is null)
        $siteAccessUsers = User::where('user_origin', 'site_access_info')
            ->where(function ($query) {
                $query->whereNull('external_role')
                      ->orWhere('external_role', '!=', 'Admin');
            })
            ->count();

        // Site Access Info Admins Count
        $siteAccessAdmins = User::where('user_origin', 'site_access_info')
            ->where('external_role', 'Admin')
            ->count();

        // Site Access Files Count (assuming this is a table/model)
        $siteAccessFiles = DB::table('site_access_files')->count();

        // Small Cell Sites Count (from Site model)
        $smallCellSites = DB::table('sites')->count();

        // Hub Sites Count (from Hub model)
        $hubSites = DB::table('hubs')->count();

        // System Performance Metrics
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfPreviousWeek = $now->copy()->subWeek()->startOfWeek();
        $endOfPreviousWeek = $now->copy()->subWeek()->endOfWeek();

        // Import statistics for this week
        $importsThisWeek = DB::table('site_access_files')
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        // Import statistics for this month
        $importsThisMonth = DB::table('site_access_files')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Success vs Failure rate for this week
        $importsStatusThisWeek = DB::table('site_access_files')
            ->where('created_at', '>=', $startOfWeek)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $completedThisWeek = $importsStatusThisWeek->get('completed', (object)['count' => 0])->count;
        $failedThisWeek = $importsStatusThisWeek->get('failed', (object)['count' => 0])->count;
        $pendingThisWeek = $importsStatusThisWeek->get('pending', (object)['count' => 0])->count;
        $processingThisWeek = $importsStatusThisWeek->get('processing', (object)['count' => 0])->count;

        $totalProcessedThisWeek = $completedThisWeek + $failedThisWeek;
        $successRateThisWeek = $totalProcessedThisWeek > 0 ? round(($completedThisWeek / $totalProcessedThisWeek) * 100, 2) : 0;
        $failureRateThisWeek = $totalProcessedThisWeek > 0 ? round(($failedThisWeek / $totalProcessedThisWeek) * 100, 2) : 0;

        // User growth for this week vs previous week
        $usersThisWeek = User::where('user_origin', 'site_access_info')
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        $usersPreviousWeek = User::where('user_origin', 'site_access_info')
            ->whereBetween('created_at', [$startOfPreviousWeek, $endOfPreviousWeek])
            ->count();

        $userGrowthRate = $this->calculateGrowthRate($usersPreviousWeek, $usersThisWeek);

        // Recent Activities for Site Access Info users
        $recentActivities = UserActivity::whereHas('user', function ($query) {
            $query->where('user_origin', 'site_access_info');
        })
            ->select('event_type', 'ip_address', 'user_agent', 'event_time')
            ->orderBy('event_time', 'desc')
            ->limit(10)
            ->get();

        return [
            'stats' => [
                'site_access_users' => $siteAccessUsers,
                'site_access_admins' => $siteAccessAdmins,
                'site_access_files' => $siteAccessFiles,
                'small_cell' => $smallCellSites,
                'hub_sites' => $hubSites,
            ],
            'system_performance' => [
                'imports_this_week' => $importsThisWeek,
                'imports_this_month' => $importsThisMonth,
                'success_rate_this_week' => $successRateThisWeek,
                'failure_rate_this_week' => $failureRateThisWeek,
                'completed_imports' => $completedThisWeek,
                'failed_imports' => $failedThisWeek,
                'pending_imports' => $pendingThisWeek,
                'processing_imports' => $processingThisWeek,
            ],
            'user_growth' => [
                'users_this_week' => $usersThisWeek,
                'users_previous_week' => $usersPreviousWeek,
                'growth_rate' => $userGrowthRate,
            ],
            'recent_activities' => $recentActivities,
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

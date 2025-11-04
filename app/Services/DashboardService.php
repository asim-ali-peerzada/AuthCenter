<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Promise;

class DashboardService
{

    public function __construct(
        protected CcmsApiService $ccmsApiService,
        protected JobFinderApiService $jobFinderApiService,
        protected SolucompApiService $solucompApiService,
        protected SamsungApiService $samsungApiService,
    ) {}

    /**
     * Auth Center Dashboard Data
     */
    public function getSummary(): array
    {
        $cacheKey = 'dashboard:summary:v1';
        $ttl = now()->addMinutes(5);

        return Cache::remember($cacheKey, $ttl, function () {
            $apiPromises = [
                'ccms' => $this->ccmsApiService->fetchSummary(),
                'job_finder' => $this->jobFinderApiService->fetchSummary(),
                'solucomp' => $this->solucompApiService->fetchSummary(),
                'samsung' => $this->samsungApiService->fetchSummary(),
            ];

            // Wait for all API calls to complete and settle the results
            $apiResults = Promise\Utils::settle($apiPromises)->wait();
            $summaries = [
                'ccms' => $apiResults['ccms']['value'] ?? [],
                'job_finder' => $apiResults['job_finder']['value'] ?? [],
                'solucomp' => $apiResults['solucomp']['value'] ?? [],
                'samsung' => $apiResults['samsung']['value'] ?? [],
            ];

            $now = Carbon::now();
            $startOfCurrentMonth = $now->copy()->startOfMonth();
            $startOfPreviousMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $endOfPreviousMonth = $startOfCurrentMonth->copy()->subSecond();
            $startOfCurrentDay = $now->copy()->startOfDay();
            $startOfPreviousDay = $now->copy()->subDay()->startOfDay();

            // Consolidate multiple queries into a single, efficient query
            $stats = DB::table('users')
                ->selectRaw('
                    COUNT(CASE WHEN role != "admin" THEN 1 END) as total_users,
                    COUNT(CASE WHEN role = "admin" AND created_at BETWEEN ? AND ? THEN 1 END) as current_admins,
                    COUNT(CASE WHEN role = "admin" AND created_at BETWEEN ? AND ? THEN 1 END) as previous_admins,
                    COUNT(CASE WHEN role != "admin" AND created_at >= ? THEN 1 END) as current_month_users
                ', [
                    $startOfCurrentMonth, $now,
                    $startOfPreviousMonth, $endOfPreviousMonth,
                    $startOfCurrentMonth
                ])
                ->first();

            // Separate query for domains as it's a different table
            $domainStats = DB::table('domains')
            ->selectRaw('
                COUNT(CASE WHEN `key` != "solucomp" THEN 1 END) as total_domains,
                COUNT(CASE WHEN `key` != "solucomp" AND created_at BETWEEN ? AND ? THEN 1 END) as current_domains,
                COUNT(CASE WHEN `key` != "solucomp" AND created_at BETWEEN ? AND ? THEN 1 END) as previous_domains
            ', [
                $startOfCurrentMonth, $now,
                $startOfPreviousMonth, $endOfPreviousMonth
            ])
            ->first();

            // Consolidate session queries into one
            $sessionStats = DB::table('sessions')
                ->selectRaw('
                    COUNT(CASE WHEN last_activity >= ? THEN 1 END) as total_sessions_24h,
                    COUNT(CASE WHEN last_activity BETWEEN ? AND ? THEN 1 END) as current_sessions,
                    COUNT(CASE WHEN last_activity BETWEEN ? AND ? THEN 1 END) as previous_sessions
                ', [
                    $startOfCurrentDay->timestamp,
                    $startOfCurrentDay->timestamp, $now->timestamp,
                    $startOfPreviousDay->timestamp, $startOfCurrentDay->timestamp
                ])
                ->first();

            // --- Assemble the final array (logic remains identical) ---
            $totalUsers = $stats->total_users;
            $previousUsers = $totalUsers - $stats->current_month_users;

            return [
                'ccms' => $summaries['ccms'],
                'job_finder' => $summaries['job_finder'],
                'samsung' => $summaries['samsung'],
                'total_users' => $totalUsers,
                'total_domains' => $domainStats->total_domains,
                'total_sessions_24h' => $sessionStats->total_sessions_24h,

                'user_growth' => [
                    'previous_month' => $previousUsers,
                    'current_month' => $totalUsers,
                    'growth_rate' => $this->calculateGrowthRate($previousUsers, $totalUsers),
                ],

                'admin_growth' => [
                    'previous_month' => $stats->previous_admins,
                    'current_month' => $stats->current_admins,
                    'growth_rate' => $this->calculateGrowthRate($stats->previous_admins, $stats->current_admins),
                ],

                'domain_growth' => [
                    'previous_month' => $domainStats->previous_domains,
                    'current_month' => $domainStats->current_domains,
                    'growth_rate' => $this->calculateGrowthRate($domainStats->previous_domains, $domainStats->current_domains),
                ],

                'session_growth' => [
                    'previous_month' => $sessionStats->previous_sessions,
                    'current_month' => $sessionStats->current_sessions,
                    'growth_rate' => $this->calculateGrowthRate($sessionStats->previous_sessions, $sessionStats->current_sessions),
                ],
            ];
        });
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

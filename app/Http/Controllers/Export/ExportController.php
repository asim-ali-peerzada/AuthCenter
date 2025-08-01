<?php

namespace App\Http\Controllers\Export;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Domain;
use App\Models\UserActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ExportController extends Controller
{
    // Export summary with activities
    public function exportSummaryWithActivities(): JsonResponse
    {
        $now = Carbon::now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endOfPreviousMonth = $startOfCurrentMonth->copy()->subSecond();

        // Dashboard summary data
        $totalUsers = User::count();
        $totalDomains = Domain::count();
        $totalSessions24h = DB::table('sessions')
            ->where('last_activity', '>=', now()->subDay()->timestamp)
            ->count();

        $previousUsers = User::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $currentUsers = User::whereBetween('created_at', [$startOfCurrentMonth, $now])->count();

        $previousDomains = Domain::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])->count();
        $currentDomains = Domain::whereBetween('created_at', [$startOfCurrentMonth, $now])->count();

        $previousSessions = DB::table('sessions')
            ->whereBetween('last_activity', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->count();

        $currentSessions = DB::table('sessions')
            ->whereBetween('last_activity', [$startOfCurrentMonth, $now])
            ->count();

        $growthRate = function ($previous, $current) {
            if ($previous === 0) return null;
            return round((($current - $previous) / $previous) * 100, 2);
        };

        // User activities
        $activities = UserActivity::with('user')
            ->latest()
            ->get()
            ->map(function ($activity) {
                return [
                    'user_name' => $activity->user ? ($activity->user->first_name . ' ' . $activity->user->last_name) : null,
                    'event'      => $activity->event_type,
                    'ip_address' => $activity->ip_address,
                    'user_agent' => $activity->user_agent,
                    'timestamp'  => $activity->created_at->toDateTimeString(),
                ];
            });

        $filename = 'summary_activities_' . now()->format('Ymd_His') . '.xlsx';
        $filePath = 'exports/' . $filename;

        Excel::store(new class(
            $totalUsers,
            $totalDomains,
            $totalSessions24h,
            $previousUsers,
            $currentUsers,
            $previousDomains,
            $currentDomains,
            $previousSessions,
            $currentSessions,
            $growthRate,
            $activities
        ) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings, \Maatwebsite\Excel\Concerns\WithStyles, \Maatwebsite\Excel\Concerns\WithTitle {

            private $rows;

            public function __construct(...$args)
            {
                [
                    $totalUsers,
                    $totalDomains,
                    $totalSessions24h,
                    $prevUsers,
                    $currUsers,
                    $prevDomains,
                    $currDomains,
                    $prevSessions,
                    $currSessions,
                    $growth,
                    $activities
                ] = $args;

                $this->rows = [
                    ['--- Dashboard Summary ---'],
                    ['Total Users', $totalUsers],
                    ['Total Domains', $totalDomains],
                    ['Total Sessions (Last 24h)', $totalSessions24h],
                    [],
                    ['User Growth (Previous Month)', $prevUsers],
                    ['User Growth (Current Month)', $currUsers],
                    ['User Growth Rate (%)', $growth($prevUsers, $currUsers)],
                    [],
                    ['Domain Growth (Previous Month)', $prevDomains],
                    ['Domain Growth (Current Month)', $currDomains],
                    ['Domain Growth Rate (%)', $growth($prevDomains, $currDomains)],
                    [],
                    ['Session Growth (Previous Month)', $prevSessions],
                    ['Session Growth (Current Month)', $currSessions],
                    ['Session Growth Rate (%)', $growth($prevSessions, $currSessions)],
                    [],
                    ['--- Recent User Activities ---'],
                    [],
                    ['User Name', 'Event', 'IP Address', 'User Agent', 'Timestamp'],
                ];

                foreach ($activities as $act) {
                    $this->rows[] = [
                        $act['user_name'],
                        $act['event'],
                        $act['ip_address'],
                        $act['user_agent'],
                        $act['timestamp'],
                    ];
                }
            }

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return []; // handled manually above
            }

            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet)
            {
                // Bold & coloured section titles
                $titles = [1, 6, 10, 14, 18, 22];
                foreach ($titles as $row) {
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setColor(new Color(Color::COLOR_DARKBLUE));
                    $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEFF6FF');
                }

                // Bold header for activities
                $sheet->getStyle('A20:E20')->getFont()->setBold(true)->setColor(new Color(Color::COLOR_WHITE));
                $sheet->getStyle('A20:E20')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');

                // Auto-size columns
                foreach (range('A', 'E') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            public function title(): string
            {
                return 'Dashboard Report';
            }
        }, $filePath, 'public');

        return response()->json([
            'success'      => true,
            'download_url' => asset('storage/' . $filePath),
        ]);
    }
}

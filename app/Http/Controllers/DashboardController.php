<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $defaultMonth = (int) date('n');
        $defaultYear = (int) date('Y');

        $month = (int) $request->get('month', $defaultMonth);
        $year = (int) $request->get('year', $defaultYear);
        if ($month < 1 || $month > 12) {
            $month = $defaultMonth;
        }
        if ($year < 2020 || $year > 2035) {
            $year = $defaultYear;
        }

        $prevMonth = $month == 1 ? 12 : $month - 1;
        $prevYear = $month == 1 ? $year - 1 : $year;

        // 1. Load Division with period_status subquery (1 query) & bind to user to prevent lazy loading
        $division = null;
        $periodStatus = 'closed';
        if ($divisionId) {
            $division = Division::select('divisions.*')
                ->addSelect([
                    'period_status' => PeriodManagement::select('status')
                        ->whereColumn('period_management.division_id', 'divisions.id')
                        ->where('period_month', $month)
                        ->where('period_year', $year)
                        ->limit(1),
                ])->find($divisionId);

            if ($division) {
                $user->setRelation('division', $division);
                if ($division->period_status !== null) {
                    $periodStatus = $division->period_status;
                } else {
                    $currentMonth = (int) date('n');
                    $currentYear = (int) date('Y');
                    $periodStatus = ($month === $currentMonth && $year === $currentYear) ? 'open' : 'closed';
                }
            }
        } else {
            $periodStatus = 'open';
            $user->setRelation('division', null);
        }

        // 2. Consolidated Evaluations for current and previous month (1 query)
        $evaluations = Evaluation::where(function ($q) use ($month, $year, $prevMonth, $prevYear) {
            $q->where(function ($sub) use ($month, $year) {
                $sub->where('period_month', $month)->where('period_year', $year);
            })->orWhere(function ($sub) use ($prevMonth, $prevYear) {
                $sub->where('period_month', $prevMonth)->where('period_year', $prevYear);
            });
        })
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->get();

        $currentEvaluations = $evaluations->filter(fn ($e) => (int) $e->period_month === $month && (int) $e->period_year === $year)->values();
        $prevEvaluations = $evaluations->filter(fn ($e) => (int) $e->period_month === $prevMonth && (int) $e->period_year === $prevYear);

        $evaluatedEmployeeIds = $currentEvaluations->pluck('employee_id')->filter()->unique()->toArray();
        $totalEvaluated = count($evaluatedEmployeeIds);

        // 3. Consolidated Employees in division: active + any currently evaluated (1 query)
        $employeesQuery = Employee::query();
        if ($divisionId) {
            $employeesQuery->where('division_id', $divisionId)
                ->where(function ($q) use ($evaluatedEmployeeIds) {
                    $q->where('is_active', true);
                    if (! empty($evaluatedEmployeeIds)) {
                        $q->orWhereIn('id', $evaluatedEmployeeIds);
                    }
                });
        } else {
            $employeesQuery->where('is_active', true);
        }
        $divisionEmployees = $employeesQuery->orderBy('name', 'asc')->get();
        $employeesById = $divisionEmployees->keyBy('id');

        $totalEmployees = $divisionEmployees->where('is_active', true)->count();
        $totalPending = max(0, $totalEmployees - $totalEvaluated);

        $progressPercentage = $totalEmployees > 0
            ? round(($totalEvaluated / $totalEmployees) * 100)
            : 0;

        $systemStatus = $progressPercentage >= 100 ? 'Selesai' : 'Dalam Proses';
        if ($periodStatus === 'closed') {
            $systemStatus = 'Periode Ditutup';
        }

        // Bind employee relation in memory to prevent lazy-loading or extra eager queries
        foreach ($currentEvaluations as $eval) {
            $eval->setRelation('employee', $employeesById->get($eval->employee_id));
        }

        $averageScore = $totalEvaluated > 0 ? round($currentEvaluations->avg('total_score'), 1) : 0;

        $predicateCounts = [
            'Sangat Baik' => $currentEvaluations->where('predicate', 'Sangat Baik')->count(),
            'Baik' => $currentEvaluations->where('predicate', 'Baik')->count(),
            'Cukup Baik' => $currentEvaluations->where('predicate', 'Cukup Baik')->count(),
            'Perlu Perhatian' => $currentEvaluations->where('predicate', 'Perlu Perhatian')->count(),
        ];

        // 4. AKHLAK Category Diagnostics (1 grouped query via shared service)
        $evalIds = $currentEvaluations->pluck('id');
        $akhlakStats = EvaluationScoringService::computeAkhlakStats($evalIds);

        $weakestPillar = null;
        $strongestPillar = null;
        if ($totalEvaluated > 0 && ! empty($akhlakStats)) {
            $scores = array_column($akhlakStats, 'score_100', 'name');
            asort($scores);
            $weakestPillar = [
                'name' => array_key_first($scores),
                'score' => reset($scores),
            ];
            $strongestPillar = [
                'name' => array_key_last($scores),
                'score' => end($scores),
            ];
        }

        $topPerformers = $currentEvaluations->sortByDesc('total_score')->take(5)->values();
        $bottomPerformers = $currentEvaluations->sortBy('total_score')->take(5)->values();

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        try {
            $cutoffDate = Carbon::create($year, $month, 25, 23, 59, 59);
        } catch (\Throwable $e) {
            $cutoffDate = Carbon::now()->endOfMonth();
        }
        $now = Carbon::now();
        $isCutoffPassed = $now->greaterThan($cutoffDate);
        $slaDaysRemaining = $isCutoffPassed ? 0 : max(0, (int) $now->diffInDays($cutoffDate, false));

        // MoM Previous Month Delta (Calculated in-memory from query #2)
        $prevDivAvg = $prevEvaluations->isNotEmpty() ? $prevEvaluations->avg('total_score') : null;
        $momDelta = ($averageScore > 0 && $prevDivAvg) ? round($averageScore - $prevDivAvg, 1) : null;
        $prevMonthName = $monthNames[$prevMonth] ?? '-';

        // Pending Employees (Bounded in-memory from query #3)
        $pendingEmployees = $divisionEmployees
            ->where('is_active', true)
            ->whereNotIn('id', $evaluatedEmployeeIds)
            ->sortBy('name')
            ->take(5)
            ->values();

        $recentEvaluated = $currentEvaluations->sortByDesc('updated_at')->take(5)->values();

        return view('user.dashboard', [
            'user' => $user,
            'division' => $division,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthNames[$month] ?? '-',
            'totalEmployees' => $totalEmployees,
            'totalPending' => $totalPending,
            'totalEvaluated' => $totalEvaluated,
            'progressPercentage' => $progressPercentage,
            'periodStatus' => $periodStatus,
            'systemStatus' => $systemStatus,
            'averageScore' => $averageScore,
            'predicateCounts' => $predicateCounts,
            'pendingEmployees' => $pendingEmployees,
            'recentEvaluated' => $recentEvaluated,

            'akhlakStats' => $akhlakStats,
            'weakestPillar' => $weakestPillar,
            'strongestPillar' => $strongestPillar,
            'topPerformers' => $topPerformers,
            'bottomPerformers' => $bottomPerformers,
            'slaCutoffDate' => '25 '.($monthNames[$month] ?? '-').' '.$year.', 23:59 WIB',
            'slaDaysRemaining' => $slaDaysRemaining,
            'isCutoffPassed' => $isCutoffPassed,
            'momDelta' => $momDelta,
            'prevMonthName' => $prevMonthName,
        ]);
    }
}

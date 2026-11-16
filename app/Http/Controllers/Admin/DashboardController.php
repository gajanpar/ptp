<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Models\User;
use App\Services\EvaluationScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
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

        // 1. Consolidated divisions + global metrics in a single query (1 query)
        // Query all divisions (both active and inactive) so relations can be resolved in memory without N+1 lazy loads.
        $allDivisions = Division::select('divisions.*')
            ->addSelect([
                'total_penilai' => User::selectRaw('COUNT(id)')->where('role', 'kepala_divisi')->where('is_active', true),
                'pending_requests' => EditRequest::selectRaw('COUNT(id)')->where('status', 'pending'),
                'prev_avg' => Evaluation::selectRaw('AVG(total_score)')->where('period_month', $prevMonth)->where('period_year', $prevYear),
                'period_status' => PeriodManagement::select('status')
                    ->whereColumn('period_management.division_id', 'divisions.id')
                    ->where('period_month', $month)
                    ->where('period_year', $year)
                    ->limit(1),
                'penilai_name' => User::select('name')
                    ->whereColumn('users.division_id', 'divisions.id')
                    ->where('role', 'kepala_divisi')
                    ->where('is_active', true)
                    ->limit(1),
            ])
            ->withCount(['employees' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $allDivisionsById = $allDivisions->keyBy('id');
        $divisions = $allDivisions->where('is_active', true)->values();

        $firstDiv = $allDivisions->first();
        if ($firstDiv !== null) {
            $totalPenilai = (int) ($firstDiv->total_penilai ?? 0);
            $pendingRequestsCount = (int) ($firstDiv->pending_requests ?? 0);
            $prevAvg = $firstDiv->prev_avg !== null ? (float) $firstDiv->prev_avg : null;
        } else {
            $fallbackStats = DB::selectOne('
                SELECT 
                    (SELECT COUNT(id) FROM users WHERE role = ? AND is_active = ?) as total_penilai,
                    (SELECT COUNT(id) FROM edit_requests WHERE status = ?) as pending_requests,
                    (SELECT AVG(total_score) FROM evaluations WHERE period_month = ? AND period_year = ?) as prev_avg
            ', ['kepala_divisi', true, 'pending', $prevMonth, $prevYear]);
            $totalPenilai = (int) ($fallbackStats->total_penilai ?? 0);
            $pendingRequestsCount = (int) ($fallbackStats->pending_requests ?? 0);
            $prevAvg = $fallbackStats->prev_avg !== null ? (float) $fallbackStats->prev_avg : null;
        }

        // Hydrate users relation in memory for each division to prevent lazy-load when Blade accesses $div->users
        foreach ($divisions as $div) {
            if ($div->penilai_name) {
                $div->setRelation('users', collect([
                    new User(['name' => $div->penilai_name, 'role' => 'kepala_divisi', 'is_active' => true]),
                ]));
            } else {
                $div->setRelation('users', collect());
            }
        }

        $totalDivisions = $divisions->count();
        $totalEmployees = (int) $divisions->sum('employees_count');

        // 2. Evaluations for this period with eager-loaded employee
        $evaluations = Evaluation::with(['employee'])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->get();

        // Link pre-loaded divisions in memory (both active and inactive) to avoid N+1 queries in Blade
        foreach ($evaluations as $eval) {
            if ($eval->division_id && $allDivisionsById->has($eval->division_id)) {
                $eval->setRelation('division', $allDivisionsById->get($eval->division_id));
            } else {
                $eval->setRelation('division', null);
            }
        }

        $totalEvaluated = $evaluations->count();
        $totalPending = max(0, $totalEmployees - $totalEvaluated);
        $branchProgress = $totalEmployees > 0
            ? round(($totalEvaluated / $totalEmployees) * 100)
            : 0;

        // Metrik Statistik Lanjutan (Information Richness)
        $averageScore = $totalEvaluated > 0 ? round($evaluations->avg('total_score'), 1) : 0;
        $highestScore = $totalEvaluated > 0 ? round($evaluations->max('total_score'), 1) : 0;
        $lowestScore = $totalEvaluated > 0 ? round($evaluations->min('total_score'), 1) : 0;

        // Predikat Rata-Rata Cabang
        $averagePredicate = 'Belum Ada Data';
        if ($averageScore >= 85.0) {
            $averagePredicate = 'Sangat Baik';
        } elseif ($averageScore >= 70.0) {
            $averagePredicate = 'Baik';
        } elseif ($averageScore >= 55.0) {
            $averagePredicate = 'Cukup Baik';
        } elseif ($averageScore > 0) {
            $averagePredicate = 'Perlu Perhatian';
        }

        // Distribusi Predikat & Persentase
        $predicateCounts = [
            'Sangat Baik' => $evaluations->where('predicate', 'Sangat Baik')->count(),
            'Baik' => $evaluations->where('predicate', 'Baik')->count(),
            'Cukup Baik' => $evaluations->where('predicate', 'Cukup Baik')->count(),
            'Perlu Perhatian' => $evaluations->where('predicate', 'Perlu Perhatian')->count(),
        ];

        $predicatePercentages = [];
        foreach ($predicateCounts as $key => $cnt) {
            $predicatePercentages[$key] = $totalEvaluated > 0 ? round(($cnt / $totalEvaluated) * 100, 1) : 0;
        }

        // 4. Diagnostik 6 Nilai Inti AKHLAK Cabang (Single consolidated SQL aggregate)
        $akhlakStats = EvaluationScoringService::computeAkhlakStats($evaluations->pluck('id'));

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

        // 2. Top 5 & Bottom 5 Performers Cabang
        $topPerformers = $evaluations->sortByDesc('total_score')->take(5)->values();
        $bottomPerformers = $evaluations->sortBy('total_score')->take(5)->values();

        // 3. Countdown SLA Gembok (Cutoff tgl 25)
        $monthNames = EvaluationScoringService::MONTH_NAMES;

        try {
            $cutoffDate = Carbon::create($year, $month, 25, 23, 59, 59);
        } catch (\Throwable $e) {
            $cutoffDate = Carbon::now()->endOfMonth();
        }
        $now = Carbon::now();
        $isCutoffPassed = $now->greaterThan($cutoffDate);
        $slaDaysRemaining = $isCutoffPassed ? 0 : max(0, (int) $now->diffInDays($cutoffDate, false));

        // 4. Rekomendasi Kontrak PDS (PT Pelindo Daya Sejahtera)
        $contractRecommendations = [
            'perpanjang' => $evaluations->where('total_score', '>=', 80.0)->count(),
            'bersyarat' => $evaluations->whereBetween('total_score', [70.0, 79.99])->count(),
            'evaluasi' => $evaluations->where('total_score', '<', 70.0)->count(),
        ];

        // 5. Tren Nilai Antar-Bulan (Month-over-Month MoM)
        $momDelta = ($averageScore > 0 && $prevAvg) ? round($averageScore - $prevAvg, 1) : null;
        $prevMonthName = $monthNames[$prevMonth] ?? '-';

        // Ringkasan per Divisi (In-Memory Collection Reuse)
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        $defaultStatus = ($month === $currentMonth && $year === $currentYear) ? 'open' : 'closed';

        $evaluationsByDivision = $evaluations->groupBy('division_id');
        $divisionStats = [];
        $lockedDivisionsCount = 0;

        foreach ($divisions as $div) {
            $divEvals = $evaluationsByDivision->get($div->id, collect());
            $evalCount = $divEvals->count();
            $status = $div->period_status ?? $defaultStatus;
            if ($status === 'closed') {
                $lockedDivisionsCount++;
            }

            $divAvgScore = $evalCount > 0 ? round($divEvals->avg('total_score'), 1) : 0;

            $divisionStats[] = [
                'division' => $div,
                'total_employees' => $div->employees_count,
                'evaluated_count' => $evalCount,
                'pending_count' => max(0, $div->employees_count - $evalCount),
                'progress' => $div->employees_count > 0 ? round(($evalCount / $div->employees_count) * 100) : 0,
                'average_score' => $divAvgScore,
                'status' => $status,
            ];
        }

        // Permohonan Akses Terbaru (Consolidated 1 query with leftJoin instead of 4 separate queries)
        $recentRequests = $pendingRequestsCount > 0
            ? EditRequest::query()
                ->leftJoin('users as requesters', 'edit_requests.requested_by', '=', 'requesters.id')
                ->leftJoin('divisions', 'edit_requests.division_id', '=', 'divisions.id')
                ->leftJoin('employees', 'edit_requests.employee_id', '=', 'employees.id')
                ->where('edit_requests.status', 'pending')
                ->orderBy('edit_requests.created_at', 'desc')
                ->take(5)
                ->select([
                    'edit_requests.*',
                    'requesters.name as requester_name',
                    'divisions.name as division_name',
                    'employees.name as employee_name',
                ])
                ->get()
            : collect();

        foreach ($recentRequests as $req) {
            $req->setRelation('requester', $req->requester_name ? new User(['name' => $req->requester_name]) : null);
            $req->setRelation('division', $req->division_name ? new Division(['name' => $req->division_name]) : null);
            $req->setRelation('employee', $req->employee_name ? new Employee(['name' => $req->employee_name]) : null);
        }

        return view('admin.dashboard', [
            'user' => Auth::user(),
            'month' => $month,
            'year' => $year,
            'monthName' => $monthNames[$month] ?? '-',
            'totalEmployees' => $totalEmployees,
            'totalDivisions' => $totalDivisions,
            'totalPenilai' => $totalPenilai,
            'pendingRequestsCount' => $pendingRequestsCount,
            'totalEvaluated' => $totalEvaluated,
            'totalPending' => $totalPending,
            'branchProgress' => $branchProgress,
            'averageScore' => $averageScore,
            'highestScore' => $highestScore,
            'lowestScore' => $lowestScore,
            'averagePredicate' => $averagePredicate,
            'predicateCounts' => $predicateCounts,
            'predicatePercentages' => $predicatePercentages,
            'divisionStats' => $divisionStats,
            'lockedDivisionsCount' => $lockedDivisionsCount,
            'recentRequests' => $recentRequests,

            // 5 Matriks Data Strategis
            'akhlakStats' => $akhlakStats,
            'weakestPillar' => $weakestPillar,
            'strongestPillar' => $strongestPillar,
            'topPerformers' => $topPerformers,
            'bottomPerformers' => $bottomPerformers,
            'slaCutoffDate' => '25 '.($monthNames[$month] ?? '-').' '.$year.', 23:59 WIB',
            'slaDaysRemaining' => $slaDaysRemaining,
            'isCutoffPassed' => $isCutoffPassed,
            'contractRecommendations' => $contractRecommendations,
            'momDelta' => $momDelta,
            'prevMonthName' => $prevMonthName,
        ]);
    }
}

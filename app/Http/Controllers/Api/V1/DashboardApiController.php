<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Services\EvaluationScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardApiController extends ApiController
{
    /**
     * Retrieve executive & evaluator live interactive metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $month = (int) $request->input('month', date('n'));
        $year = (int) $request->input('year', date('Y'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }

        // Division scoping (non-admin locked to own division)
        $rawDivId = $request->input('division_id');
        $divisionId = $isAdmin
            ? ($request->filled('division_id') && $rawDivId !== 'all' && is_numeric($rawDivId) ? (int) $rawDivId : null)
            : $user->division_id;

        // Employees query
        $employeesQuery = Employee::where('is_active', true);
        if ($divisionId) {
            $employeesQuery->where('division_id', $divisionId);
        }
        $totalEmployees = $employeesQuery->count();

        // Evaluations query
        $evalQuery = Evaluation::with(['employee.division'])
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($divisionId) {
            $evalQuery->where('division_id', $divisionId);
        }
        $evaluations = $evalQuery->get();
        $totalEvaluated = $evaluations->count();
        $pendingEvaluations = max(0, $totalEmployees - $totalEvaluated);
        $submissionRate = $totalEmployees > 0 ? round(($totalEvaluated / $totalEmployees) * 100, 1) : 0.0;

        // Score summary
        $avgScore = $totalEvaluated > 0 ? round($evaluations->avg('total_score'), 2) : 0.0;
        $maxScore = $totalEvaluated > 0 ? round($evaluations->max('total_score'), 2) : 0.0;
        $minScore = $totalEvaluated > 0 ? round($evaluations->min('total_score'), 2) : 0.0;

        // 1. Diagnostik 6 Nilai Inti AKHLAK (single grouped SQL aggregate)
        $rawStats = EvaluationScoringService::computeAkhlakStats($evaluations->pluck('id'));
        $akhlakStats = [];
        foreach ($rawStats as $pillarName => $stat) {
            $akhlakStats[$pillarName] = [
                'name' => $stat['name'],
                'raw_score' => $stat['raw_avg'],
                'normalized_score' => $stat['score_100'],
            ];
        }

        $weakestPillar = null;
        $strongestPillar = null;
        if ($totalEvaluated > 0 && ! empty($akhlakStats)) {
            $scores = array_column($akhlakStats, 'normalized_score', 'name');
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

        // 2. Top & Bottom Performers (Top 5 for admin, Top 3 for evaluator)
        $talentLimit = $isAdmin ? 5 : 3;
        $formatPerformer = static function ($ev): array {
            return [
                'employee_id' => $ev->employee_id,
                'nik' => $ev->employee?->nik ?: '-',
                'name' => $ev->employee?->name,
                'position' => $ev->employee?->position ?: '-',
                'division' => $ev->employee?->division?->name ?: '-',
                'vendor_name' => $ev->employee?->vendor_name ?: 'PT PDS',
                'total_score' => (float) $ev->total_score,
                'predicate' => $ev->predicate,
            ];
        };

        $topPerformers = $evaluations->sortByDesc('total_score')->take($talentLimit)->values()->map($formatPerformer);
        $bottomPerformers = $evaluations->sortBy('total_score')->take($talentLimit)->values()->map($formatPerformer);

        // 3. Rekomendasi Kontrak PDS (Khusus Admin / SDM)
        $contractRecommendations = null;
        if ($isAdmin) {
            $contractRecommendations = [
                'perpanjang' => [
                    'count' => $evaluations->where('total_score', '>=', 80.0)->count(),
                    'label' => 'Rekomendasi Perpanjangan Penuh (Skor >= 80)',
                ],
                'bersyarat' => [
                    'count' => $evaluations->whereBetween('total_score', [70.0, 79.99])->count(),
                    'label' => 'Perpanjang Bersyarat / Pembinaan (Skor 70 - 79.9)',
                ],
                'evaluasi' => [
                    'count' => $evaluations->where('total_score', '<', 70.0)->count(),
                    'label' => 'Evaluasi Keras / Rotasi Vendor (Skor < 70)',
                ],
            ];
        }

        // 4. Tren MoM (Month-over-Month)
        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $prevQuery = Evaluation::where('period_month', $prevMonth)
            ->where('period_year', $prevYear);
        if ($divisionId) {
            $prevQuery->where('division_id', $divisionId);
        }
        $prevAvg = $prevQuery->avg('total_score');

        $momTrend = null;
        if ($prevAvg !== null && $prevAvg > 0) {
            $delta = round($avgScore - $prevAvg, 2);
            $momTrend = [
                'current_avg' => $avgScore,
                'previous_avg' => round((float) $prevAvg, 2),
                'delta' => $delta,
                'direction' => $delta >= 0 ? 'up' : 'down',
                'formatted' => ($delta >= 0 ? '+'.$delta : (string) $delta).' poin vs bulan lalu',
            ];
        } else {
            $momTrend = [
                'current_avg' => $avgScore,
                'previous_avg' => null,
                'delta' => 0.0,
                'direction' => 'neutral',
                'formatted' => 'Data bulan lalu belum tersedia',
            ];
        }

        return $this->jsonSuccess([
            'filter' => [
                'month' => $month,
                'year' => $year,
                'division_id' => $divisionId,
                'scope' => $divisionId ? 'divisional' : 'cabang_wide',
            ],
            'summary' => [
                'total_employees' => $totalEmployees,
                'evaluated_count' => $totalEvaluated,
                'pending_count' => $pendingEvaluations,
                'submission_percentage' => $submissionRate,
                'average_score' => $avgScore,
                'highest_score' => $maxScore,
                'lowest_score' => $minScore,
            ],
            'akhlak_diagnostic' => [
                'pillars' => $akhlakStats,
                'weakest_pillar' => $weakestPillar,
                'strongest_pillar' => $strongestPillar,
            ],
            'talent_matrix' => [
                'top_performers' => $topPerformers,
                'bottom_performers' => $bottomPerformers,
            ],
            'contract_recommendations' => $contractRecommendations,
            'mom_trend' => $momTrend,
        ], 'Data metrik analitik dashboard berhasil diambil.');
    }
}

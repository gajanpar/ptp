<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodApiController extends ApiController
{
    /**
     * Retrieve real-time SLA cutoff countdown and lock enforcement status.
     */
    public function slaStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $month = (int) $request->input('month', date('n'));
        $year = (int) $request->input('year', date('Y'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }

        // Target division
        $divisionId = $user->role === 'admin'
            ? ($request->filled('division_id') ? (int) $request->input('division_id') : null)
            : $user->division_id;

        // Cutoff time: 25th of evaluation month at 23:59:59 Asia/Jakarta
        $now = Carbon::now('Asia/Jakarta');
        try {
            $cutoffDate = Carbon::create($year, $month, 25, 23, 59, 59, 'Asia/Jakarta');
        } catch (\Throwable) {
            $cutoffDate = Carbon::create((int) date('Y'), (int) date('n'), 25, 23, 59, 59, 'Asia/Jakarta');
        }

        $isPastCutoff = $now->greaterThan($cutoffDate);
        $remainingSeconds = $isPastCutoff ? 0 : $now->diffInSeconds($cutoffDate, false);

        $days = (int) floor($remainingSeconds / 86400);
        $hours = (int) floor(($remainingSeconds % 86400) / 3600);
        $minutes = (int) floor(($remainingSeconds % 3600) / 60);
        $seconds = (int) ($remainingSeconds % 60);

        // Check lock status in database
        $isPeriodLocked = false;
        if ($divisionId) {
            $isPeriodLocked = PeriodManagement::getStatus($divisionId, $month, $year) === 'closed';
        } else {
            // Cabang level: locked if all divisions are closed or past cutoff
            $isPeriodLocked = $isPastCutoff;
        }

        // Calculate progress stats for this period and division
        $tadQuery = Employee::where('is_active', true);
        if ($divisionId) {
            $tadQuery->where('division_id', $divisionId);
        }
        $totalTad = $tadQuery->count();

        $evalQuery = Evaluation::where('period_month', $month)
            ->where('period_year', $year);
        if ($divisionId) {
            $evalQuery->where('division_id', $divisionId);
        }
        $evaluatedTad = $evalQuery->distinct('employee_id')->count('employee_id');
        $pendingTad = max(0, $totalTad - $evaluatedTad);
        $completionRate = $totalTad > 0 ? round(($evaluatedTad / $totalTad) * 100, 1) : 0.0;

        $monthLabel = EvaluationScoringService::getMonthName($month);

        return $this->jsonSuccess([
            'period' => [
                'month' => $month,
                'year' => $year,
                'month_name' => $monthLabel,
                'label' => $monthLabel.' '.$year,
            ],
            'server_time' => $now->toIso8601String(),
            'cutoff' => [
                'date' => $cutoffDate->toIso8601String(),
                'formatted' => $cutoffDate->format('d F Y, H:i:s').' WIB',
                'day_of_month' => 25,
                'is_past_cutoff' => $isPastCutoff,
            ],
            'countdown' => [
                'total_seconds' => $remainingSeconds,
                'days' => $days,
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => $seconds,
                'human' => $isPastCutoff
                    ? 'Batas waktu pengisian bulan ini telah berakhir (Gembok Aktif)'
                    : "{$days} Hari {$hours} Jam {$minutes} Menit",
            ],
            'enforcement' => [
                'is_locked' => $isPeriodLocked,
                'can_input' => ! $isPeriodLocked,
                'status_badge' => $isPeriodLocked ? 'TERKUNCI' : 'DIBUKA',
            ],
            'progress' => [
                'total_tad' => $totalTad,
                'evaluated' => $evaluatedTad,
                'pending' => $pendingTad,
                'percentage' => $completionRate,
            ],
        ], 'Status SLA gembok penilaian berhasil dimuat.');
    }
}

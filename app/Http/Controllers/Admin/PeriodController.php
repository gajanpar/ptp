<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeriodController extends Controller
{
    /**
     * Tampilkan matriks manajemen periode per divisi dan per bulan.
     */
    public function index(Request $request)
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $year = (int) $request->get('year', $currentYear);
        if ($year < 2020 || $year > 2035) {
            $year = $currentYear;
        }

        $divisions = Division::where('is_active', true)->orderBy('id')->get();

        // Ambil semua record period_management untuk tahun yang dipilih
        $periodRecords = PeriodManagement::where('period_year', $year)
            ->get()
            ->groupBy('division_id');

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        // Buat matriks status [division_id][month] => 'open' | 'closed'
        $matrix = [];
        $totalOpen = 0;
        $totalClosed = 0;

        foreach ($divisions as $div) {
            $divRecords = $periodRecords->get($div->id, collect())->keyBy('period_month');

            for ($m = 1; $m <= 12; $m++) {
                if (isset($divRecords[$m])) {
                    $status = $divRecords[$m]->status;
                    $recordId = $divRecords[$m]->id;
                    $updatedAt = $divRecords[$m]->updated_at;
                } else {
                    $status = ($m === $currentMonth && $year === $currentYear) ? 'open' : 'closed';
                    $recordId = null;
                    $updatedAt = null;
                }

                $matrix[$div->id][$m] = [
                    'status' => $status,
                    'record_id' => $recordId,
                    'updated_at' => $updatedAt,
                ];

                if ($status === 'open') {
                    $totalOpen++;
                } else {
                    $totalClosed++;
                }
            }
        }

        $years = range($currentYear - 2, $currentYear + 2);

        return view('admin.periods.index', [
            'year' => $year,
            'years' => $years,
            'currentMonth' => $currentMonth,
            'currentYear' => $currentYear,
            'divisions' => $divisions,
            'monthNames' => $monthNames,
            'matrix' => $matrix,
            'totalOpen' => $totalOpen,
            'totalClosed' => $totalClosed,
        ]);
    }

    /**
     * Toggle status periode satu divisi untuk bulan dan tahun tertentu.
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'division_id' => 'required|exists:divisions,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2035',
            'status' => 'nullable|in:open,closed',
        ]);

        $divisionId = (int) $request->division_id;
        $month = (int) $request->month;
        $year = (int) $request->year;

        $currentStatus = PeriodManagement::getStatus($divisionId, $month, $year);
        $newStatus = $request->filled('status')
            ? $request->status
            : ($currentStatus === 'open' ? 'closed' : 'open');

        PeriodManagement::updateOrCreate(
            [
                'division_id' => $divisionId,
                'period_month' => $month,
                'period_year' => $year,
            ],
            [
                'status' => $newStatus,
                'updated_by' => Auth::id(),
            ]
        );

        $division = Division::find($divisionId);
        $monthNames = EvaluationScoringService::MONTH_NAMES;
        $monthName = $monthNames[$month] ?? "Bulan $month";
        $statusLabel = $newStatus === 'open' ? 'DIBUKA' : 'DITUTUP';

        ActivityLog::record(
            'period.toggle',
            "Mengubah status periode {$monthName} {$year} untuk {$division->name} menjadi {$statusLabel}",
            'PeriodManagement',
            $divisionId,
            [
                'division_id' => $divisionId,
                'division_name' => $division->name,
                'month' => $month,
                'year' => $year,
                'old_status' => $currentStatus,
                'new_status' => $newStatus,
            ]
        );

        return back()->with('success', "Akses periode {$monthName} {$year} untuk {$division->name} berhasil {$statusLabel}.");
    }

    /**
     * Buka atau tutup serentak semua divisi untuk satu bulan terpilih.
     */
    public function bulkToggle(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2035',
            'action' => 'required|in:open_all,close_all',
        ]);

        $month = (int) $request->month;
        $year = (int) $request->year;
        $status = ($request->action === 'open_all') ? 'open' : 'closed';

        $divisions = Division::where('is_active', true)->get();

        foreach ($divisions as $div) {
            PeriodManagement::updateOrCreate(
                [
                    'division_id' => $div->id,
                    'period_month' => $month,
                    'period_year' => $year,
                ],
                [
                    'status' => $status,
                    'updated_by' => Auth::id(),
                ]
            );
        }

        $monthNames = EvaluationScoringService::MONTH_NAMES;
        $monthName = $monthNames[$month] ?? "Bulan $month";
        $statusLabel = ($status === 'open') ? 'DIBUKA SERENTAK' : 'DITUTUP SERENTAK';

        ActivityLog::record(
            'period.bulk_toggle',
            "Mengubah serentak seluruh divisi untuk periode {$monthName} {$year} menjadi {$statusLabel}",
            'PeriodManagement',
            null,
            [
                'month' => $month,
                'year' => $year,
                'status' => $status,
                'divisions_count' => $divisions->count(),
            ]
        );

        return back()->with('success', "Seluruh divisi cabang untuk periode {$monthName} {$year} berhasil {$statusLabel}.");
    }
}

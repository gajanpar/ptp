<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Services\EvaluationScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $month = (int) $request->get('month', 4); // default April 2026 yang sudah ada 3 evaluasi di seeder
        $year = (int) $request->get('year', 2026);

        $evaluations = Evaluation::with(['employee', 'evaluator'])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->when($divisionId, function ($query, $divId) {
                return $query->where('division_id', $divId);
            })
            ->orderBy('total_score', 'desc')
            ->get();

        return view('user.laporan.index', [
            'month' => $month,
            'year' => $year,
            'monthNames' => EvaluationScoringService::MONTH_NAMES,
            'evaluations' => $evaluations,
            'totalEvaluations' => $evaluations->count(),
        ]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $evaluation = Evaluation::with(['employee', 'evaluator', 'details.subcategory.category'])
            ->findOrFail($id);

        if ($divisionId && (int) $evaluation->division_id !== (int) $divisionId) {
            abort(403, 'Anda tidak berhak melihat data divisi lain.');
        }

        return view('user.laporan.detail', [
            'evaluation' => $evaluation,
            'groupedDetails' => EvaluationScoringService::groupDetailsByCategory($evaluation->details),
        ]);
    }

    public function print(Request $request, $id)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;
        $action = in_array($request->get('action'), ['print', 'save_pdf'], true)
            ? $request->get('action')
            : 'print';

        $evaluation = Evaluation::with(['employee', 'evaluator', 'details.subcategory.category'])
            ->findOrFail($id);

        if ($divisionId && (int) $evaluation->division_id !== (int) $divisionId) {
            abort(403);
        }

        return view('user.laporan.print', [
            'evaluation' => $evaluation,
            'groupedDetails' => EvaluationScoringService::groupDetailsByCategory($evaluation->details),
            'action' => $action,
        ]);
    }
}

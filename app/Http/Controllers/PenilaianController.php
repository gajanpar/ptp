<?php

namespace App\Http\Controllers;

use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationDetail;
use App\Models\EvaluationSubcategory;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PenilaianController extends Controller
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
        $activeTab = $request->get('tab', 'pending'); // 'pending' = Belum Dinilai, 'evaluated' = Sudah Dinilai

        // Ambil status periode
        $periodStatus = $divisionId
            ? PeriodManagement::getStatus($divisionId, $month, $year)
            : 'open';
        $isPeriodOpen = ($periodStatus === 'open');

        // Cek apakah ada pengajuan division_access yang disetujui
        $hasApprovedDivisionAccess = false;
        if (! $isPeriodOpen && $divisionId) {
            $hasApprovedDivisionAccess = EditRequest::where('division_id', $divisionId)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->where('request_type', 'division_access')
                ->where('status', 'approved')
                ->exists();
        }

        // Ambil semua karyawan aktif di divisi penilai
        $employees = Employee::where('is_active', true)
            ->when($divisionId, function ($query, $divId) {
                return $query->where('division_id', $divId);
            })
            ->orderBy('name')
            ->get();

        // Ambil evaluasi yang ada pada periode ini
        $evaluations = Evaluation::with('employee')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->when($divisionId, function ($query, $divId) {
                return $query->where('division_id', $divId);
            })
            ->get()
            ->keyBy('employee_id');

        // Cek pengajuan edit individual per karyawan
        $individualRequests = EditRequest::where('period_month', $month)
            ->where('period_year', $year)
            ->where('request_type', 'individual_edit')
            ->when($divisionId, function ($query, $divId) {
                return $query->where('division_id', $divId);
            })
            ->get()
            ->keyBy('employee_id');

        // Pisahkan daftar Belum Dinilai dan Sudah Dinilai
        $pendingEmployees = [];
        $evaluatedEmployees = [];

        foreach ($employees as $emp) {
            if (isset($evaluations[$emp->id])) {
                $eval = $evaluations[$emp->id];
                $req = $individualRequests[$emp->id] ?? null;

                $evaluatedEmployees[] = [
                    'employee' => $emp,
                    'evaluation' => $eval,
                    'can_edit' => $isPeriodOpen || $hasApprovedDivisionAccess || ($req && $req->status === 'approved'),
                    'request' => $req,
                ];
            } else {
                $pendingEmployees[] = [
                    'employee' => $emp,
                    'can_evaluate' => $isPeriodOpen || $hasApprovedDivisionAccess,
                ];
            }
        }

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        return view('user.penilaian.index', [
            'month' => $month,
            'year' => $year,
            'monthNames' => $monthNames,
            'periodStatus' => $periodStatus,
            'isPeriodOpen' => $isPeriodOpen,
            'hasApprovedDivisionAccess' => $hasApprovedDivisionAccess,
            'pendingEmployees' => $pendingEmployees,
            'evaluatedEmployees' => $evaluatedEmployees,
            'totalPending' => count($pendingEmployees),
            'totalEvaluated' => count($evaluatedEmployees),
            'activeTab' => $activeTab,
        ]);
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $employeeId = (int) $request->get('employee_id');
        $month = (int) $request->get('month', 5);
        $year = (int) $request->get('year', 2026);

        $employee = Employee::where('id', $employeeId)
            ->when($divisionId, function ($q, $divId) {
                return $q->where('division_id', $divId);
            })
            ->firstOrFail();

        // Cek apakah sudah pernah dinilai
        $existing = Evaluation::where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->first();

        if ($existing) {
            return redirect()->route('penilaian.edit', [
                'id' => $existing->id,
                'month' => $month,
                'year' => $year,
            ]);
        }

        // Cek hak akses periode
        $isPeriodOpen = PeriodManagement::isOpen((int) $employee->division_id, $month, $year);
        $hasApprovedAccess = EditRequest::where('division_id', $employee->division_id)
            ->where('request_type', 'division_access')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'approved')
            ->exists();

        if (! $isPeriodOpen && ! $hasApprovedAccess) {
            return redirect()->route('penilaian.index', ['month' => $month, 'year' => $year])
                ->with('error', 'Akses penilaian periode ini sedang terkunci. Silakan ajukan pembukaan akses.');
        }

        $categories = EvaluationCategory::with('subcategories')->orderBy('order_index')->get();
        $ratingOptions = EvaluationScoringService::getRatingOptions();

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        return view('user.penilaian.form', [
            'employee' => $employee,
            'month' => $month,
            'year' => $year,
            'periodLabel' => ($monthNames[$month] ?? '-').' '.$year,
            'categories' => $categories,
            'ratingOptions' => $ratingOptions,
            'isEdit' => false,
            'existingData' => [],
            'evaluation' => null,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $evaluation = Evaluation::with(['employee', 'details'])->findOrFail($id);

        if ($divisionId && (int) $evaluation->division_id !== (int) $divisionId) {
            abort(403, 'Anda tidak berhak mengakses data penilaian divisi lain.');
        }

        $evalDivisionId = (int) ($evaluation->employee->division_id ?? $evaluation->division_id);
        $month = (int) $evaluation->period_month;
        $year = (int) $evaluation->period_year;

        // Cek izin edit jika closed
        $isPeriodOpen = PeriodManagement::isOpen($evalDivisionId, $month, $year);
        $hasApprovedIndividual = EditRequest::where('employee_id', $evaluation->employee_id)
            ->where('request_type', 'individual_edit')
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'approved')
            ->exists();
        $hasApprovedDivision = EditRequest::where('division_id', $evalDivisionId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('request_type', 'division_access')
            ->where('status', 'approved')
            ->exists();

        if (! $isPeriodOpen && ! $hasApprovedIndividual && ! $hasApprovedDivision) {
            return redirect()->route('penilaian.index', ['month' => $month, 'year' => $year, 'tab' => 'evaluated'])
                ->with('error', 'Penilaian ini terkunci. Anda perlu mengajukan edit dan menunggu persetujuan admin.');
        }

        $categories = EvaluationCategory::with('subcategories')->orderBy('order_index')->get();
        $ratingOptions = EvaluationScoringService::getRatingOptions();

        $existingData = [];
        foreach ($evaluation->details as $det) {
            $existingData[$det->subcategory_id] = [
                'rating' => $det->rating,
                'score' => $det->score,
                'reason' => $det->reason,
            ];
        }

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        return view('user.penilaian.form', [
            'employee' => $evaluation->employee,
            'month' => $month,
            'year' => $year,
            'periodLabel' => ($monthNames[$month] ?? '-').' '.$year,
            'categories' => $categories,
            'ratingOptions' => $ratingOptions,
            'isEdit' => true,
            'existingData' => $existingData,
            'evaluation' => $evaluation,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2035',
            'ratings' => 'required|array',
            'ratings.*' => 'required|integer|between:1,6',
            'reasons' => 'nullable|array',
        ]);

        $employeeId = (int) $request->employee_id;
        $month = (int) $request->month;
        $year = (int) $request->year;

        $employee = Employee::findOrFail($employeeId);

        // Security Hardening: Validasi Hak Akses Divisi (Anti-IDOR / Anti-BOLA)
        if ($divisionId && (int) $employee->division_id !== (int) $divisionId) {
            abort(403, 'Akses ditolak. Anda tidak memiliki otoritas untuk menilai karyawan dari unit divisi lain.');
        }

        // Security Hardening: Validasi Status Periode & Otorisasi Pengajuan
        $isPeriodOpen = PeriodManagement::isOpen($employee->division_id, $month, $year);
        $hasApprovedIndividual = EditRequest::where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'approved')
            ->exists();
        $hasApprovedDivision = EditRequest::where('division_id', $employee->division_id)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('request_type', 'division_access')
            ->where('status', 'approved')
            ->exists();

        if (! $isPeriodOpen && ! $hasApprovedIndividual && ! $hasApprovedDivision) {
            return back()->withInput()->with('error', 'Akses penilaian untuk periode ini sedang terkunci. Pengajuan pembukaan akses atau izin edit yang disetujui diperlukan.');
        }

        // Pastikan semua 17 subkategori valid & dinilai
        $validSubcategoryIds = EvaluationSubcategory::pluck('id')->all();
        $totalSubcategories = count($validSubcategoryIds);
        $ratings = $request->ratings;
        if (count($ratings) !== $totalSubcategories || array_diff(array_keys($ratings), $validSubcategoryIds) !== []) {
            return back()->withInput()->with('error', 'Harap isi seluruh '.$totalSubcategories.' butir penilaian yang valid.');
        }

        $reasons = is_array($request->reasons) ? $request->reasons : [];

        $calc = EvaluationScoringService::calculate($ratings);

        DB::transaction(function () use ($employeeId, $employee, $user, $month, $year, $calc, $ratings, $reasons) {
            $evaluation = Evaluation::updateOrCreate([
                'employee_id' => $employeeId,
                'period_month' => $month,
                'period_year' => $year,
            ], [
                'user_id' => $user->id,
                'division_id' => $employee->division_id,
                'total_score' => $calc['total_score'],
                'average_score' => $calc['average_score'],
                'predicate' => $calc['predicate'],
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Bersihkan detail lama jika edit
            EvaluationDetail::where('evaluation_id', $evaluation->id)->delete();

            foreach ($ratings as $subcategoryId => $rating) {
                $reasonText = isset($reasons[$subcategoryId]) && is_string($reasons[$subcategoryId])
                    ? mb_substr(trim($reasons[$subcategoryId]), 0, 500)
                    : '';

                EvaluationDetail::create([
                    'evaluation_id' => $evaluation->id,
                    'subcategory_id' => (int) $subcategoryId,
                    'rating' => (int) $rating,
                    'score' => EvaluationScoringService::getRatingScore((int) $rating),
                    'reason' => $reasonText,
                ]);
            }

            // Tandai edit request sebagai completed bila ada
            EditRequest::where('employee_id', $employeeId)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->where('status', 'approved')
                ->update(['status' => 'completed']);
        });

        return redirect()->route('penilaian.index', [
            'month' => $month,
            'year' => $year,
            'tab' => 'evaluated',
        ])->with('success', 'Penilaian untuk '.$employee->name.' berhasil disimpan dengan Total Skor '.$calc['total_score'].' ('.$calc['predicate'].').');
    }
}

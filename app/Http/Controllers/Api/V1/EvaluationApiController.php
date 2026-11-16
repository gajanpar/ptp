<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationDetail;
use App\Models\EvaluationSubcategory;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EvaluationApiController extends ApiController
{
    /**
     * Get queue of active TAD employees who have not been evaluated for the given period.
     */
    public function unassessed(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $month = (int) $request->input('month', date('n'));
        $year = (int) $request->input('year', date('Y'));

        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }

        $divisionId = $isAdmin
            ? ($request->filled('division_id') && $request->input('division_id') !== 'all' ? (int) $request->input('division_id') : null)
            : $user->division_id;

        // Get IDs of employees already evaluated in this period
        $evaluatedIdsQuery = Evaluation::where('period_month', $month)
            ->where('period_year', $year);

        if ($divisionId) {
            $evaluatedIdsQuery->where('division_id', $divisionId);
        }
        $evaluatedIds = $evaluatedIdsQuery->pluck('employee_id')->all();

        // Get unassessed active employees
        $unassessedQuery = Employee::with('division')
            ->where('is_active', true)
            ->whereNotIn('id', $evaluatedIds)
            ->orderBy('name');

        if ($divisionId) {
            $unassessedQuery->where('division_id', $divisionId);
        }

        $unassessed = $unassessedQuery->get()->map(function ($emp) {
            return [
                'id' => $emp->id,
                'nik' => $emp->nik ?: '-',
                'name' => $emp->name,
                'position' => $emp->position ?: '-',
                'division_id' => $emp->division_id,
                'division_name' => $emp->division?->name ?: '-',
                'vendor_name' => $emp->vendor_name ?: 'PT PDS',
            ];
        });

        return $this->jsonSuccess([
            'month' => $month,
            'year' => $year,
            'division_id' => $divisionId,
            'total_unassessed' => $unassessed->count(),
            'employees' => $unassessed,
        ], 'Daftar antrian TAD belum dinilai berhasil diambil.');
    }

    /**
     * Get the active AKHLAK rubric definition (categories, subcategories, rating scale).
     */
    public function rubrik(): JsonResponse
    {
        $categories = EvaluationCategory::with('subcategories')->orderBy('order_index')->get()->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'subcategories' => $cat->subcategories->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'name' => $sub->name,
                        'description' => $sub->description,
                        'max_score' => 5.88,
                    ];
                }),
            ];
        });

        $ratingOptions = EvaluationScoringService::getRatingOptions();

        return $this->jsonSuccess([
            'categories' => $categories,
            'rating_options' => $ratingOptions,
            'total_subcategories' => EvaluationSubcategory::count(),
        ], 'Struktur rubrik penilaian AKHLAK berhasil dimuat.');
    }

    /**
     * Submit an evaluation for a single TAD employee.
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2035',
            'ratings' => 'required|array',
            'ratings.*' => 'required|integer|between:1,6',
            'reasons' => 'nullable|array',
            'reasons.*' => 'nullable|string|max:500',
        ], [
            'employee_id.required' => 'ID Tenaga Alih Daya (TAD) wajib disertakan.',
            'ratings.required' => 'Nilai butir subkategori (1-6) wajib diisi lengkap.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi penilaian gagal', 422, $validator->errors());
        }

        $employeeId = (int) $request->input('employee_id');
        $month = (int) $request->input('month');
        $year = (int) $request->input('year');
        $ratings = $request->input('ratings');
        $reasons = $request->input('reasons', []);

        $employee = Employee::with('division')->findOrFail($employeeId);

        // Security: Divisional access control (Anti-IDOR)
        if (! $isAdmin && $user->division_id && $employee->division_id !== $user->division_id) {
            return $this->jsonError('Akses ditolak. Anda tidak memiliki otoritas menilai karyawan dari divisi lain.', 403);
        }

        // Security: Lock & SLA Enforcement check
        $divisionId = $employee->division_id;
        $isPeriodOpen = PeriodManagement::isOpen($divisionId, $month, $year);
        $hasApprovedIndividual = EditRequest::where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('status', 'approved')
            ->exists();
        $hasApprovedDivision = EditRequest::where('division_id', $divisionId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('request_type', 'division_access')
            ->where('status', 'approved')
            ->exists();

        if (! $isAdmin && ! $isPeriodOpen && ! $hasApprovedIndividual && ! $hasApprovedDivision) {
            return $this->jsonError('Penilaian periode ini sedang terkunci (SLA Gembok Aktif). Diperlukan persetujuan pengajuan buka akses dari Admin SDM.', 423);
        }

        // Validate all 17 subcategories exist and are present
        $subcategories = EvaluationSubcategory::all()->keyBy('id');
        $totalSubcategories = $subcategories->count();
        if (count($ratings) !== $totalSubcategories || array_diff(array_keys($ratings), $subcategories->keys()->all()) !== []) {
            return $this->jsonError("Seluruh {$totalSubcategories} butir rubrik penilaian AKHLAK harus dinilai dengan ID subkategori yang valid.", 422);
        }

        // Persist evaluation atomically
        [$evaluation, $calc] = $this->persistEvaluation($employee, $user, $month, $year, $ratings, $reasons, $subcategories);

        ActivityLog::log(
            'api_evaluation_submitted',
            "Penilaian API untuk TAD {$employee->name} (Bulan {$month}/{$year}) berhasil disimpan dengan skor {$calc['total_score']} ({$calc['predicate']})",
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->jsonSuccess([
            'evaluation_id' => $evaluation->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'period' => "{$month}/{$year}",
            'total_score' => $calc['total_score'],
            'average_score' => $calc['average_score'],
            'predicate' => $calc['predicate'],
            'submitted_at' => $evaluation->submitted_at?->toIso8601String(),
        ], "Penilaian untuk {$employee->name} berhasil disimpan.");
    }

    /**
     * Submit evaluations for multiple TAD employees in a single batch.
     */
    public function batchSubmit(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2035',
            'evaluations' => 'required|array|min:1',
            'evaluations.*.employee_id' => 'required|exists:employees,id',
            'evaluations.*.ratings' => 'required|array',
            'evaluations.*.ratings.*' => 'required|integer|between:1,6',
            'evaluations.*.reasons' => 'nullable|array',
        ], [
            'evaluations.required' => 'Daftar data penilaian TAD wajib disertakan.',
        ]);

        if ($validator->fails()) {
            return $this->jsonError('Validasi batch penilaian gagal', 422, $validator->errors());
        }

        $month = (int) $request->input('month');
        $year = (int) $request->input('year');
        $items = $request->input('evaluations');
        $subcategories = EvaluationSubcategory::all()->keyBy('id');
        $validSubIds = $subcategories->keys()->all();
        $totalSubcategories = $subcategories->count();
        $isAdmin = $user->role === 'admin';

        // Preload target employees to eliminate N+1 queries inside the batch loop
        $employeeIds = collect($items)->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->all();
        $employeesById = Employee::whereIn('id', $employeeIds)->get()->keyBy('id');

        $processed = [];
        $failed = [];
        $openDivisionCache = [];
        $divisionAccessCache = [];

        foreach ($items as $index => $item) {
            $empId = (int) $item['employee_id'];
            $ratings = $item['ratings'] ?? [];
            $reasons = is_array($item['reasons'] ?? null) ? $item['reasons'] : [];

            $emp = $employeesById->get($empId);
            if (! $emp) {
                $failed[] = ['index' => $index, 'employee_id' => $empId, 'reason' => 'Karyawan tidak ditemukan.'];

                continue;
            }

            if (! $isAdmin && $user->division_id && (int) $emp->division_id !== (int) $user->division_id) {
                $failed[] = ['index' => $index, 'employee_id' => $empId, 'name' => $emp->name, 'reason' => 'Tidak memiliki otoritas divisi.'];

                continue;
            }

            // Enforce SLA period lock unless admin or approved edit/access request exists
            $divId = (int) $emp->division_id;
            if (! array_key_exists($divId, $openDivisionCache)) {
                $openDivisionCache[$divId] = PeriodManagement::isOpen($divId, $month, $year);
                $divisionAccessCache[$divId] = EditRequest::where('division_id', $divId)
                    ->where('period_month', $month)
                    ->where('period_year', $year)
                    ->where('request_type', 'division_access')
                    ->where('status', 'approved')
                    ->exists();
            }

            $hasIndividualEdit = ! $isAdmin && ! $openDivisionCache[$divId] && ! $divisionAccessCache[$divId]
                ? EditRequest::where('employee_id', $emp->id)
                    ->where('period_month', $month)
                    ->where('period_year', $year)
                    ->where('request_type', 'individual_edit')
                    ->where('status', 'approved')
                    ->exists()
                : false;

            if (! $isAdmin && ! $openDivisionCache[$divId] && ! $divisionAccessCache[$divId] && ! $hasIndividualEdit) {
                $failed[] = ['index' => $index, 'employee_id' => $empId, 'name' => $emp->name, 'reason' => 'Periode penilaian terkunci (SLA Gembok Aktif).'];

                continue;
            }

            if (count($ratings) !== $totalSubcategories || array_diff(array_keys($ratings), $validSubIds) !== []) {
                $failed[] = ['index' => $index, 'employee_id' => $empId, 'name' => $emp->name, 'reason' => "Butir penilaian tidak lengkap atau ID subkategori tidak valid ({$totalSubcategories} butir wajib diisi)."];

                continue;
            }

            [, $calc] = $this->persistEvaluation($emp, $user, $month, $year, $ratings, $reasons, $subcategories);

            $processed[] = [
                'employee_id' => $emp->id,
                'name' => $emp->name,
                'total_score' => $calc['total_score'],
                'predicate' => $calc['predicate'],
            ];
        }

        ActivityLog::log(
            'api_batch_evaluation_submitted',
            "Pengguna {$user->name} memproses batch penilaian API: ".count($processed).' berhasil, '.count($failed).' gagal',
            $user->id,
            $request->ip(),
            $request->userAgent()
        );

        return $this->jsonSuccess([
            'month' => $month,
            'year' => $year,
            'total_submitted' => count($items),
            'total_success' => count($processed),
            'total_failed' => count($failed),
            'processed' => $processed,
            'failed' => $failed,
        ], 'Batch penilaian berhasil diproses.');
    }

    /**
     * Persist an evaluation header, its 17 detail rows, and complete any approved edit requests.
     *
     * @return array{0: Evaluation, 1: array}
     */
    private function persistEvaluation(
        Employee $employee,
        $user,
        int $month,
        int $year,
        array $ratings,
        array $reasons,
        $subcategories
    ): array {
        $calc = EvaluationScoringService::calculate($ratings);

        $evaluation = DB::transaction(function () use ($employee, $user, $month, $year, $calc, $ratings, $reasons, $subcategories) {
            $eval = Evaluation::updateOrCreate([
                'employee_id' => $employee->id,
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

            EvaluationDetail::where('evaluation_id', $eval->id)->delete();

            foreach ($ratings as $subId => $rating) {
                if (! $subcategories->has($subId)) {
                    continue;
                }

                $reasonText = isset($reasons[$subId]) && is_string($reasons[$subId])
                    ? mb_substr(trim($reasons[$subId]), 0, 500)
                    : null;

                EvaluationDetail::create([
                    'evaluation_id' => $eval->id,
                    'subcategory_id' => (int) $subId,
                    'rating' => (int) $rating,
                    'score' => EvaluationScoringService::getRatingScore((int) $rating),
                    'reason' => $reasonText,
                ]);
            }

            EditRequest::where('employee_id', $employee->id)
                ->where('period_month', $month)
                ->where('period_year', $year)
                ->where('status', 'approved')
                ->update(['status' => 'completed']);

            return $eval;
        });

        return [$evaluation, $calc];
    }
}

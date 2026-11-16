<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationSubcategory;
use App\Services\EvaluationScoringService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display executive branch performance reports (Monthly & Annual).
     */
    public function index(Request $request): View
    {
        $periodType = $request->get('period_type', 'monthly');
        $year = (int) $request->get('year', 2026);
        $month = (int) $request->get('month', 4);
        if ($month < 1 || $month > 12) {
            $month = 4;
        }
        $divisionId = (string) $request->get('division_id', 'all');

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        $divisions = Division::where('is_active', true)->orderBy('id')->get();
        $selectedDivision = $divisionId !== 'all' ? Division::find((int) $divisionId) : null;

        if ($periodType === 'annual') {
            return $this->renderAnnualReport($request, $year, $divisionId, $divisions, $monthNames);
        }

        return $this->renderMonthlyReport($request, $month, $year, $divisionId, $divisions, $monthNames, $selectedDivision);
    }

    /**
     * Show single evaluation detail breakdown with all 17 AKHLAK indicators.
     */
    public function show($id)
    {
        $evaluation = Evaluation::with([
            'employee.division',
            'evaluator',
            'details.subcategory.category',
        ])->findOrFail($id);

        $groupedDetails = EvaluationScoringService::groupDetailsByCategory($evaluation->details);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'evaluation' => $evaluation,
                'groupedDetails' => $groupedDetails,
            ]);
        }

        return view('admin.reports.detail', [
            'evaluation' => $evaluation,
            'groupedDetails' => $groupedDetails,
        ]);
    }

    /**
     * Print official individual evaluation form with Pelindo Cirebon letterhead.
     */
    public function printSingle(Request $request, $id): View
    {
        $action = in_array($request->get('action'), ['print', 'save_pdf'], true)
            ? $request->get('action')
            : 'print';

        $evaluation = Evaluation::with([
            'employee.division',
            'evaluator',
            'details.subcategory.category',
        ])->findOrFail($id);

        return view('admin.reports.print_single', [
            'evaluation' => $evaluation,
            'groupedDetails' => EvaluationScoringService::groupDetailsByCategory($evaluation->details),
            'action' => $action,
        ]);
    }

    /**
     * Print official branch summary report (Monthly / Annual) with executive signatures.
     */
    public function printSummary(Request $request): View
    {
        $periodType = $request->get('period_type', 'monthly');
        $year = (int) $request->get('year', 2026);
        $month = (int) $request->get('month', 4);
        if ($month < 1 || $month > 12) {
            $month = 4;
        }
        $divisionId = (string) $request->get('division_id', 'all');
        $action = in_array($request->get('action'), ['print', 'save_pdf'], true)
            ? $request->get('action')
            : 'print';

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        $division = $divisionId !== 'all' ? Division::find((int) $divisionId) : null;

        if ($periodType === 'annual') {
            $annualData = $this->computeAnnualData($year, $divisionId);

            return view('admin.reports.print_summary_annual', [
                'year' => $year,
                'division' => $division,
                'annualData' => $annualData,
                'monthNames' => $monthNames,
                'action' => $action,
            ]);
        }

        $query = Evaluation::with(['employee.division', 'evaluator'])
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($divisionId !== 'all') {
            $query->where('division_id', (int) $divisionId);
        }

        $evaluations = $query->orderByDesc('total_score')->get();

        $metrics = $this->computeMonthlyMetrics($evaluations, $divisionId);

        return view('admin.reports.print_summary', [
            'month' => $month,
            'year' => $year,
            'monthName' => $monthNames[$month] ?? '-',
            'division' => $division,
            'evaluations' => $evaluations,
            'metrics' => $metrics,
            'action' => $action,
        ]);
    }

    /**
     * Secure local CSV/Spreadsheet export (replaces insecure legacy external Google Apps Script).
     * Output includes UTF-8 BOM for seamless rendering in Microsoft Excel and spreadsheet tools.
     */
    public function export(Request $request): StreamedResponse
    {
        $periodType = $request->get('period_type', 'monthly');
        $year = (int) $request->get('year', 2026);
        $month = (int) $request->get('month', 4);
        if ($month < 1 || $month > 12) {
            $month = 4;
        }
        $divisionId = (string) $request->get('division_id', 'all');
        $format = strtolower((string) $request->get('format', 'csv'));

        $monthName = EvaluationScoringService::getMonthName($month);
        $subcategories = EvaluationSubcategory::with('category')->orderBy('order_index')->get();

        $baseFilename = ($periodType === 'annual')
            ? "Laporan_Tahunan_Kinerja_TAD_Pelindo_Cirebon_{$year}"
            : "Laporan_Bulanan_Kinerja_TAD_Pelindo_Cirebon_{$monthName}_{$year}";

        if ($format === 'excel') {
            return $this->streamExcelExport($baseFilename, $periodType, $month, $year, $divisionId, $subcategories);
        }

        return $this->streamCsvExport($baseFilename, $periodType, $month, $year, $divisionId, $subcategories);
    }

    /**
     * Sanitize spreadsheet cell text against CSV/Excel Formula Injection (CWE-1236).
     */
    private function sanitizeSpreadsheetCell(mixed $value): mixed
    {
        if (is_numeric($value) || $value === null) {
            return $value;
        }

        $str = (string) $value;
        if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * Load monthly evaluations filtered cleanly by division for export streams.
     */
    private function getMonthlyEvaluationsForExport(int $month, int $year, string $divisionId)
    {
        $query = Evaluation::with([
            'employee.division',
            'evaluator',
            'details',
        ])
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($divisionId !== 'all') {
            $query->where('division_id', (int) $divisionId);
        }

        return $query->orderByDesc('total_score')->get();
    }

    /**
     * Stream Excel-compatible HTML spreadsheet with corporate Pelindo styles.
     */
    private function streamExcelExport(
        string $baseFilename,
        string $periodType,
        int $month,
        int $year,
        string $divisionId,
        $subcategories
    ): StreamedResponse {
        $filename = "{$baseFilename}.xls";
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];
        $monthNames = EvaluationScoringService::MONTH_NAMES;

        return response()->stream(function () use ($periodType, $month, $year, $divisionId, $monthNames, $subcategories): void {
            $out = fopen('php://output', 'w');

            fwrite($out, '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">'."\n");
            fwrite($out, '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'."\n");
            fwrite($out, "<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan Kinerja TAD</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->\n");
            fwrite($out, '<style>
                body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #0f172a; }
                table { border-collapse: collapse; width: 100%; }
                th { background-color: #00223B; color: #FFFFFF; font-weight: bold; border: 1px solid #CBD5E1; padding: 7px 10px; text-align: center; }
                td { border: 1px solid #CBD5E1; padding: 6px 8px; vertical-align: middle; }
                .title { font-size: 13pt; font-weight: bold; color: #00223B; }
                .subtitle { font-size: 11pt; font-weight: bold; color: #0072CE; }
                .meta { font-size: 9.5pt; color: #64748B; }
                .num { mso-number-format: "0\.00"; text-align: right; }
                .str { mso-number-format: "\@"; }
                .str-center { mso-number-format: "\@"; text-align: center; }
                .center { text-align: center; }
                .right { text-align: right; }
            </style>'."\n");
            fwrite($out, '</head><body>'."\n<table>\n");

            if ($periodType === 'annual') {
                $annualData = $this->computeAnnualData($year, $divisionId);

                fwrite($out, '<tr><td colspan="18" class="title">PT PELABUHAN INDONESIA (PERSERO) - BRANCH CIREBON</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="18" class="subtitle">SISTEM PENILAIAN KINERJA TENAGA ALIH DAYA (SPKTAD)</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="18" class="meta">REKAPITULASI PENILAIAN TAHUNAN - TAHUN '.htmlspecialchars((string) $year).'</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="18" class="meta">Tanggal Unduh: '.now()->translatedFormat('d F Y H:i').' WIB</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="18"></td></tr>'."\n<tr>");
                fwrite($out, '<th>No</th><th>NIK</th><th>Nama Karyawan TAD</th><th>Divisi Penempatan</th><th>Jabatan / Posisi</th><th>Mitra Vendor</th>');
                for ($m = 1; $m <= 12; $m++) {
                    fwrite($out, '<th>'.substr($monthNames[$m], 0, 3).'</th>');
                }
                fwrite($out, '<th>Bulan Dinilai</th><th>Total Akumulasi</th><th>Rata-Rata Tahunan</th><th>Predikat Akhir</th>'."</tr>\n");

                foreach ($annualData['rows'] as $idx => $row) {
                    $emp = $row['employee'];
                    fwrite($out, '<tr>');
                    fwrite($out, '<td class="center">'.($idx + 1).'</td>');
                    fwrite($out, '<td class="str-center">'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->nik ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->name ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->division?->name ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->position ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->vendor_name ?? '-')).'</td>');

                    for ($m = 1; $m <= 12; $m++) {
                        fwrite($out, isset($row['monthly_scores'][$m])
                            ? '<td class="num">'.number_format($row['monthly_scores'][$m], 2, '.', '').'</td>'
                            : '<td class="center">-</td>');
                    }

                    fwrite($out, '<td class="center">'.htmlspecialchars("{$row['evaluated_count']}/12").'</td>');
                    fwrite($out, '<td class="num">'.number_format($row['total_score_sum'], 2, '.', '').'</td>');
                    fwrite($out, '<td class="num">'.number_format($row['annual_average'], 2, '.', '').'</td>');
                    fwrite($out, '<td class="center">'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($row['predicate'])).'</td>');
                    fwrite($out, "</tr>\n");
                }
            } else {
                $evaluations = $this->getMonthlyEvaluationsForExport($month, $year, $divisionId);
                $colspan = 11 + $subcategories->count();

                fwrite($out, '<tr><td colspan="'.$colspan.'" class="title">PT PELABUHAN INDONESIA (PERSERO) - BRANCH CIREBON</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="'.$colspan.'" class="subtitle">SISTEM PENILAIAN KINERJA TENAGA ALIH DAYA (SPKTAD)</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="'.$colspan.'" class="meta">REKAPITULASI PENILAIAN BULANAN - '.htmlspecialchars("{$monthNames[$month]} {$year}").'</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="'.$colspan.'" class="meta">Tanggal Unduh: '.now()->translatedFormat('d F Y H:i').' WIB</td></tr>'."\n");
                fwrite($out, '<tr><td colspan="'.$colspan.'"></td></tr>'."\n<tr>");
                fwrite($out, '<th>No</th><th>NIK</th><th>Nama Karyawan TAD</th><th>Divisi Penempatan</th><th>Jabatan</th><th>Mitra Vendor</th>');
                foreach ($subcategories as $sub) {
                    $catName = $sub->category?->name ?? 'Kategori';
                    fwrite($out, '<th>'.htmlspecialchars("Item #{$sub->order_index} ({$catName})").'</th>');
                }
                fwrite($out, '<th>Total Skor (0-100)</th><th>Rata-Rata Rating</th><th>Predikat Kinerja</th><th>Pejabat Penilai</th><th>Tanggal Evaluasi</th>'."</tr>\n");

                foreach ($evaluations as $idx => $eval) {
                    $detailMap = $eval->details->keyBy('subcategory_id');
                    $emp = $eval->employee;

                    fwrite($out, '<tr>');
                    fwrite($out, '<td class="center">'.($idx + 1).'</td>');
                    fwrite($out, '<td class="str-center">'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->nik ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->name ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->division?->name ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->position ?? '-')).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($emp?->vendor_name ?? '-')).'</td>');

                    foreach ($subcategories as $sub) {
                        if (isset($detailMap[$sub->id])) {
                            $valStr = number_format($detailMap[$sub->id]->score, 2)." (R{$detailMap[$sub->id]->rating})";
                            fwrite($out, '<td class="center">'.htmlspecialchars($valStr).'</td>');
                        } else {
                            fwrite($out, '<td class="center">-</td>');
                        }
                    }

                    fwrite($out, '<td class="num">'.number_format($eval->total_score, 2, '.', '').'</td>');
                    fwrite($out, '<td class="num">'.number_format($eval->average_score, 2, '.', '').'</td>');
                    fwrite($out, '<td class="center">'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($eval->predicate)).'</td>');
                    fwrite($out, '<td>'.htmlspecialchars((string) $this->sanitizeSpreadsheetCell($eval->evaluator?->name ?? '-')).'</td>');
                    fwrite($out, '<td class="center">'.htmlspecialchars($eval->submitted_at ? $eval->submitted_at->format('d/m/Y H:i') : '-').'</td>');
                    fwrite($out, "</tr>\n");
                }
            }

            fwrite($out, "</table>\n</body></html>\n");
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Stream UTF-8 BOM CSV spreadsheet export.
     */
    private function streamCsvExport(
        string $baseFilename,
        string $periodType,
        int $month,
        int $year,
        string $divisionId,
        $subcategories
    ): StreamedResponse {
        $filename = "{$baseFilename}.csv";
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];
        $monthNames = EvaluationScoringService::MONTH_NAMES;

        return response()->stream(function () use ($periodType, $month, $year, $divisionId, $monthNames, $subcategories): void {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['PT PELABUHAN INDONESIA (PERSERO) - BRANCH CIREBON']);
            fputcsv($handle, ['SISTEM PENILAIAN KINERJA TENAGA ALIH DAYA (SPKTAD)']);
            fputcsv($handle, [$periodType === 'annual'
                ? "REKAPITULASI PENILAIAN TAHUNAN - TAHUN {$year}"
                : "REKAPITULASI PENILAIAN BULANAN - {$monthNames[$month]} {$year}"]);
            fputcsv($handle, ['Tanggal Unduh: '.now()->translatedFormat('d F Y H:i').' WIB']);
            fputcsv($handle, []);

            if ($periodType === 'annual') {
                $annualData = $this->computeAnnualData($year, $divisionId);

                $columns = ['No', 'NIK', 'Nama Karyawan TAD', 'Divisi Penempatan', 'Jabatan / Posisi', 'Mitra Vendor'];
                for ($m = 1; $m <= 12; $m++) {
                    $columns[] = substr($monthNames[$m], 0, 3);
                }
                $columns = array_merge($columns, ['Bulan Dinilai', 'Total Akumulasi', 'Rata-Rata Tahunan', 'Predikat Akhir']);
                fputcsv($handle, $columns);

                foreach ($annualData['rows'] as $idx => $row) {
                    $emp = $row['employee'];
                    $line = array_map($this->sanitizeSpreadsheetCell(...), [
                        $idx + 1,
                        $emp?->nik ?? '-',
                        $emp?->name ?? '-',
                        $emp?->division?->name ?? '-',
                        $emp?->position ?? '-',
                        $emp?->vendor_name ?? '-',
                    ]);

                    for ($m = 1; $m <= 12; $m++) {
                        $line[] = isset($row['monthly_scores'][$m])
                            ? number_format($row['monthly_scores'][$m], 2)
                            : '-';
                    }

                    $line[] = "{$row['evaluated_count']}/12";
                    $line[] = number_format($row['total_score_sum'], 2);
                    $line[] = number_format($row['annual_average'], 2);
                    $line[] = $this->sanitizeSpreadsheetCell($row['predicate']);

                    fputcsv($handle, $line);
                }
            } else {
                $evaluations = $this->getMonthlyEvaluationsForExport($month, $year, $divisionId);

                $columns = ['No', 'NIK', 'Nama Karyawan TAD', 'Divisi Penempatan', 'Jabatan', 'Mitra Vendor'];
                foreach ($subcategories as $sub) {
                    $catName = $sub->category?->name ?? 'Kategori';
                    $columns[] = "Item #{$sub->order_index} ({$catName})";
                }
                $columns = array_merge($columns, ['Total Skor (0-100)', 'Rata-Rata Rating', 'Predikat Kinerja', 'Pejabat Penilai', 'Tanggal Evaluasi']);
                fputcsv($handle, $columns);

                foreach ($evaluations as $idx => $eval) {
                    $detailMap = $eval->details->keyBy('subcategory_id');
                    $emp = $eval->employee;

                    $line = array_map($this->sanitizeSpreadsheetCell(...), [
                        $idx + 1,
                        $emp?->nik ?? '-',
                        $emp?->name ?? '-',
                        $emp?->division?->name ?? '-',
                        $emp?->position ?? '-',
                        $emp?->vendor_name ?? '-',
                    ]);

                    foreach ($subcategories as $sub) {
                        $line[] = isset($detailMap[$sub->id])
                            ? number_format($detailMap[$sub->id]->score, 2)." (R{$detailMap[$sub->id]->rating})"
                            : '-';
                    }

                    $line[] = number_format($eval->total_score, 2);
                    $line[] = number_format($eval->average_score, 2);
                    $line[] = $this->sanitizeSpreadsheetCell($eval->predicate);
                    $line[] = $this->sanitizeSpreadsheetCell($eval->evaluator?->name ?? '-');
                    $line[] = $eval->submitted_at ? $eval->submitted_at->format('d/m/Y H:i') : '-';

                    fputcsv($handle, $line);
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Helper to render monthly report view.
     */
    private function renderMonthlyReport(
        Request $request,
        int $month,
        int $year,
        string $divisionId,
        $divisions,
        array $monthNames,
        ?Division $selectedDivision
    ): View {
        $query = Evaluation::with(['employee.division', 'evaluator'])
            ->where('period_month', $month)
            ->where('period_year', $year);

        if ($divisionId !== 'all') {
            $query->where('division_id', (int) $divisionId);
        }

        $evaluations = $query->orderByDesc('total_score')->get();
        $metrics = $this->computeMonthlyMetrics($evaluations, $divisionId);

        // Division comparison — single grouped query to prevent N+1 (one query per division).
        $allDivisionEvaluations = Evaluation::where('period_month', $month)
            ->where('period_year', $year)
            ->get(['division_id', 'total_score'])
            ->groupBy('division_id');

        $divisionComparisons = [];
        foreach ($divisions as $div) {
            $divEvals = $allDivisionEvaluations->get($div->id, collect());
            $count = $divEvals->count();
            $avgScore = $count > 0 ? round($divEvals->avg('total_score'), 2) : 0.0;

            $divisionComparisons[] = [
                'division' => $div,
                'count' => $count,
                'avg_score' => $avgScore,
                'predicate' => $count > 0 ? EvaluationScoringService::getPredicate($avgScore) : 'Belum Ada',
            ];
        }

        return view('admin.reports.index', [
            'periodType' => 'monthly',
            'month' => $month,
            'year' => $year,
            'monthNames' => $monthNames,
            'currentMonthName' => $monthNames[$month] ?? '-',
            'divisionId' => $divisionId,
            'divisions' => $divisions,
            'selectedDivision' => $selectedDivision,
            'evaluations' => $evaluations,
            'metrics' => $metrics,
            'divisionComparisons' => $divisionComparisons,
        ]);
    }

    /**
     * Helper to render annual report view.
     */
    private function renderAnnualReport(
        Request $request,
        int $year,
        string $divisionId,
        $divisions,
        array $monthNames
    ): View {
        $annualData = $this->computeAnnualData($year, $divisionId);

        return view('admin.reports.index', [
            'periodType' => 'annual',
            'month' => 1,
            'year' => $year,
            'monthNames' => $monthNames,
            'currentMonthName' => "Tahun {$year}",
            'divisionId' => $divisionId,
            'divisions' => $divisions,
            'selectedDivision' => $divisionId !== 'all' ? Division::find((int) $divisionId) : null,
            'annualData' => $annualData,
        ]);
    }

    /**
     * Calculate monthly branch metrics.
     */
    private function computeMonthlyMetrics($evaluations, string $divisionId): array
    {
        $targetEmployeeCount = ($divisionId === 'all')
            ? Employee::where('is_active', true)->count()
            : Employee::where('division_id', (int) $divisionId)->where('is_active', true)->count();

        $count = $evaluations->count();
        $averageScore = $count > 0 ? round($evaluations->avg('total_score'), 2) : 0.0;
        $highest = $evaluations->first();
        $lowest = $evaluations->last();
        $completionRate = $targetEmployeeCount > 0 ? round(($count / $targetEmployeeCount) * 100) : 0;

        $predicates = [
            'Sangat Baik' => $evaluations->where('predicate', 'Sangat Baik')->count(),
            'Baik' => $evaluations->where('predicate', 'Baik')->count(),
            'Cukup Baik' => $evaluations->where('predicate', 'Cukup Baik')->count(),
            'Perlu Perhatian' => $evaluations->where('predicate', 'Perlu Perhatian')->count(),
        ];

        return [
            'total_evaluated' => $count,
            'target_employees' => $targetEmployeeCount,
            'completion_rate' => $completionRate,
            'average_score' => $averageScore,
            'branch_predicate' => EvaluationScoringService::getPredicate($averageScore),
            'highest' => $highest,
            'lowest' => $lowest,
            'predicates' => $predicates,
        ];
    }

    /**
     * Calculate annual aggregate data across 12 months for active TAD employees.
     */
    private function computeAnnualData(int $year, string $divisionId): array
    {
        $employeeQuery = Employee::with('division')
            ->where('is_active', true);

        if ($divisionId !== 'all') {
            $employeeQuery->where('division_id', (int) $divisionId);
        }

        $employees = $employeeQuery->orderBy('division_id')->orderBy('name')->get();

        // Get all evaluations for the year
        $evaluations = Evaluation::where('period_year', $year)
            ->get()
            ->groupBy('employee_id');

        $rows = [];
        $totalSum = 0;
        $evaluatedCountTotal = 0;

        $predicateSummary = [
            'Sangat Baik' => 0,
            'Baik' => 0,
            'Cukup Baik' => 0,
            'Perlu Perhatian' => 0,
        ];

        foreach ($employees as $emp) {
            $empEvals = $evaluations->get($emp->id, collect());
            $monthlyScores = [];
            $sum = 0;

            foreach ($empEvals as $ev) {
                $monthlyScores[$ev->period_month] = (float) $ev->total_score;
                $sum += (float) $ev->total_score;
            }

            $count = count($monthlyScores);
            $annualAvg = $count > 0 ? round($sum / $count, 2) : 0.0;
            $predicate = $count > 0 ? EvaluationScoringService::getPredicate($annualAvg) : 'Belum Ada';

            if ($count > 0 && isset($predicateSummary[$predicate])) {
                $predicateSummary[$predicate]++;
                $totalSum += $annualAvg;
                $evaluatedCountTotal++;
            }

            $rows[] = [
                'employee' => $emp,
                'monthly_scores' => $monthlyScores,
                'evaluated_count' => $count,
                'total_score_sum' => round($sum, 2),
                'annual_average' => $annualAvg,
                'predicate' => $predicate,
            ];
        }

        // Sort rows by annual average descending
        usort($rows, function ($a, $b) {
            return $b['annual_average'] <=> $a['annual_average'];
        });

        $grandAverage = $evaluatedCountTotal > 0 ? round($totalSum / $evaluatedCountTotal, 2) : 0.0;

        return [
            'rows' => $rows,
            'total_employees' => $employees->count(),
            'evaluated_employees' => $evaluatedCountTotal,
            'grand_average' => $grandAverage,
            'grand_predicate' => EvaluationScoringService::getPredicate($grandAverage),
            'predicate_summary' => $predicateSummary,
        ];
    }
}

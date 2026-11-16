<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationDetail;
use App\Models\EvaluationSubcategory;
use App\Models\User;

/**
 * DataQualityService - Standar Audit Kualitas & Integritas Data Korporat SPKTAD
 * Menerapkan dimensi Kualitas Data: Kelengkapan, Keunikan, Validitas, Integritas Relasional, dan Konsistensi.
 */
class DataQualityService
{
    /**
     * Run all comprehensive data quality audits and return detailed report.
     *
     * @return array<string, mixed>
     */
    public function auditAll(): array
    {
        $checks = [];

        // 1. Completeness Dimension (Kelengkapan Data)
        $checks['completeness'] = $this->auditCompleteness();

        // 2. Uniqueness Dimension (Keunikan / Anti-Duplikasi)
        $checks['uniqueness'] = $this->auditUniqueness();

        // 3. Validity Dimension (Validitas Format & Skala Bisnis)
        $checks['validity'] = $this->auditValidity();

        // 4. Referential Integrity Dimension (Integritas Relasi Kunci Asing)
        $checks['referential_integrity'] = $this->auditReferentialIntegrity();

        // 5. Transaction & Dummy Data Status (Status Higienitas Produksi)
        $checks['hygiene_status'] = $this->auditHygiene();

        // Calculate Global Health Score
        $totalRules = 0;
        $passedRules = 0;
        $issues = [];

        foreach ($checks as $dimensionKey => $dimension) {
            foreach ($dimension['rules'] as $rule) {
                $totalRules++;
                if ($rule['passed']) {
                    $passedRules++;
                } else {
                    $issues[] = [
                        'dimension' => $dimensionKey,
                        'rule' => $rule['name'],
                        'severity' => $rule['severity'],
                        'description' => $rule['description'],
                        'count' => $rule['count'] ?? 0,
                    ];
                }
            }
        }

        $healthScore = $totalRules > 0 ? round(($passedRules / $totalRules) * 100, 1) : 100.0;
        $isProductionReady = empty(array_filter($issues, fn ($i) => $i['severity'] === 'CRITICAL'));

        return [
            'timestamp' => now()->toIso8601String(),
            'health_score' => $healthScore,
            'passed_rules' => $passedRules,
            'total_rules' => $totalRules,
            'is_production_ready' => $isProductionReady,
            'summary_status' => $healthScore === 100.0 ? 'EXCELLENT' : ($isProductionReady ? 'GOOD' : 'REQUIRES_ACTION'),
            'checks' => $checks,
            'issues' => $issues,
        ];
    }

    /**
     * Audit 1: Completeness
     */
    protected function auditCompleteness(): array
    {
        $rules = [];

        // Rule 1.1: Karyawan TAD tanpa NIK
        $missingNikCount = Employee::whereNull('nik')->orWhere('nik', '')->count();
        $rules[] = [
            'name' => 'TAD Employee NIK Presence',
            'passed' => $missingNikCount === 0,
            'severity' => 'CRITICAL',
            'count' => $missingNikCount,
            'description' => "Ditemukan {$missingNikCount} personel TAD tanpa NIK resmi.",
        ];

        // Rule 1.2: Karyawan TAD tanpa Divisi
        $missingDivCount = Employee::whereNull('division_id')->count();
        $rules[] = [
            'name' => 'TAD Employee Division Mapping',
            'passed' => $missingDivCount === 0,
            'severity' => 'CRITICAL',
            'count' => $missingDivCount,
            'description' => "Ditemukan {$missingDivCount} personel TAD tanpa penempatan divisi.",
        ];

        // Rule 1.3: Karyawan TAD tanpa Mitra Vendor
        $missingVendorCount = Employee::whereNull('vendor_name')->orWhere('vendor_name', '')->count();
        $rules[] = [
            'name' => 'TAD Vendor Name Completeness',
            'passed' => $missingVendorCount === 0,
            'severity' => 'WARNING',
            'count' => $missingVendorCount,
            'description' => "Ditemukan {$missingVendorCount} personel TAD tanpa informasi mitra penyedia alih daya.",
        ];

        // Rule 1.4: Rubrik Standar AKHLAK Lengkap (12 Kategori, 17 Subkategori)
        $catCount = EvaluationCategory::count();
        $subCount = EvaluationSubcategory::count();
        $isRubrikComplete = ($catCount === 12 && $subCount === 17);
        $rules[] = [
            'name' => 'Rubrik AKHLAK Master Completeness',
            'passed' => $isRubrikComplete,
            'severity' => 'CRITICAL',
            'count' => $subCount,
            'description' => "Rubrik AKHLAK saat ini memiliki {$catCount}/12 kategori dan {$subCount}/17 butir indikator.",
        ];

        return ['rules' => $rules];
    }

    /**
     * Audit 2: Uniqueness
     */
    protected function auditUniqueness(): array
    {
        $rules = [];

        // Rule 2.1: Duplikasi NIK Karyawan (SQLite + PostgreSQL portable HAVING)
        $duplicateNiks = Employee::select('nik')
            ->whereNotNull('nik')
            ->groupBy('nik')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $rules[] = [
            'name' => 'TAD Employee NIK Uniqueness',
            'passed' => $duplicateNiks === 0,
            'severity' => 'CRITICAL',
            'count' => $duplicateNiks,
            'description' => "Ditemukan {$duplicateNiks} kelompok NIK ganda pada master TAD.",
        ];

        // Rule 2.2: Duplikasi Username Pengguna
        $duplicateUsernames = User::select('username')
            ->groupBy('username')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $rules[] = [
            'name' => 'User Account Username Uniqueness',
            'passed' => $duplicateUsernames === 0,
            'severity' => 'CRITICAL',
            'count' => $duplicateUsernames,
            'description' => "Ditemukan {$duplicateUsernames} akun pengguna dengan username ganda.",
        ];

        // Rule 2.3: Duplikasi Evaluasi per Periode
        $duplicateEvals = Evaluation::select('employee_id', 'period_month', 'period_year')
            ->groupBy('employee_id', 'period_month', 'period_year')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $rules[] = [
            'name' => 'Evaluation Record Uniqueness per Period',
            'passed' => $duplicateEvals === 0,
            'severity' => 'CRITICAL',
            'count' => $duplicateEvals,
            'description' => "Ditemukan {$duplicateEvals} transaksi evaluasi ganda untuk karyawan dan periode yang sama.",
        ];

        return ['rules' => $rules];
    }

    /**
     * Audit 3: Validity
     */
    protected function auditValidity(): array
    {
        $rules = [];

        // Rule 3.1: Validitas Skala Rating (1 s.d. 6)
        $invalidRatingsCount = EvaluationDetail::where('rating', '<', 1)
            ->orWhere('rating', '>', 6)
            ->count();
        $rules[] = [
            'name' => 'Rating Scale Boundary Check (1-6)',
            'passed' => $invalidRatingsCount === 0,
            'severity' => 'CRITICAL',
            'count' => $invalidRatingsCount,
            'description' => "Ditemukan {$invalidRatingsCount} rincian penilaian di luar batas rating 1 s.d. 6.",
        ];

        // Rule 3.2: Validitas Rentang Total Skor (0 s.d. 100)
        $invalidScoresCount = Evaluation::where('total_score', '<', 0)
            ->orWhere('total_score', '>', 100)
            ->count();
        $rules[] = [
            'name' => 'Total Score Boundary Check (0-100)',
            'passed' => $invalidScoresCount === 0,
            'severity' => 'CRITICAL',
            'count' => $invalidScoresCount,
            'description' => "Ditemukan {$invalidScoresCount} evaluasi dengan total skor melampaui batas 0–100.",
        ];

        // Rule 3.3: Validitas Predikat Kinerja
        $validPredicates = ['Sangat Baik', 'Baik', 'Cukup Baik', 'Perlu Perhatian'];
        $invalidPredicatesCount = Evaluation::whereNotIn('predicate', $validPredicates)->count();
        $rules[] = [
            'name' => 'Standard Corporate Predicate Validity',
            'passed' => $invalidPredicatesCount === 0,
            'severity' => 'CRITICAL',
            'count' => $invalidPredicatesCount,
            'description' => "Ditemukan {$invalidPredicatesCount} evaluasi dengan predikat non-standar.",
        ];

        return ['rules' => $rules];
    }

    /**
     * Audit 4: Referential Integrity
     */
    protected function auditReferentialIntegrity(): array
    {
        $rules = [];

        // Rule 4.1: Karyawan dengan Divisi Tidak Terdaftar
        $orphanEmpDiv = Employee::whereDoesntHave('division')->count();
        $rules[] = [
            'name' => 'Employee to Division FK Integrity',
            'passed' => $orphanEmpDiv === 0,
            'severity' => 'CRITICAL',
            'count' => $orphanEmpDiv,
            'description' => "Ditemukan {$orphanEmpDiv} karyawan dengan referensi divisi tidak valid.",
        ];

        // Rule 4.2: Evaluasi tanpa Karyawan
        $orphanEvals = Evaluation::whereDoesntHave('employee')->count();
        $rules[] = [
            'name' => 'Evaluation to Employee FK Integrity',
            'passed' => $orphanEvals === 0,
            'severity' => 'CRITICAL',
            'count' => $orphanEvals,
            'description' => "Ditemukan {$orphanEvals} evaluasi yatim piatu (orphan record).",
        ];

        // Rule 4.3: Rincian Evaluasi tanpa Subkategori
        $orphanDetails = EvaluationDetail::whereDoesntHave('subcategory')->count();
        $rules[] = [
            'name' => 'Evaluation Detail to Rubrik Subcategory FK Integrity',
            'passed' => $orphanDetails === 0,
            'severity' => 'CRITICAL',
            'count' => $orphanDetails,
            'description' => "Ditemukan {$orphanDetails} butir penilaian merujuk ke subkategori yang tidak eksis.",
        ];

        return ['rules' => $rules];
    }

    /**
     * Audit 5: Hygiene & Dummy Data Audit
     */
    protected function auditHygiene(): array
    {
        $rules = [];

        $evaluationCount = Evaluation::count();
        $detailCount = EvaluationDetail::count();
        $activeEmpCount = Employee::where('is_active', true)->count();

        $rules[] = [
            'name' => 'Clean State Evaluation Count (Production Readiness)',
            'passed' => true, // Informational
            'severity' => 'INFO',
            'count' => $evaluationCount,
            'description' => "Jumlah transaksi evaluasi saat ini: {$evaluationCount} entri (Rincian butir: {$detailCount}).",
        ];

        $rules[] = [
            'name' => 'Active Employee Master Count',
            'passed' => $activeEmpCount >= 35,
            'severity' => 'WARNING',
            'count' => $activeEmpCount,
            'description' => "Jumlah personel TAD aktif: {$activeEmpCount} (Standar cabang: 35 TAD).",
        ];

        return ['rules' => $rules];
    }
}

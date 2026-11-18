<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationDetail;
use App\Models\EvaluationSubcategory;
use App\Models\PeriodManagement;
use App\Models\User;
use App\Services\DataQualityService;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DataTransitionAndQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        Artisan::call('db:seed');
    }

    public function test_production_seeder_initializes_master_data_without_dummy_evaluations(): void
    {
        // Bersihkan transaksi dummy dan jalankan ProductionSeeder
        EvaluationDetail::query()->delete();
        Evaluation::query()->delete();
        EditRequest::query()->delete();

        $this->seed(ProductionSeeder::class);

        // Pastikan master data lengkap
        $this->assertEquals(3, Division::where('is_active', true)->count());
        $this->assertEquals(4, User::where('is_active', true)->count());
        $this->assertEquals(12, EvaluationCategory::count());
        $this->assertEquals(17, EvaluationSubcategory::count());
        $this->assertEquals(35, Employee::where('is_active', true)->count());

        // Pastikan nol transaksi dummy pada database produksi
        $this->assertEquals(0, Evaluation::count());
        $this->assertEquals(0, EvaluationDetail::count());
        $this->assertEquals(0, EditRequest::count());
    }

    public function test_clean_dummy_command_purges_transactions_safely(): void
    {
        // Pastikan ada transaksi dummy sebelum dibersihkan (dari default seeder)
        $this->assertGreaterThan(0, Evaluation::count());
        $this->assertGreaterThan(0, EvaluationDetail::count());

        // Jalankan perintah pembersihan dengan opsi --force
        $exitCode = Artisan::call('ptp:clean-dummy', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        // Verifikasi transaksi dummy bersih tuntas
        $this->assertEquals(0, Evaluation::count());
        $this->assertEquals(0, EvaluationDetail::count());
        $this->assertEquals(0, EditRequest::count());

        // Namun master data penting TETAP UTUH (Preserved)
        $this->assertEquals(35, Employee::count());
        $this->assertEquals(3, Division::count());
        $this->assertEquals(12, EvaluationCategory::count());
        $this->assertEquals(17, EvaluationSubcategory::count());
    }

    public function test_clean_dummy_dry_run_does_not_mutate_data(): void
    {
        $evalCountBefore = Evaluation::count();
        $this->assertGreaterThan(0, $evalCountBefore);

        $exitCode = Artisan::call('ptp:clean-dummy', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);

        $this->assertEquals($evalCountBefore, Evaluation::count());
    }

    public function test_data_quality_service_detects_flaws_accurately(): void
    {
        /** @var DataQualityService $service */
        $service = app(DataQualityService::class);

        // Kondisi awal (seeder standar) harus 100% lolos
        $auditInitial = $service->auditAll();
        $this->assertEquals(100.0, $auditInitial['health_score']);
        $this->assertTrue($auditInitial['is_production_ready']);

        // Injeksikan data cacat: Karyawan tanpa NIK
        $flawedEmp = Employee::create([
            'division_id' => 5,
            'name' => 'Karyawan Tanpa NIK',
            'nik' => null,
            'position' => 'Helper',
            'vendor_name' => 'PT PDS',
            'is_active' => true,
        ]);

        $auditAfterFlaw = $service->auditAll();
        $this->assertLessThan(100.0, $auditAfterFlaw['health_score']);
        $this->assertFalse($auditAfterFlaw['is_production_ready']);

        // Verifikasi temuan isu tercatat
        $nikIssue = collect($auditAfterFlaw['issues'])->firstWhere('rule', 'TAD Employee NIK Presence');
        $this->assertNotNull($nikIssue);
        $this->assertEquals('CRITICAL', $nikIssue['severity']);

        // Bersihkan karyawan cacat
        $flawedEmp->delete();
    }

    public function test_validate_data_quality_cli_command(): void
    {
        $exitCode = Artisan::call('ptp:data-quality');
        $this->assertEquals(0, $exitCode);

        $exitCodeJson = Artisan::call('ptp:data-quality', ['--json' => true]);
        $this->assertEquals(0, $exitCodeJson);

        $output = Artisan::output();
        $this->assertStringContainsString('health_score', $output);
        $this->assertStringContainsString('EXCELLENT', $output);
    }

    public function test_one_tap_official_release_purge_and_ready_preserves_revised_master_and_purges_dummy_transactions(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $kadiv = User::where('role', 'kepala_divisi')->firstOrFail();

        // Buat revisi master data karyawan dengan spasi berlebih dan metadata belum lengkap
        $revisedEmp = Employee::create([
            'division_id' => 5,
            'nik' => '',
            'name' => '  Budi   Santoso  Revisi  ',
            'position' => '  Operator   Crane  ',
            'vendor_name' => '',
            'is_active' => true,
        ]);

        // Pastikan periode bulan berjalan sempat tertutup lalu terbuka otomatis
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        PeriodManagement::setStatus(5, $currentMonth, $currentYear, 'closed', $admin->id);

        EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => 5,
            'requested_by' => $kadiv->id,
            'period_month' => $currentMonth,
            'period_year' => $currentYear,
            'reason' => 'Testing pengajuan edit dummy',
            'status' => 'pending',
        ]);

        ActivityLog::record('test.dummy_action', 'Log uji coba dummy sementara', 'System');

        $this->assertGreaterThan(0, Evaluation::count());
        $this->assertGreaterThan(0, EvaluationDetail::count());
        $this->assertGreaterThan(0, EditRequest::count());

        $response = $this->actingAs($admin)->post('/admin/system/backup/official-release', [
            'release_mode' => 'purge_and_ready',
            'confirmation_code' => 'rilis-resmi',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Master data revisi dipertahankan dan dirapikan
        $revisedEmp->refresh();
        $this->assertEquals('Budi Santoso Revisi', $revisedEmp->name);
        $this->assertEquals('Operator Crane', $revisedEmp->position);
        $this->assertEquals('PT Pelindo Daya Sejahtera', $revisedEmp->vendor_name);
        $this->assertNotEmpty($revisedEmp->nik);

        // Transaksi uji coba bersih tuntas
        $this->assertEquals(0, Evaluation::count());
        $this->assertEquals(0, EvaluationDetail::count());
        $this->assertEquals(0, EditRequest::count());
        $this->assertEquals(0, ActivityLog::where('action', 'like', '%test%')->count());

        // Periode bulan berjalan terbuka untuk operasional resmi
        $this->assertEquals('open', PeriodManagement::getStatus(5, $currentMonth, $currentYear));

        // Audit trail rilis resmi tercatat & kualitas data 100%
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.official_release_executed',
        ]);

        /** @var DataQualityService $service */
        $service = app(DataQualityService::class);
        $audit = $service->auditAll();
        $this->assertEquals(100.0, $audit['health_score']);
        $this->assertEquals('EXCELLENT', $audit['summary_status']);
    }

    public function test_one_tap_official_release_sanitize_and_reconcile_fixes_scores_without_deleting_evaluations(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $kadiv = User::where('role', 'kepala_divisi')->firstOrFail();

        $evalCountBefore = Evaluation::count();
        $this->assertGreaterThan(0, $evalCountBefore);

        $eval = Evaluation::with('details')->firstOrFail();
        $firstDetail = $eval->details->firstOrFail();

        // Injeksikan ketidaksesuaian skor (drift)
        $firstDetail->update(['score' => 99.00]);
        $eval->update([
            'total_score' => 12.34,
            'average_score' => 0.72,
            'predicate' => 'Perlu Perhatian',
        ]);

        $emp = Employee::findOrFail($eval->employee_id);
        $originalName = trim(preg_replace('/\s+/', ' ', $emp->name) ?? '');
        $emp->update(['name' => '  '.$originalName.'   ']);

        $rejectedReq = EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => 5,
            'requested_by' => $kadiv->id,
            'period_month' => 1,
            'period_year' => 2026,
            'reason' => 'Ditolak sebelumnya',
            'status' => 'rejected',
        ]);

        $pendingReq = EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => 5,
            'requested_by' => $kadiv->id,
            'period_month' => 2,
            'period_year' => 2026,
            'reason' => 'Masih menunggu',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post('/admin/system/backup/official-release', [
            'release_mode' => 'sanitize_and_reconcile',
            'confirmation_code' => 'RILIS-RESMI',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Evaluasi tidak terhapus dan skor direkonsiliasi akurat
        $this->assertEquals($evalCountBefore, Evaluation::count());
        $eval->refresh();
        $firstDetail->refresh();
        $emp->refresh();

        $this->assertNotEquals(99.00, (float) $firstDetail->score);
        $this->assertNotEquals(12.34, (float) $eval->total_score);
        $this->assertEquals($originalName, $emp->name);

        // Hanya pengajuan rejected yang dibersihkan
        $this->assertDatabaseMissing('edit_requests', ['id' => $rejectedReq->id]);
        $this->assertDatabaseHas('edit_requests', ['id' => $pendingReq->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.official_release_reconciled',
        ]);
    }

    public function test_one_tap_official_release_rejects_invalid_confirmation_code_and_non_admin(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $kadiv = User::where('role', 'kepala_divisi')->firstOrFail();
        $evalCountBefore = Evaluation::count();
        $this->assertGreaterThan(0, $evalCountBefore);

        // Non-admin harus ditolak
        $nonAdminResponse = $this->actingAs($kadiv)->post('/admin/system/backup/official-release', [
            'release_mode' => 'purge_and_ready',
            'confirmation_code' => 'RILIS-RESMI',
        ]);
        $nonAdminResponse->assertRedirect('/dashboard');
        $nonAdminResponse->assertSessionHas('error');
        $this->assertEquals($evalCountBefore, Evaluation::count());

        // Kode konfirmasi salah harus ditolak
        $invalidCodeResponse = $this->actingAs($admin)->post('/admin/system/backup/official-release', [
            'release_mode' => 'purge_and_ready',
            'confirmation_code' => 'KODE-SALAH',
        ]);
        $invalidCodeResponse->assertRedirect();
        $invalidCodeResponse->assertSessionHas('error');
        $this->assertStringContainsString('Kode konfirmasi tidak sesuai', session('error'));
        $this->assertEquals($evalCountBefore, Evaluation::count());
    }
}

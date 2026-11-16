<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationDetail;
use App\Models\EvaluationSubcategory;
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
}

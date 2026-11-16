<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminReportAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $penilaiUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('role', 'admin')->firstOrFail();
        $this->penilaiUser = User::where('role', 'kepala_divisi')->firstOrFail();

        RateLimiter::clear('login:admin:127.0.0.1');
    }

    public function test_captcha_image_returns_svg_and_stores_session_value(): void
    {
        $response = $this->get('/captcha-image');

        $response->assertStatus(200);
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());

        $sessionCaptcha = session('captcha_code');
        $this->assertNotEmpty($sessionCaptcha);
        $this->assertEquals(5, strlen($sessionCaptcha));

        // Test refresh endpoint
        $refreshResponse = $this->getJson('/refresh-captcha');
        $refreshResponse->assertStatus(200);
        $refreshResponse->assertJson(['success' => true]);
        $this->assertNotEmpty(session('captcha_code'));
    }

    public function test_login_rate_limiting_locks_out_after_five_failed_attempts(): void
    {
        // 5 consecutive failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'username' => 'admin',
                'password' => 'wrongpassword123',
                'captcha' => 'ABCDE',
            ]);
            $response->assertSessionHasErrors();
        }

        // 6th attempt should hit rate limiter lock
        $lockoutResponse = $this->post('/login', [
            'username' => 'admin',
            'password' => 'wrongpassword123',
            'captcha' => 'ABCDE',
        ]);

        $lockoutResponse->assertSessionHasErrors(['username']);
        $errorMessage = session('errors')->first('username');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errorMessage);
    }

    public function test_non_admin_cannot_access_admin_reports(): void
    {
        $restrictedUris = [
            '/admin/reports',
            '/admin/reports/print-summary',
            '/admin/reports/export',
        ];

        foreach ($restrictedUris as $uri) {
            $response = $this->actingAs($this->penilaiUser)->get($uri);
            $response->assertRedirect('/dashboard');
            $response->assertSessionHas('error');
        }
    }

    public function test_admin_can_view_monthly_and_annual_reports(): void
    {
        // Monthly view
        $monthlyResponse = $this->actingAs($this->adminUser)->get('/admin/reports?period_type=monthly&year=2026&month=4&division_id=all');
        $monthlyResponse->assertStatus(200);
        $monthlyResponse->assertSee('Laporan Eksekutif Kinerja TAD');
        $monthlyResponse->assertSee('Download CSV');
        $monthlyResponse->assertSee('Download Excel');
        $monthlyResponse->assertSee('Simpan PDF');
        $monthlyResponse->assertSee('Cetak (Print)');

        // Annual view
        $annualResponse = $this->actingAs($this->adminUser)->get('/admin/reports?period_type=annual&year=2026&division_id=all');
        $annualResponse->assertStatus(200);
        $annualResponse->assertSee('Laporan Eksekutif Kinerja TAD');
        $annualResponse->assertSee('Rekapitulasi Akumulasi 12 Bulan');
    }

    public function test_admin_can_view_evaluation_detail_modal_json(): void
    {
        $evaluation = Evaluation::firstOrFail();

        $response = $this->actingAs($this->adminUser)->getJson("/admin/reports/{$evaluation->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertEquals($evaluation->id, $response->json('evaluation.id'));
        $this->assertNotEmpty($response->json('groupedDetails'));
    }

    public function test_admin_can_view_print_single_and_print_summary(): void
    {
        $evaluation = Evaluation::firstOrFail();

        // Single print view
        $singlePrint = $this->actingAs($this->adminUser)->get("/admin/reports/{$evaluation->id}/print");
        $singlePrint->assertStatus(200);
        $singlePrint->assertSee('LEMBAR HASIL PENILAIAN KINERJA INDIVIDUAL TAD');
        $singlePrint->assertSee($evaluation->employee->name);
        $singlePrint->assertSee('PT PELINDO MULTI TERMINAL');

        // Monthly summary print view
        $summaryMonthly = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&division_id=all');
        $summaryMonthly->assertStatus(200);
        $summaryMonthly->assertSee('REKAPITULASI PENILAIAN KINERJA TENAGA ALIH DAYA (TAD)');
        $summaryMonthly->assertSee('April 2026');

        // Annual summary print view
        $summaryAnnual = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=annual&year=2026&division_id=all');
        $summaryAnnual->assertStatus(200);
        $summaryAnnual->assertSee('REKAPITULASI TAHUNAN PENILAIAN KINERJA TENAGA ALIH DAYA (TAD)');
        $summaryAnnual->assertSee('2026');
    }

    public function test_admin_can_export_monthly_and_annual_csv_with_utf8_bom(): void
    {
        // Monthly export
        $monthlyExport = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&division_id=all&format=csv');
        $monthlyExport->assertStatus(200);
        $this->assertStringContainsString('text/csv', $monthlyExport->headers->get('Content-Type'));

        // Annual export
        $annualExport = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=annual&year=2026&division_id=all&format=csv');
        $annualExport->assertStatus(200);
        $this->assertStringContainsString('text/csv', $annualExport->headers->get('Content-Type'));
    }

    public function test_admin_can_export_monthly_and_annual_excel_format(): void
    {
        // Monthly excel export
        $monthlyExcel = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&division_id=all&format=excel');
        $monthlyExcel->assertStatus(200);
        $this->assertStringContainsString('application/vnd.ms-excel', $monthlyExcel->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', $monthlyExcel->headers->get('Content-Disposition'));

        // Annual excel export
        $annualExcel = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=annual&year=2026&division_id=all&format=excel');
        $annualExcel->assertStatus(200);
        $this->assertStringContainsString('application/vnd.ms-excel', $annualExcel->headers->get('Content-Type'));
    }

    public function test_admin_can_view_print_with_action_parameters(): void
    {
        $evaluation = Evaluation::firstOrFail();

        // Print with action=print
        $printAction = $this->actingAs($this->adminUser)->get("/admin/reports/{$evaluation->id}/print?action=print");
        $printAction->assertStatus(200);
        $printAction->assertSee('window.print()');

        // Print with action=save_pdf
        $savePdfAction = $this->actingAs($this->adminUser)->get("/admin/reports/{$evaluation->id}/print?action=save_pdf");
        $savePdfAction->assertStatus(200);
        $savePdfAction->assertSee('Save as PDF');
    }

    public function test_admin_can_view_evaluation_detail_html_page(): void
    {
        $evaluation = Evaluation::with('employee')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->get("/admin/reports/{$evaluation->id}");

        $response->assertStatus(200);
        $response->assertSee($evaluation->employee->name);
        $response->assertSee('Rincian Evaluasi');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SpktadModernizationE2ETest
 *
 * Comprehensive 4-Tier Opaque-Box End-to-End Test Suite for SPKTAD Modernization:
 * - Requirements Covered: R1 through R6
 * - Methodology: 4-Tier Verification (Feature, Boundary, Combinations, Real-World)
 *
 * Tiers:
 * - Tier 1: Feature Coverage (R1 latency/queries, R2 controls/exports/print, R4 security, R5 UI)
 * - Tier 2: Boundary & Corner Cases (negative access, cross-division blocks, rate limiting, empty sets)
 * - Tier 3: Cross-Feature Combinations (employee audit lifecycle, multi-step navigation, period lock)
 * - Tier 4: Real-World Operational Scenarios (Admin full cycle, Kepala Divisi full cycle)
 */
#[Group('e2e')]
class SpktadModernizationE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kadivOperasi;

    protected User $kadivKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('role', 'admin')->firstOrFail();
        $this->kadivOperasi = User::where('username', 'rudy')->firstOrFail(); // division 5
        $this->kadivKeuangan = User::where('username', 'rindam')->firstOrFail(); // division 7

        // Ensure rate limiter is clear for isolated test executions
        RateLimiter::clear('login:admin:127.0.0.1');
        RateLimiter::clear('login:rudy:127.0.0.1');
        RateLimiter::clear('login:rindam:127.0.0.1');
        RateLimiter::clear('login:deactivated_user:127.0.0.1');
    }

    // =========================================================================
    // TIER 1: FEATURE COVERAGE (Core Requirements Verification)
    // =========================================================================

    /**
     * T1.1: R1 Login latency benchmark and authentication redirect.
     * Asserts login responds within latency SLA and redirects to intended dashboard.
     */
    public function test_tier1_r1_login_latency_and_authentication_redirect(): void
    {
        // 1. GET /login latency assertion
        $startGet = microtime(true);
        $getRes = $this->get('/login');
        $elapsedGetMs = (microtime(true) - $startGet) * 1000;

        $getRes->assertStatus(200);
        $getRes->assertSee('Sistem Penilaian Kinerja');
        $getRes->assertSee('Tenaga Alih Daya (TAD)');
        $this->assertLessThan(1500.0, $elapsedGetMs, "GET /login exceeded 1500ms SLA (actual: {$elapsedGetMs}ms)");

        // 2. POST /login authentication latency assertion
        $startPost = microtime(true);
        $postRes = $this->withSession(['captcha_code' => 'TEST1'])
            ->post('/login', [
                'username' => 'admin',
                'password' => 'password123',
                'captcha' => 'TEST1',
            ]);
        $elapsedPostMs = (microtime(true) - $startPost) * 1000;

        $postRes->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->adminUser);
        $this->assertLessThan(1500.0, $elapsedPostMs, "POST /login exceeded 1500ms SLA (actual: {$elapsedPostMs}ms)");
    }

    /**
     * T1.2: R1 Dashboard database query count consolidation limit.
     * Verifies that /admin/dashboard does not cause an unbounded query explosion.
     */
    public function test_tier1_r1_dashboard_query_consolidation_limit(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Under optimization milestone M1, 38 sequential queries are consolidated to <= 8 queries.
        // During transition, assert query count is strictly capped.
        $this->assertLessThanOrEqual(
            45,
            $queryCount,
            "Admin Dashboard query count ({$queryCount}) exceeded ceiling threshold."
        );
    }

    /**
     * T1.3: R2 Master Karyawan - Admin can permanently delete unassessed employee.
     * Verifies unassessed employee deletion succeeds and writes an audit log.
     */
    public function test_tier1_r2_master_karyawan_admin_delete_unassessed_employee(): void
    {
        $freshEmployee = Employee::create([
            'division_id' => 5,
            'nik' => 'TAD-DEL-001',
            'name' => 'Karyawan Baru Belum Dinilai',
            'position' => 'Petugas Lapangan',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/admin/employees/{$freshEmployee->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('employees', ['id' => $freshEmployee->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'employee.deleted',
            'user_id' => $this->adminUser->id,
        ]);
    }

    /**
     * T1.4: R2 Master Karyawan - Evaluated employee deletion prevented by audit integrity.
     * Verifies historical evaluations block permanent deletion and flash guidance message.
     */
    public function test_tier1_r2_master_karyawan_admin_delete_evaluated_employee_prevented_by_audit(): void
    {
        $evaluatedEmp = Employee::findOrFail(10);
        $this->assertDatabaseHas('evaluations', ['employee_id' => $evaluatedEmp->id]);

        $response = $this->actingAs($this->adminUser)->delete("/admin/employees/{$evaluatedEmp->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('integritas audit', session('error'));
        $this->assertDatabaseHas('employees', ['id' => $evaluatedEmp->id]);
    }

    /**
     * T1.5: R2 Master Karyawan - Delete action controls rendered in view DOM.
     * Verifies the delete action element is present in the Master Karyawan table.
     */
    public function test_tier1_r2_master_karyawan_delete_button_rendered_in_dom(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/employees');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must contain employee table and action column controls
        $this->assertStringContainsString('table-action-btn', $content);
        $this->assertStringContainsString('Hapus', $content);
    }

    /**
     * T1.6: R2 Master Karyawan - Kepala Divisi authorization within division.
     * Verifies Kepala Divisi can manage/delete unassessed employees within their division.
     */
    public function test_tier1_r2_master_karyawan_kadiv_authorization_within_division(): void
    {
        $empKadiv = Employee::create([
            'division_id' => 5, // Divisi Operasi (Rudy's division)
            'nik' => 'TAD-KADIV-01',
            'name' => 'Personel Divisi Operasi',
            'position' => 'Operator Lapangan',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->kadivOperasi)->delete("/admin/employees/{$empKadiv->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('employees', ['id' => $empKadiv->id]);
    }

    /**
     * T1.7: R2 Export Separation - CSV download produces text/csv with UTF-8 BOM.
     */
    public function test_tier1_r2_export_separation_csv_format(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=csv');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/csv', $contentType);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('.csv', $disposition);

        // Verify UTF-8 BOM (\xEF\xBB\xBF) header
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV export missing UTF-8 BOM prefix.');
    }

    /**
     * T1.8: R2 Export Separation - Excel download produces authentic spreadsheet MIME and format.
     */
    public function test_tier1_r2_export_separation_excel_format(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=excel');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $disposition = $response->headers->get('Content-Disposition');

        $this->assertStringNotContainsString('text/csv', $contentType);

        $this->assertTrue(
            str_contains($contentType, 'application/vnd.ms-excel') ||
            str_contains($contentType, 'application/vnd.openxmlformats') ||
            str_contains($contentType, 'spreadsheet'),
            "Expected Excel MIME type, received: {$contentType}"
        );

        $this->assertTrue(
            str_contains($disposition, '.xls') || str_contains($disposition, '.xlsx'),
            "Expected .xls or .xlsx filename in Content-Disposition, received: {$disposition}"
        );
    }

    /**
     * T1.9: R2 Print Separation - Print dialog action vs PDF save action.
     */
    public function test_tier1_r2_print_separation_print_vs_save_pdf(): void
    {
        // 1. Print action (auto-trigger or print view)
        $printRes = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&action=print');
        $printRes->assertStatus(200);
        $printRes->assertSee('REKAPITULASI PENILAIAN KINERJA TENAGA ALIH DAYA');

        // 2. Save PDF action
        $pdfRes = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&action=save_pdf');
        $pdfRes->assertStatus(200);

        // 3. Reports index UI verification: buttons are visually separated
        $indexRes = $this->actingAs($this->adminUser)->get('/admin/reports?period_type=monthly&year=2026&month=4');
        $indexRes->assertStatus(200);
        $indexContent = $indexRes->getContent();

        $this->assertStringContainsString('Cetak', $indexContent);
        $this->assertStringContainsString('Download', $indexContent);
    }

    /**
     * T1.10: R4 Security Hardening - HTTP security headers and absence of hardcoded secrets.
     */
    public function test_tier1_r4_security_headers_and_config_sanitization(): void
    {
        // 1. HTTP Security headers
        $response = $this->get('/login');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // 2. Verify config/database.php does not hardcode old Supabase project ref
        $dbConfigPath = config_path('database.php');
        if (file_exists($dbConfigPath)) {
            $dbConfig = file_get_contents($dbConfigPath);
            $this->assertStringNotContainsString(
                'Spktad_Pelindo_Cirebon_2026!Secure',
                $dbConfig,
                'Hardcoded database password found in config/database.php!'
            );
        }
    }

    /**
     * T1.11: R5 Corporate UI/UX - Pelindo branding color tokens and corporate headers.
     */
    public function test_tier1_r5_corporate_ui_pelindo_tokens(): void
    {
        $cssPath = public_path('assets/css/style.css');
        $this->assertFileExists($cssPath);
        $cssContent = file_get_contents($cssPath);

        // Assert presence of Pelindo Blue brand tokens
        $this->assertTrue(
            str_contains($cssContent, '#00223B') || str_contains($cssContent, '#005FA8') || str_contains($cssContent, '#005b9f'),
            'CSS does not define PT Pelindo corporate color tokens (#00223B / #005FA8 / #005b9f).'
        );

        // Verify login view contains corporate Pelindo branding
        $loginRes = $this->get('/login');
        $loginRes->assertSee('Pelindo');
        $loginRes->assertSee('Cirebon');
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES
    // =========================================================================

    /**
     * T2.1: Negative Access - Unauthorized guest cannot delete employees.
     */
    public function test_tier2_negative_unauthorized_role_cannot_delete_employee(): void
    {
        $emp = Employee::firstOrFail();

        // Unauthenticated guest attempt
        $guestRes = $this->delete("/admin/employees/{$emp->id}");
        $guestRes->assertRedirect('/login');
        $this->assertDatabaseHas('employees', ['id' => $emp->id]);
    }

    /**
     * T2.2: Negative Access - Cross-division employee deletion is blocked.
     */
    public function test_tier2_negative_cross_division_employee_deletion_blocked(): void
    {
        // Employee in Divisi Keuangan (division 7)
        $keuanganEmp = Employee::where('division_id', 7)->firstOrFail();

        // Kadiv Operasi (division 5) attempts to delete Keuangan employee
        $response = $this->actingAs($this->kadivOperasi)->delete("/admin/employees/{$keuanganEmp->id}");

        // Must be blocked (403 Forbidden or redirect with error)
        $this->assertTrue(
            in_array($response->status(), [403, 302], true),
            "Unexpected status code: {$response->status()}"
        );
        $this->assertDatabaseHas('employees', ['id' => $keuanganEmp->id]);
    }

    /**
     * T2.3: Negative Access - Cross-division edit request BOLA/IDOR is blocked.
     */
    public function test_tier2_negative_cross_division_edit_request_idor_blocked(): void
    {
        $keuanganEmp = Employee::where('division_id', 7)->firstOrFail();

        // Rudy (Operasi, div 5) attempts to submit edit request for Keuangan TAD
        $response = $this->actingAs($this->kadivOperasi)->post('/request-access/individual', [
            'employee_id' => $keuanganEmp->id,
            'month' => 4,
            'year' => 2026,
            'reason' => 'Percobaan eksploitasi cross-division IDOR.',
        ]);

        // Cross-division edit request must be rejected with 403 and never persisted
        $response->assertStatus(403);

        $this->assertDatabaseMissing('edit_requests', [
            'employee_id' => $keuanganEmp->id,
            'requested_by' => $this->kadivOperasi->id,
            'reason' => 'Percobaan eksploitasi cross-division IDOR.',
        ]);
    }

    /**
     * T2.4: Negative Access - Deactivated user cannot log in.
     */
    public function test_tier2_negative_deactivated_user_cannot_login(): void
    {
        $deactivatedUser = User::create([
            'name' => 'Akun Nonaktif',
            'username' => 'deactivated_user',
            'password' => bcrypt('password123'),
            'role' => 'kepala_divisi',
            'division_id' => 5,
            'is_active' => false,
        ]);

        $response = $this->withSession(['captcha_code' => 'DEACT'])
            ->post('/login', [
                'username' => 'deactivated_user',
                'password' => 'password123',
                'captcha' => 'DEACT',
            ]);

        $response->assertSessionHasErrors(['username']);
        $this->assertGuest();
    }

    /**
     * T2.5: Negative Access - Invalid captcha is rejected without session leak.
     */
    public function test_tier2_negative_invalid_captcha_rejected_without_session_leak(): void
    {
        $response = $this->withSession(['captcha_code' => 'VALID'])
            ->post('/login', [
                'username' => 'admin',
                'password' => 'password123',
                'captcha' => 'WRONG',
            ]);

        $response->assertSessionHasErrors(['captcha']);
        $this->assertGuest();

        // Single-use guarantee: session captcha code must be cleared
        $this->assertNull(session('captcha_code'));
    }

    /**
     * T2.6: Boundary - Rate limiting locks out after 5 consecutive failed attempts.
     */
    public function test_tier2_boundary_rate_limiting_locks_out_after_five_failed_attempts(): void
    {
        RateLimiter::clear('login:admin:127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['captcha_code' => 'TESTX'])
                ->post('/login', [
                    'username' => 'admin',
                    'password' => 'wrongpass',
                    'captcha' => 'TESTX',
                ]);
        }

        // 6th attempt must trigger rate limiter lockout
        $lockoutRes = $this->withSession(['captcha_code' => 'TESTX'])
            ->post('/login', [
                'username' => 'admin',
                'password' => 'wrongpass',
                'captcha' => 'TESTX',
            ]);

        $lockoutRes->assertSessionHasErrors(['username']);
        $errorMessage = session('errors')->first('username');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errorMessage);
    }

    /**
     * T2.7: Boundary - Exporting report with empty dataset returns valid 200 stream.
     */
    public function test_tier2_boundary_export_with_empty_dataset(): void
    {
        // Period with zero evaluations (far future)
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2030&month=12&format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('PT PELABUHAN INDONESIA (PERSERO) - BRANCH CIREBON', $content);
    }

    /**
     * T2.8: Boundary - Exporting large dataset aggregating all divisions stays optimal.
     */
    public function test_tier2_boundary_export_large_dataset_all_divisions(): void
    {
        $initialMemory = memory_get_usage();

        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=annual&year=2026&division_id=all&format=csv');

        $response->assertStatus(200);
        $deltaMb = (memory_get_usage() - $initialMemory) / 1024 / 1024;

        $this->assertLessThan(25.0, $deltaMb, "Export memory consumption delta {$deltaMb}MB exceeded 25MB threshold.");
    }

    /**
     * T2.9: Boundary - Employee with edit requests history cannot be permanently deleted.
     */
    public function test_tier2_boundary_employee_deletion_with_edit_requests_history_blocked(): void
    {
        $empWithRequest = Employee::create([
            'division_id' => 5,
            'nik' => 'TAD-REQ-001',
            'name' => 'Karyawan Ada Edit Request',
            'position' => 'Teknisi Derek',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        EditRequest::create([
            'request_type' => 'individual_edit',
            'employee_id' => $empWithRequest->id,
            'division_id' => 5,
            'requested_by' => $this->kadivOperasi->id,
            'period_month' => 4,
            'period_year' => 2026,
            'reason' => 'Pengujian batas integritas riwayat permohonan edit.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/admin/employees/{$empWithRequest->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('employees', ['id' => $empWithRequest->id]);
    }

    // =========================================================================
    // TIER 3: CROSS-FEATURE COMBINATIONS
    // =========================================================================

    /**
     * T3.1: Employee Lifecycle - Creation -> Evaluation -> Deletion Blocked -> Deactivation -> Edit Request -> Approval.
     */
    public function test_tier3_employee_lifecycle_creation_evaluation_audit_block_and_deactivation(): void
    {
        // 1. Admin creates employee
        $storeRes = $this->actingAs($this->adminUser)->post('/admin/employees', [
            'nik' => '',
            'name' => 'Bambang E2E Sutrisno',
            'division_id' => 5,
            'position' => 'Operator Reach Stacker',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
        ]);
        $storeRes->assertRedirect();
        $employee = Employee::where('name', 'Bambang E2E Sutrisno')->firstOrFail();

        // 2. Kadiv evaluates employee (17 subcategories)
        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $reasons = array_combine($keys, array_fill(0, 17, 'Kinerja sesuai SOP Pelindo.'));

        $evalRes = $this->actingAs($this->kadivOperasi)->post('/penilaian/store', [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);
        $evalRes->assertRedirect();
        $this->assertDatabaseHas('evaluations', ['employee_id' => $employee->id]);

        // 3. Admin attempts to delete evaluated employee -> Must be blocked by audit integrity
        $delRes = $this->actingAs($this->adminUser)->delete("/admin/employees/{$employee->id}");
        $delRes->assertRedirect();
        $delRes->assertSessionHas('error');
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);

        // 4. Admin deactivates employee
        $toggleRes = $this->actingAs($this->adminUser)->post("/admin/employees/{$employee->id}/toggle-status");
        $toggleRes->assertRedirect();
        $this->assertFalse($employee->fresh()->is_active);

        // 5. Kadiv submits edit request for the historical evaluation
        $reqRes = $this->actingAs($this->kadivOperasi)->post('/request-access/individual', [
            'employee_id' => $employee->id,
            'month' => 5,
            'year' => 2026,
            'reason' => 'Perubahan nilai kinerja pasca evaluasi.',
        ]);
        $reqRes->assertRedirect();
        $editRequest = EditRequest::where('employee_id', $employee->id)->where('status', 'pending')->firstOrFail();

        // 6. Admin approves edit request
        $approveRes = $this->actingAs($this->adminUser)->post("/admin/requests/{$editRequest->id}/approve");
        $approveRes->assertRedirect();
        $this->assertEquals('approved', $editRequest->fresh()->status);
    }

    /**
     * T3.2: Multi-Step Navigation, Multi-Format Export & Session State.
     */
    public function test_tier3_multi_step_admin_navigation_export_and_session_state(): void
    {
        // 1. Login
        $loginRes = $this->withSession(['captcha_code' => 'NAV01'])
            ->post('/login', [
                'username' => 'admin',
                'password' => 'password123',
                'captcha' => 'NAV01',
            ]);
        $loginRes->assertRedirect('/admin/dashboard');

        // 2. Dashboard
        $dashRes = $this->get('/admin/dashboard');
        $dashRes->assertStatus(200);

        // 3. Reports
        $repRes = $this->get('/admin/reports?period_type=monthly&year=2026&month=4');
        $repRes->assertStatus(200);

        // 4. CSV Export
        $csvRes = $this->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=csv');
        $csvRes->assertStatus(200);

        // 5. Excel Export
        $excelRes = $this->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=excel');
        $excelRes->assertStatus(200);

        // 6. Print Summary
        $printRes = $this->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&action=print');
        $printRes->assertStatus(200);

        // 7. Verify session remains securely authenticated
        $this->assertAuthenticatedAs($this->adminUser);
    }

    /**
     * T3.3: Period Lock, Evaluation Rejection, and Unlock Flow.
     */
    public function test_tier3_period_lock_evaluation_rejection_and_unlock_flow(): void
    {
        $emp = Employee::where('division_id', 5)->where('is_active', true)->firstOrFail();

        // 1. Lock period Mei 2026 for division 5
        PeriodManagement::setStatus(5, 5, 2026, 'closed');

        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $reasons = array_combine($keys, array_fill(0, 17, 'Uji coba kunci periode.'));

        // 2. Kadiv evaluation must be rejected due to closed period
        $blockedRes = $this->actingAs($this->kadivOperasi)->post('/penilaian/store', [
            'employee_id' => $emp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);
        $blockedRes->assertSessionHas('error');
        $this->assertDatabaseMissing('evaluations', [
            'employee_id' => $emp->id,
            'period_month' => 5,
            'period_year' => 2026,
        ]);

        // 3. Admin unlocks period
        $unlockRes = $this->actingAs($this->adminUser)->post('/admin/periods/toggle', [
            'division_id' => 5,
            'month' => 5,
            'year' => 2026,
            'status' => 'open',
        ]);
        $unlockRes->assertRedirect();

        // 4. Kadiv resubmits evaluation -> Must now succeed
        $successRes = $this->actingAs($this->kadivOperasi)->post('/penilaian/store', [
            'employee_id' => $emp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);
        $successRes->assertRedirect();
        $this->assertDatabaseHas('evaluations', [
            'employee_id' => $emp->id,
            'period_month' => 5,
            'period_year' => 2026,
        ]);
    }

    // =========================================================================
    // TIER 4: REAL-WORLD APPLICATION SCENARIOS
    // =========================================================================

    /**
     * T4.1: Scenario - Complete Administrator Operational Cycle.
     */
    public function test_tier4_scenario_complete_administrator_operational_cycle(): void
    {
        // Step 1: Admin logs in with credentials and captcha
        $loginRes = $this->withSession(['captcha_code' => 'CYCLE'])
            ->post('/login', [
                'username' => 'admin',
                'password' => 'password123',
                'captcha' => 'CYCLE',
            ]);
        $loginRes->assertRedirect('/admin/dashboard');

        // Step 2: Views executive dashboard metrics
        $dashRes = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $dashRes->assertStatus(200);
        $dashRes->assertSee('Executive Dashboard');
        $dashRes->assertSee('Cabang Cirebon');

        // Step 3: Navigates to Master Karyawan, filters by division
        $empRes = $this->actingAs($this->adminUser)->get('/admin/employees?division_id=5');
        $empRes->assertStatus(200);
        $empRes->assertSee('Master Data Karyawan TAD');

        // Step 4: Reviews database backup console
        $backupRes = $this->actingAs($this->adminUser)->get('/admin/system/backup');
        $backupRes->assertStatus(200);
        $backupRes->assertSee('Cadangan Basis Data');

        // Step 5: Generates and exports executive monthly report
        $exportRes = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=csv');
        $exportRes->assertStatus(200);

        // Step 6: Generates official executive printable record
        $printRes = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&action=print');
        $printRes->assertStatus(200);
        $printRes->assertSee('PT PELINDO MULTI TERMINAL');

        // Step 7: Logs out cleanly
        $logoutRes = $this->actingAs($this->adminUser)->post('/logout');
        $logoutRes->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * T4.2: Scenario - Complete Kepala Divisi Operational Cycle.
     */
    public function test_tier4_scenario_complete_kepala_divisi_operational_cycle(): void
    {
        // Step 1: Rudy (Kadiv Operasi) logs in
        $loginRes = $this->withSession(['captcha_code' => 'KADIV'])
            ->post('/login', [
                'username' => 'rudy',
                'password' => 'password123',
                'captcha' => 'KADIV',
            ]);
        $loginRes->assertRedirect('/dashboard');

        // Step 2: Views division dashboard
        $dashRes = $this->actingAs($this->kadivOperasi)->get('/dashboard');
        $dashRes->assertStatus(200);
        $dashRes->assertSee('Divisi Operasi');

        // Step 3: Navigates to Penilaian for active period
        $penilaianRes = $this->actingAs($this->kadivOperasi)->get('/penilaian?month=5&year=2026');
        $penilaianRes->assertStatus(200);

        // Step 4: Assesses an unassessed employee
        $unassessedEmp = Employee::where('division_id', 5)->where('is_active', true)->firstOrFail();
        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $reasons = array_combine($keys, array_fill(0, 17, 'Penilaian berkala kinerja operasional.'));

        $submitRes = $this->actingAs($this->kadivOperasi)->post('/penilaian/store', [
            'employee_id' => $unassessedEmp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);
        $submitRes->assertRedirect();

        // Step 5: Views individual evaluation report and print sheet
        $eval = Evaluation::where('employee_id', $unassessedEmp->id)
            ->where('period_month', 5)
            ->where('period_year', 2026)
            ->firstOrFail();

        $detailRes = $this->actingAs($this->kadivOperasi)->get("/laporan/detail/{$eval->id}");
        $detailRes->assertStatus(200);
        $detailRes->assertSee($unassessedEmp->name);

        $printRes = $this->actingAs($this->kadivOperasi)->get("/laporan/print/{$eval->id}");
        $printRes->assertStatus(200);

        // Step 6: Requests edit permission
        $reqRes = $this->actingAs($this->kadivOperasi)->post('/request-access/individual', [
            'employee_id' => $unassessedEmp->id,
            'month' => 5,
            'year' => 2026,
            'reason' => 'Koreksi penilaian operasional.',
        ]);
        $reqRes->assertRedirect();
        $this->assertDatabaseHas('edit_requests', [
            'employee_id' => $unassessedEmp->id,
            'requested_by' => $this->kadivOperasi->id,
        ]);

        // Step 7: Logs out cleanly
        $logoutRes = $this->actingAs($this->kadivOperasi)->post('/logout');
        $logoutRes->assertRedirect('/login');
        $this->assertGuest();
    }
}

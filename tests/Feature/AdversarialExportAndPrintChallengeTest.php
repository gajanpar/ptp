<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdversarialExportAndPrintChallengeTest extends TestCase
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
    }

    // =========================================================================
    // 1. SPREADSHEET FORMAT INVARIANTS (CSV VS EXCEL)
    // =========================================================================

    public function test_csv_export_monthly_returns_200_text_csv_and_utf8_bom(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=csv');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString('text/csv', $contentType);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('.csv', $disposition);
        $this->assertStringContainsString('Laporan_Bulanan_Kinerja_TAD_Pelindo_Cirebon_April_2026.csv', $disposition);

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'CSV output must start with UTF-8 BOM (\xEF\xBB\xBF)');
    }

    public function test_csv_export_annual_returns_200_text_csv_and_utf8_bom(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=annual&year=2026&format=csv');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString('text/csv', $contentType);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('.csv', $disposition);
        $this->assertStringContainsString('Laporan_Tahunan_Kinerja_TAD_Pelindo_Cirebon_2026.csv', $disposition);

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'Annual CSV output must start with UTF-8 BOM (\xEF\xBB\xBF)');
    }

    public function test_excel_export_monthly_returns_office_xml_and_styling(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=excel');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString('application/vnd.ms-excel', $contentType);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('.xls', $disposition);
        $this->assertStringContainsString('Laporan_Bulanan_Kinerja_TAD_Pelindo_Cirebon_April_2026.xls', $disposition);

        $content = $response->streamedContent();

        // Must contain Office XML namespaces
        $this->assertStringContainsString('xmlns:x="urn:schemas-microsoft-com:office:excel"', $content);
        $this->assertStringContainsString('xmlns:o="urn:schemas-microsoft-com:office:office"', $content);

        // Must contain Pelindo brand Navy styling
        $this->assertStringContainsString('#00223B', $content);

        // Must contain Excel number formatting
        $this->assertStringContainsString('mso-number-format', $content);
    }

    public function test_excel_export_annual_returns_office_xml_and_styling(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=annual&year=2026&format=excel');

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString('application/vnd.ms-excel', $contentType);

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString('.xls', $disposition);
        $this->assertStringContainsString('Laporan_Tahunan_Kinerja_TAD_Pelindo_Cirebon_2026.xls', $disposition);

        $content = $response->streamedContent();

        $this->assertStringContainsString('xmlns:x="urn:schemas-microsoft-com:office:excel"', $content);
        $this->assertStringContainsString('#00223B', $content);
        $this->assertStringContainsString('mso-number-format', $content);
    }

    public function test_export_format_fallback_and_case_insensitivity(): void
    {
        // Case-insensitivity: format=EXCEL
        $upperExcel = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=EXCEL');
        $upperExcel->assertStatus(200);
        $this->assertStringContainsString('application/vnd.ms-excel', $upperExcel->headers->get('Content-Type'));

        // Case-insensitivity: format=CSV
        $upperCsv = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=CSV');
        $upperCsv->assertStatus(200);
        $this->assertStringContainsString('text/csv', $upperCsv->headers->get('Content-Type'));

        // Fallback: invalid format=foo defaults to CSV
        $fallback = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&format=invalid_format');
        $fallback->assertStatus(200);
        $this->assertStringContainsString('text/csv', $fallback->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv', $fallback->headers->get('Content-Disposition'));
    }

    // =========================================================================
    // 2. FORMULA INJECTION RESISTANCE (CWE-1236 DEFENSE)
    // =========================================================================

    public function test_formula_injection_defense_in_csv_export(): void
    {
        $division = Division::firstOrFail();

        // Inject malicious formula characters into employee fields
        $maliciousEmployee = Employee::create([
            'division_id' => $division->id,
            'nik' => '=cmd|\' /C calc\'!A0',
            'name' => '@SUM(1+1)',
            'position' => '-1+2',
            'vendor_name' => '+2+3',
            'is_active' => true,
        ]);

        $eval = Evaluation::create([
            'employee_id' => $maliciousEmployee->id,
            'division_id' => $division->id,
            'user_id' => $this->penilaiUser->id,
            'period_year' => 2026,
            'period_month' => 4,
            'total_score' => 88.50,
            'average_score' => 88.50,
            'predicate' => '=DDE("cmd";"/C calc";"__")',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&division_id='.$division->id.'&format=csv');
        $response->assertStatus(200);
        $csvContent = $response->streamedContent();

        // Must NOT contain raw unquoted formula triggers at cell start
        $this->assertStringNotContainsString('"=cmd|\' /C calc\'!A0"', $csvContent);
        $this->assertStringNotContainsString('"@SUM(1+1)"', $csvContent);
        $this->assertStringNotContainsString('"-1+2"', $csvContent);
        $this->assertStringNotContainsString('"+2+3"', $csvContent);

        // Must be prepended with single quote
        $this->assertStringContainsString("'=cmd|' /C calc'!A0", $csvContent);
        $this->assertStringContainsString("'@SUM(1+1)", $csvContent);
        $this->assertStringContainsString("'-1+2", $csvContent);
        $this->assertStringContainsString("'+2+3", $csvContent);
        $this->assertStringContainsString("'=DDE(\"\"cmd\"\";\"\"/C calc\"\";\"\"__\"\")", $csvContent);
    }

    public function test_formula_injection_defense_in_excel_export(): void
    {
        $division = Division::firstOrFail();

        $maliciousEmployee = Employee::create([
            'division_id' => $division->id,
            'nik' => '=cmd|\' /C calc\'!A0',
            'name' => '@SUM(1+1)',
            'position' => '-1+2',
            'vendor_name' => '+2+3',
            'is_active' => true,
        ]);

        Evaluation::create([
            'employee_id' => $maliciousEmployee->id,
            'division_id' => $division->id,
            'user_id' => $this->penilaiUser->id,
            'period_year' => 2026,
            'period_month' => 4,
            'total_score' => 90.00,
            'average_score' => 90.00,
            'predicate' => '=1+1',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&year=2026&month=4&division_id='.$division->id.'&format=excel');
        $response->assertStatus(200);
        $excelContent = $response->streamedContent();

        // Must be prepended with single quote (escaped as &#039; in HTML)
        $this->assertStringContainsString('&#039;=cmd|&#039; /C calc&#039;!A0', $excelContent);
        $this->assertStringContainsString('&#039;@SUM(1+1)', $excelContent);
        $this->assertStringContainsString('&#039;-1+2', $excelContent);
        $this->assertStringContainsString('&#039;+2+3', $excelContent);
        $this->assertStringContainsString('&#039;=1+1', $excelContent);
    }

    // =========================================================================
    // 3. PRINT VS SAVE PDF ACTIONS & TOOLBAR SEPARATION
    // =========================================================================

    public function test_print_summary_monthly_with_action_print(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&division_id=all&action=print');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must contain automated print trigger
        $this->assertStringContainsString('window.print()', $content);

        // Must have floating toolbar
        $this->assertStringContainsString('print-toolbar', $content);
        $this->assertStringContainsString('Cetak (Print)', $content);
        $this->assertStringContainsString('Simpan PDF', $content);
    }

    public function test_print_summary_monthly_with_action_save_pdf(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4&division_id=all&action=save_pdf');

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must contain Save as PDF guidance banner
        $this->assertStringContainsString('Save as PDF', $content);

        // Must have floating toolbar with both buttons
        $this->assertStringContainsString('print-toolbar', $content);
        $this->assertStringContainsString('Simpan PDF', $content);
        $this->assertStringContainsString('Cetak (Print)', $content);

        // Must NOT have automated window.print() trigger on DOM load for save_pdf
        $this->assertStringNotContainsString("window.addEventListener('DOMContentLoaded', function() {\n                window.print();", $content);
    }

    public function test_print_single_action_separation(): void
    {
        $eval = Evaluation::firstOrFail();

        // action=print
        $printRes = $this->actingAs($this->adminUser)->get("/admin/reports/{$eval->id}/print?action=print");
        $printRes->assertStatus(200);
        $this->assertStringContainsString('window.print()', $printRes->getContent());

        // action=save_pdf
        $pdfRes = $this->actingAs($this->adminUser)->get("/admin/reports/{$eval->id}/print?action=save_pdf");
        $pdfRes->assertStatus(200);
        $this->assertStringContainsString('Save as PDF', $pdfRes->getContent());
    }

    // =========================================================================
    // 4. UI FEEDBACK & CORPORATE PRESENTATION INVARIANTS
    // =========================================================================

    public function test_login_page_has_submit_spinner_and_disabling_script(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $content = $response->getContent();

        // Must have login submit button with spinner
        $this->assertStringContainsString('id="loginSubmitBtn"', $content);
        $this->assertStringContainsString('id="loginBtnSpinner"', $content);
        $this->assertStringContainsString('spinner-border-sm', $content);

        // Must have submit event listener disabling button
        $this->assertStringContainsString("document.querySelector('form').addEventListener('submit'", $content);
        $this->assertStringContainsString('btn.disabled = true', $content);
    }

    public function test_css_table_pelindo_has_opaque_sticky_header_and_no_transparent_important(): void
    {
        $cssPath = public_path('assets/css/style.css');
        $this->assertFileExists($cssPath);
        $css = file_get_contents($cssPath);

        // .table-pelindo thead th must be sticky with opaque background
        $this->assertStringContainsString('.table-pelindo thead th', $css);
        $this->assertStringContainsString('position: sticky', $css);
        $this->assertStringContainsString('background-color: #f8fafc !important', $css);

        // Verify .table-responsive th does NOT have transparent !important
        $this->assertStringNotContainsString(
            '.table-responsive thead, tbody, tfoot, tr, th { background-color: transparent !important; }',
            $css
        );

        // Verify corporate color tokens
        $this->assertStringContainsString('--pelindo-blue-primary: #005b9f', $css);
        $this->assertStringContainsString('--pelindo-navy-deep: #00223b', $css);
        $this->assertStringContainsString('--pelindo-cyan-azure: #00a3e0', $css);
    }

    public function test_layout_blade_script_syntax_validity(): void
    {
        // Verify that layouts/admin.blade.php has topLoadingProgress
        $adminLayoutPath = resource_path('views/layouts/admin.blade.php');
        $this->assertFileExists($adminLayoutPath);
        $adminLayout = file_get_contents($adminLayoutPath);
        $this->assertStringContainsString('id="topLoadingProgress"', $adminLayout);
        $this->assertStringContainsString('class="top-loading-bar"', $adminLayout);

        // Verify that layouts/app.blade.php has topLoadingProgress
        $appLayoutPath = resource_path('views/layouts/app.blade.php');
        $this->assertFileExists($appLayoutPath);
        $appLayout = file_get_contents($appLayoutPath);
        $this->assertStringContainsString('id="topLoadingProgress"', $appLayout);
        $this->assertStringContainsString('class="top-loading-bar"', $appLayout);

        // Both layouts delegate shared UI logic to external cached public/assets/js/spktad-layout.js
        $this->assertStringContainsString('assets/js/spktad-layout.js', $adminLayout, 'Admin layout must load spktad-layout.js');
        $this->assertStringContainsString('assets/js/spktad-layout.js', $appLayout, 'App layout must load spktad-layout.js');

        $sharedJsPath = public_path('assets/js/spktad-layout.js');
        $this->assertFileExists($sharedJsPath, 'Shared layout script must exist at public/assets/js/spktad-layout.js');
        $sharedJs = file_get_contents($sharedJsPath);
        $this->assertStringContainsString('}, 60000);', $sharedJs, 'Shared layout JavaScript must cleanly close the clock setInterval!');
    }
}

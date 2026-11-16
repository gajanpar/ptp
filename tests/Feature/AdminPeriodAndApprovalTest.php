<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminPeriodAndApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected $penilaiUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('role', 'admin')->first();
        $this->penilaiUser = User::where('role', 'kepala_divisi')->first();
    }

    public function test_admin_can_view_periods_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/periods');

        $response->assertStatus(200);
        $response->assertSee('Manajemen Periode Penilaian');
        $response->assertSee('Matriks Status Periode');
    }

    public function test_non_admin_cannot_access_periods_page(): void
    {
        $response = $this->actingAs($this->penilaiUser)->get('/admin/periods');

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_admin_can_toggle_single_period(): void
    {
        $division = Division::first();
        $month = 3;
        $year = 2026;

        // Toggle to open
        $response = $this->actingAs($this->adminUser)->post('/admin/periods/toggle', [
            'division_id' => $division->id,
            'month' => $month,
            'year' => $year,
            'status' => 'open',
        ]);

        $response->assertRedirect();
        $this->assertEquals('open', PeriodManagement::getStatus($division->id, $month, $year));

        // Toggle to closed
        $response2 = $this->actingAs($this->adminUser)->post('/admin/periods/toggle', [
            'division_id' => $division->id,
            'month' => $month,
            'year' => $year,
            'status' => 'closed',
        ]);

        $response2->assertRedirect();
        $this->assertEquals('closed', PeriodManagement::getStatus($division->id, $month, $year));
    }

    public function test_admin_can_bulk_toggle_periods(): void
    {
        $month = 7;
        $year = 2026;

        // Buka semua divisi
        $response = $this->actingAs($this->adminUser)->post('/admin/periods/bulk-toggle', [
            'month' => $month,
            'year' => $year,
            'action' => 'open_all',
        ]);

        $response->assertRedirect();
        $divisions = Division::where('is_active', true)->get();
        foreach ($divisions as $div) {
            $this->assertEquals('open', PeriodManagement::getStatus($div->id, $month, $year));
        }

        // Tutup semua divisi
        $response2 = $this->actingAs($this->adminUser)->post('/admin/periods/bulk-toggle', [
            'month' => $month,
            'year' => $year,
            'action' => 'close_all',
        ]);

        $response2->assertRedirect();
        foreach ($divisions as $div) {
            $this->assertEquals('closed', PeriodManagement::getStatus($div->id, $month, $year));
        }
    }

    public function test_admin_can_view_edit_requests_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/requests');

        $response->assertStatus(200);
        $response->assertSee('Pengajuan Akses & Edit Penilaian');
        $response->assertSee('Daftar Antrian Permohonan');
    }

    public function test_admin_can_approve_division_access_request(): void
    {
        $division = Division::first();
        $month = 1;
        $year = 2026;

        // Pastikan awalnya closed
        PeriodManagement::updateOrCreate(
            ['division_id' => $division->id, 'period_month' => $month, 'period_year' => $year],
            ['status' => 'closed', 'updated_by' => $this->adminUser->id]
        );

        $request = EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => $division->id,
            'requested_by' => $this->penilaiUser->id,
            'period_month' => $month,
            'period_year' => $year,
            'reason' => 'Perlu input ulang evaluasi Januari',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->post("/admin/requests/{$request->id}/approve", [
            'admin_note' => 'Disetujui untuk perbaikan data',
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($this->adminUser->id, $request->reviewed_by);
        $this->assertEquals('Disetujui untuk perbaikan data', $request->admin_note);

        // Verifikasi period_management ikut berubah jadi 'open'
        $this->assertEquals('open', PeriodManagement::getStatus($division->id, $month, $year));
    }

    public function test_admin_can_approve_individual_edit_request(): void
    {
        $employee = Employee::first();
        $month = 4;
        $year = 2026;

        $request = EditRequest::create([
            'request_type' => 'individual_edit',
            'employee_id' => $employee->id,
            'division_id' => $employee->division_id,
            'requested_by' => $this->penilaiUser->id,
            'period_month' => $month,
            'period_year' => $year,
            'reason' => 'Koreksi nilai indikator Loyal',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->post("/admin/requests/{$request->id}/approve", [
            'admin_note' => 'Disetujui silakan diedit',
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($this->adminUser->id, $request->reviewed_by);
        $this->assertEquals('Disetujui silakan diedit', $request->admin_note);
    }

    public function test_admin_can_reject_request_with_note(): void
    {
        $division = Division::first();

        $request = EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => $division->id,
            'requested_by' => $this->penilaiUser->id,
            'period_month' => 2,
            'period_year' => 2026,
            'reason' => 'Buka periode',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->post("/admin/requests/{$request->id}/reject", [
            'admin_note' => 'Periode tersebut sudah ditutup resmi oleh pimpinan cabang',
        ]);

        $response->assertRedirect();

        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertEquals($this->adminUser->id, $request->reviewed_by);
        $this->assertEquals('Periode tersebut sudah ditutup resmi oleh pimpinan cabang', $request->admin_note);
    }
}

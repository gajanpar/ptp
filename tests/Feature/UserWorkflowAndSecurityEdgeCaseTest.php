<?php

namespace Tests\Feature;

use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserWorkflowAndSecurityEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected User $penilaiOperasi;

    protected User $penilaiKeuangan;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->penilaiOperasi = User::where('username', 'rudy')->firstOrFail();
        $this->penilaiKeuangan = User::where('username', 'rindam')->firstOrFail();
        $this->adminUser = User::where('username', 'admin')->firstOrFail();
    }

    public function test_user_cannot_access_or_evaluate_employee_from_different_division(): void
    {
        // Create an employee in Divisi Keuangan (division_id = 7)
        $keuanganEmp = Employee::create([
            'nik' => 'TAD-KEU-01',
            'name' => 'Staf Keuangan TAD',
            'division_id' => 7,
            'position' => 'Kasir Pelabuhan',
            'vendor_name' => 'PT PDS',
            'is_active' => true,
        ]);

        // Rudy (Operasi, div 5) attempts to create evaluation for Keuangan TAD
        $createResponse = $this->actingAs($this->penilaiOperasi)
            ->get("/penilaian/create?employee_id={$keuanganEmp->id}&month=5&year=2026");
        $createResponse->assertStatus(404);

        // Create an evaluation belonging to Keuangan
        $keuanganEval = Evaluation::create([
            'user_id' => $this->penilaiKeuangan->id,
            'employee_id' => $keuanganEmp->id,
            'division_id' => 7,
            'period_month' => 4,
            'period_year' => 2026,
            'total_score' => 85.00,
            'average_score' => 5.00,
            'predicate' => 'Sangat Baik',
            'status' => 'submitted',
        ]);

        // Rudy attempts to view detail & print of Keuangan evaluation
        $detailResponse = $this->actingAs($this->penilaiOperasi)->get("/laporan/detail/{$keuanganEval->id}");
        $detailResponse->assertStatus(403);

        $printResponse = $this->actingAs($this->penilaiOperasi)->get("/laporan/print/{$keuanganEval->id}");
        $printResponse->assertStatus(403);
    }

    public function test_evaluation_submission_requires_all_seventeen_subcategories(): void
    {
        $emp = Employee::where('division_id', 5)->where('is_active', true)->firstOrFail();

        // Submit only 16 subcategories instead of 17 (Fix Bug K9)
        $keys = range(13, 28); // 16 items
        $ratings = array_combine($keys, array_fill(0, 16, 5));

        $response = $this->actingAs($this->penilaiOperasi)->post('/penilaian/store', [
            'employee_id' => $emp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Harap isi seluruh 17 butir penilaian', session('error'));
    }

    public function test_evaluation_submission_rejects_out_of_range_ratings(): void
    {
        $emp = Employee::where('division_id', 5)->where('is_active', true)->firstOrFail();

        // Rating 0 and rating 7 are invalid (valid is 1-6)
        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $ratings[13] = 7; // Out of range

        $response = $this->actingAs($this->penilaiOperasi)->post('/penilaian/store', [
            'employee_id' => $emp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
        ]);

        $response->assertSessionHasErrors(['ratings.13']);
    }

    public function test_user_can_submit_division_access_request_and_duplicate_is_prevented(): void
    {
        // First submission
        $response = $this->actingAs($this->penilaiOperasi)->post('/request-access/division', [
            'month' => 4,
            'year' => 2026,
            'reason' => 'Perlu membuka akses periode April untuk evaluasi susulan.',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('edit_requests', [
            'request_type' => 'division_access',
            'division_id' => 5,
            'period_month' => 4,
            'period_year' => 2026,
            'status' => 'pending',
        ]);

        // Duplicate submission while pending
        $dupResponse = $this->actingAs($this->penilaiOperasi)->post('/request-access/division', [
            'month' => 4,
            'year' => 2026,
            'reason' => 'Mencoba submit kedua kali.',
        ]);

        $dupResponse->assertSessionHas('info');
        $this->assertStringContainsString('sudah pernah diajukan', session('info'));
    }

    public function test_user_can_submit_individual_edit_request_and_duplicate_is_prevented(): void
    {
        $eval = Evaluation::where('division_id', 5)->where('period_month', 4)->firstOrFail();

        // First submission
        $response = $this->actingAs($this->penilaiOperasi)->post('/request-access/individual', [
            'employee_id' => $eval->employee_id,
            'month' => 4,
            'year' => 2026,
            'reason' => 'Perbaikan catatan evaluasi kedisiplinan.',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('edit_requests', [
            'request_type' => 'individual_edit',
            'employee_id' => $eval->employee_id,
            'status' => 'pending',
        ]);

        // Duplicate submission while pending
        $dupResponse = $this->actingAs($this->penilaiOperasi)->post('/request-access/individual', [
            'employee_id' => $eval->employee_id,
            'month' => 4,
            'year' => 2026,
            'reason' => 'Pengajuan ulang.',
        ]);

        $dupResponse->assertSessionHas('info');
        $this->assertStringContainsString('sedang menunggu persetujuan', session('info'));
    }

    public function test_user_cannot_edit_locked_evaluation_without_approval(): void
    {
        $eval = Evaluation::where('division_id', 5)->where('period_month', 4)->firstOrFail();

        // Make sure period April 2026 is closed for division 5
        PeriodManagement::setStatus(5, 4, 2026, 'closed');

        $response = $this->actingAs($this->penilaiOperasi)->get("/penilaian/edit/{$eval->id}");
        $response->assertRedirect('/penilaian?month=4&year=2026&tab=evaluated');
        $response->assertSessionHas('error');
    }

    public function test_approved_individual_edit_allows_access_and_reverts_to_completed_after_save(): void
    {
        $eval = Evaluation::where('division_id', 5)->where('period_month', 4)->firstOrFail();

        PeriodManagement::setStatus(5, 4, 2026, 'closed');

        // Create approved edit request
        $editReq = EditRequest::create([
            'request_type' => 'individual_edit',
            'division_id' => 5,
            'employee_id' => $eval->employee_id,
            'requested_by' => $this->penilaiOperasi->id,
            'period_month' => 4,
            'period_year' => 2026,
            'reason' => 'Izin perbaikan nilai',
            'status' => 'approved',
        ]);

        // Penilai now has access to edit form
        $editFormResponse = $this->actingAs($this->penilaiOperasi)->get("/penilaian/edit/{$eval->id}");
        $editFormResponse->assertStatus(200);
        $editFormResponse->assertSee('Edit Penilaian');
        $editFormResponse->assertSee('Skala Penilaian (1 s.d. 6)');

        // Penilai saves updated evaluation
        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 6)); // Update all to 6
        $reasons = array_combine($keys, array_fill(0, 17, 'Peningkatan kedisiplinan dan kinerja nyata.'));

        $saveResponse = $this->actingAs($this->penilaiOperasi)->post('/penilaian/store', [
            'employee_id' => $eval->employee_id,
            'month' => 4,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);

        $saveResponse->assertRedirect('/penilaian?month=4&year=2026&tab=evaluated');
        $saveResponse->assertSessionHas('success');

        // Verify edit_requests status is now 'completed' (Fix Bug K9(b))
        $this->assertEquals('completed', $editReq->fresh()->status);

        // Next edit attempt must be blocked again since single-use approval was consumed
        $blockedResponse = $this->actingAs($this->penilaiOperasi)->get("/penilaian/edit/{$eval->id}");
        $blockedResponse->assertRedirect('/penilaian?month=4&year=2026&tab=evaluated');
        $blockedResponse->assertSessionHas('error');
    }

    public function test_logout_invalidates_session_and_clears_auth(): void
    {
        $response = $this->actingAs($this->penilaiOperasi)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertFalse(Auth::check());
        $response->assertSessionHas('info');
    }
}

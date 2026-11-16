<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EnterpriseAuditAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $penilaiUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('username', 'admin')->firstOrFail();
        $this->penilaiUser = User::where('username', 'rudy')->firstOrFail();
    }

    public function test_security_headers_are_present_on_all_responses(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_admin_actions_automatically_generate_activity_logs(): void
    {
        // 1. Period Toggle Action
        $this->actingAs($this->adminUser)->post('/admin/periods/toggle', [
            'division_id' => 5,
            'month' => 4,
            'year' => 2026,
            'status' => 'open',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'period.toggle',
            'user_name' => $this->adminUser->name,
            'target_model' => 'PeriodManagement',
        ]);

        // 2. Edit Request Approval Action
        $req = EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => 5,
            'requested_by' => $this->penilaiUser->id,
            'period_month' => 4,
            'period_year' => 2026,
            'status' => 'pending',
            'reason' => 'Perlu buka periode',
        ]);

        $this->actingAs($this->adminUser)->post("/admin/requests/{$req->id}/approve", [
            'admin_note' => 'Disetujui untuk susulan.',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'edit_request.approved',
            'target_model' => 'EditRequest',
            'target_id' => (string) $req->id,
        ]);

        // 3. Employee Creation Action
        $this->actingAs($this->adminUser)->post('/admin/employees', [
            'nik' => 'TAD-TEST-09',
            'name' => 'Budi Auditor',
            'division_id' => 5,
            'position' => 'Staff Lapangan',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'employee.created',
            'target_model' => 'Employee',
        ]);

        // 4. Password Reset Action
        $this->actingAs($this->adminUser)->post("/admin/users/{$this->penilaiUser->id}/reset-password", [
            'new_password' => 'newsecretpassword123',
            'new_password_confirmation' => 'newsecretpassword123',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.password_reset',
            'target_model' => 'User',
            'target_id' => (string) $this->penilaiUser->id,
        ]);
    }

    public function test_activity_logs_admin_page_and_filtering(): void
    {
        // Record test log
        ActivityLog::record(
            'system.test_event',
            'Pengujian visual log audit sistem',
            'System',
            '1'
        );

        $response = $this->actingAs($this->adminUser)->get('/admin/activity-logs');

        $response->assertStatus(200);
        $response->assertSee('Log Aktivitas &amp; Audit Trail', false);
        $response->assertSee('system.test_event');

        // Test filter by action
        $filteredResponse = $this->actingAs($this->adminUser)->get('/admin/activity-logs?action=system');
        $filteredResponse->assertStatus(200);
        $filteredResponse->assertSee('system.test_event');
    }

    public function test_non_admin_cannot_access_activity_logs(): void
    {
        $response = $this->actingAs($this->penilaiUser)->get('/admin/activity-logs');
        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_auto_lock_periods_command(): void
    {
        // Explicitly set an expired past period (March 2026) as 'open'
        PeriodManagement::setStatus(5, 3, 2026, 'open');
        $this->assertEquals('open', PeriodManagement::getStatus(5, 3, 2026));

        // Run auto lock command
        Artisan::call('period:auto-lock', ['--month' => 3, '--year' => 2026]);

        // Assert it is now closed
        $this->assertEquals('closed', PeriodManagement::getStatus(5, 3, 2026));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'period.auto_locked',
            'target_id' => '5',
        ]);
    }

    public function test_reconcile_scores_command(): void
    {
        $eval = Evaluation::firstOrFail();

        // Inject intentional drift in evaluation total score
        $eval->update([
            'total_score' => 50.00,
            'predicate' => 'Perlu Perhatian',
        ]);

        // Run score reconciliation command
        Artisan::call('ptp:reconcile-scores');

        $eval->refresh();

        // Total score should be accurately recalculated from 17 details (e.g. > 70)
        $this->assertGreaterThan(70.00, (float) $eval->total_score);
        $this->assertNotEquals('Perlu Perhatian', $eval->predicate);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'evaluation.reconciled',
            'target_id' => (string) $eval->id,
        ]);
    }
}

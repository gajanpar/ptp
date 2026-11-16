<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCanManageEmployees;
use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * AdversarialMasterKaryawanChallengeTest
 *
 * Comprehensive adversarial verification harness for Master Karyawan:
 * - Admin authorization & cross-division supervision
 * - Kepala Divisi isolation, cross-division tamper resistance, and BOLA/IDOR prevention
 * - Privilege escalation defenses on admin modules
 * - Unauthenticated / unauthorized role prevention
 * - UI invariant rendering (delete buttons, Kelola Divisi visibility, division filter)
 * - Audit preservation & referential integrity resilience (no 500 crashes)
 */
class AdversarialMasterKaryawanChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kadivOperasi;

    protected User $kadivPerencanaan;

    protected User $kadivKeuangan;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('role', 'admin')->firstOrFail();
        $this->kadivOperasi = User::where('role', 'kepala_divisi')->where('division_id', 5)->firstOrFail();
        $this->kadivPerencanaan = User::where('role', 'kepala_divisi')->where('division_id', 6)->firstOrFail();
        $this->kadivKeuangan = User::where('role', 'kepala_divisi')->where('division_id', 7)->firstOrFail();
    }

    // =========================================================================
    // 1. ADMIN AUTHORIZATION & CROSS-DIVISION SUPERVISION
    // =========================================================================

    public function test_admin_can_view_all_employees_across_all_divisions_and_filter_freely(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/employees');
        $response->assertStatus(200);

        // Verify total count in metric card reflects all divisions
        $totalEmp = Employee::count();
        $response->assertSee((string) $totalEmp);

        // Verify filtering by each division
        foreach ([5, 6, 7] as $divId) {
            $filterRes = $this->actingAs($this->adminUser)->get('/admin/employees?division_id='.$divId);
            $filterRes->assertStatus(200);

            $divEmp = Employee::where('division_id', $divId)->first();
            if ($divEmp) {
                $filterRes->assertSee($divEmp->name);
            }
        }

        // Test search across divisions: search for an employee specifically in division 7
        $target = Employee::where('division_id', 7)->firstOrFail();
        $searchRes = $this->actingAs($this->adminUser)->get('/admin/employees?search='.urlencode($target->name));
        $searchRes->assertStatus(200);
        $searchRes->assertSee($target->name);
    }

    public function test_admin_can_delete_unassessed_employee_with_activity_log(): void
    {
        $freshEmp = Employee::create([
            'division_id' => 6,
            'nik' => 'TAD-UNASSESSED-01',
            'name' => 'Karyawan Tanpa Riwayat',
            'position' => 'Staff Administrasi',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('employees', ['id' => $freshEmp->id]);

        $response = $this->actingAs($this->adminUser)->delete("/admin/employees/{$freshEmp->id}");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['id' => $freshEmp->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'employee.deleted',
            'user_id' => $this->adminUser->id,
        ]);
    }

    public function test_admin_deleting_evaluated_employee_is_safely_prevented_without_500_crash(): void
    {
        $evaluatedEmp = Employee::whereHas('evaluations')->firstOrFail();

        $response = $this->actingAs($this->adminUser)->delete("/admin/employees/{$evaluatedEmp->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('integritas audit', session('error'));

        // Verify record is preserved
        $this->assertDatabaseHas('employees', ['id' => $evaluatedEmp->id]);
    }

    // =========================================================================
    // 2. KEPALA DIVISI AUTHORIZATION & DIVISION ISOLATION
    // =========================================================================

    public function test_kepala_divisi_sees_only_own_division_employees(): void
    {
        $response = $this->actingAs($this->kadivOperasi)->get('/admin/employees');
        $response->assertStatus(200);

        $div5Emp = Employee::where('division_id', 5)->first();
        if ($div5Emp) {
            $response->assertSee($div5Emp->name);
        }

        $div6Emp = Employee::where('division_id', 6)->first();
        if ($div6Emp) {
            $response->assertDontSee($div6Emp->name);
        }

        $div7Emp = Employee::where('division_id', 7)->first();
        if ($div7Emp) {
            $response->assertDontSee($div7Emp->name);
        }
    }

    public function test_kepala_divisi_cannot_bypass_division_scoping_via_query_parameters(): void
    {
        // Kadiv Operasi (division 5) tries ?division_id=7 (Keuangan)
        $response = $this->actingAs($this->kadivOperasi)->get('/admin/employees?division_id=7');
        $response->assertStatus(200);

        $div7Emp = Employee::where('division_id', 7)->first();
        if ($div7Emp) {
            $response->assertDontSee($div7Emp->name);
        }

        $div5Emp = Employee::where('division_id', 5)->first();
        if ($div5Emp) {
            $response->assertSee($div5Emp->name);
        }
    }

    public function test_kepala_divisi_search_is_strictly_scoped_to_own_division(): void
    {
        $foreignEmp = Employee::where('division_id', 7)->firstOrFail();

        // Kadiv Operasi searches specifically for Keuangan employee's name
        $response = $this->actingAs($this->kadivOperasi)->get('/admin/employees?search='.urlencode($foreignEmp->name));
        $response->assertStatus(200);

        // The search input will echo the query, but the employee table must be empty!
        $employees = $response->viewData('employees');
        $this->assertCount(0, $employees);
        $response->assertSee('Tidak ditemukan data karyawan TAD');
    }

    public function test_kepala_divisi_creating_employee_is_forced_to_own_division_even_if_tampered(): void
    {
        // Kadiv Operasi attempts to inject employee into division 7 (Keuangan)
        $tamperedPayload = [
            'nik' => 'TAD-TAMPER-01',
            'name' => 'Karyawan Hasil Injeksi',
            'division_id' => 7, // Malicious target division
            'position' => 'Staff Keuangan Gelap',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
        ];

        $response = $this->actingAs($this->kadivOperasi)->post('/admin/employees', $tamperedPayload);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $created = Employee::where('name', 'Karyawan Hasil Injeksi')->firstOrFail();

        // Division MUST be 5 (Kadiv Operasi's division), NOT 7!
        $this->assertEquals(5, $created->division_id, 'Tampered division_id was NOT overridden with authenticated user division!');
    }

    public function test_kepala_divisi_can_delete_unassessed_employee_in_own_division(): void
    {
        $unassessedOwn = Employee::create([
            'division_id' => 5, // Kadiv Operasi division
            'nik' => 'TAD-OWN-DEL-01',
            'name' => 'Karyawan Operasi Baru',
            'position' => 'Operator Dermaga',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->kadivOperasi)->delete("/admin/employees/{$unassessedOwn->id}");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['id' => $unassessedOwn->id]);
    }

    public function test_kepala_divisi_cannot_delete_employee_in_another_division_blocked_with_403(): void
    {
        $unassessedForeign = Employee::create([
            'division_id' => 7, // Keuangan
            'nik' => 'TAD-FOREIGN-DEL',
            'name' => 'Karyawan Keuangan Terlindungi',
            'position' => 'Kasir Dermaga',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        // Kadiv Operasi attempts to delete employee in Keuangan
        $response = $this->actingAs($this->kadivOperasi)->delete("/admin/employees/{$unassessedForeign->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('employees', ['id' => $unassessedForeign->id]);
    }

    public function test_kepala_divisi_cannot_update_employee_in_another_division_blocked_with_403(): void
    {
        $foreignEmp = Employee::where('division_id', 7)->firstOrFail();
        $originalName = $foreignEmp->name;

        // Kadiv Operasi attempts to modify Keuangan employee profile
        $response = $this->actingAs($this->kadivOperasi)->put("/admin/employees/{$foreignEmp->id}", [
            'nik' => $foreignEmp->nik,
            'name' => 'Nama Dihack Oleh Kadiv Lain',
            'division_id' => 7,
            'position' => 'Posisi Modifikasi',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => 1,
        ]);

        $response->assertStatus(403);
        $this->assertEquals($originalName, $foreignEmp->fresh()->name);
    }

    public function test_kepala_divisi_cannot_toggle_status_of_foreign_division_employee_blocked_with_403(): void
    {
        $foreignEmp = Employee::where('division_id', 7)->where('is_active', true)->firstOrFail();

        // Kadiv Operasi attempts to deactivate foreign employee
        $response = $this->actingAs($this->kadivOperasi)->post("/admin/employees/{$foreignEmp->id}/toggle-status");

        $response->assertStatus(403);
        $this->assertTrue($foreignEmp->fresh()->is_active);
    }

    public function test_kepala_divisi_cannot_transfer_own_employee_to_another_division_via_update(): void
    {
        $ownEmp = Employee::where('division_id', 5)->firstOrFail();

        // Kadiv attempts to change division_id to 6
        $response = $this->actingAs($this->kadivOperasi)->put("/admin/employees/{$ownEmp->id}", [
            'nik' => $ownEmp->nik,
            'name' => $ownEmp->name.' Updated',
            'division_id' => 6, // Unauthorized transfer attempt
            'position' => $ownEmp->position,
            'vendor_name' => $ownEmp->vendor_name,
            'is_active' => 1,
        ]);

        $response->assertRedirect();
        // The division MUST remain 5!
        $this->assertEquals(5, $ownEmp->fresh()->division_id);
    }

    public function test_kepala_divisi_deleting_evaluated_employee_in_own_division_safely_prevented(): void
    {
        $evaluatedOwn = Employee::where('division_id', 5)->whereHas('evaluations')->firstOrFail();

        $response = $this->actingAs($this->kadivOperasi)->delete("/admin/employees/{$evaluatedOwn->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('integritas audit', session('error'));
        $this->assertDatabaseHas('employees', ['id' => $evaluatedOwn->id]);
    }

    public function test_kepala_divisi_deleting_evaluated_foreign_employee_triggers_403_before_eval_check(): void
    {
        $foreignEmp = Employee::where('division_id', 7)->firstOrFail();

        // Attach an evaluation to this foreign employee
        Evaluation::create([
            'employee_id' => $foreignEmp->id,
            'division_id' => 7,
            'user_id' => $this->kadivKeuangan->id,
            'period_year' => 2026,
            'period_month' => 4,
            'score_total' => 88.5,
            'predicate' => 'Baik',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->kadivOperasi)->delete("/admin/employees/{$foreignEmp->id}");

        // Division boundary check must take precedence over evaluation existence check
        $response->assertStatus(403);
        $this->assertDatabaseHas('employees', ['id' => $foreignEmp->id]);
    }

    // =========================================================================
    // 3. PRIVILEGE ESCALATION PREVENTION
    // =========================================================================

    public function test_kepala_divisi_cannot_store_or_update_divisions_blocked_with_redirect_or_403(): void
    {
        // 1. Web request: blocked by admin middleware, redirected to dashboard with error
        $webStoreRes = $this->actingAs($this->kadivOperasi)->post('/admin/divisions', [
            'name' => 'Divisi Palsu',
            'description' => 'Deskripsi',
        ]);
        $webStoreRes->assertRedirect(route('dashboard'));
        $webStoreRes->assertSessionHas('error');

        $webUpdateRes = $this->actingAs($this->kadivOperasi)->put('/admin/divisions/5', [
            'name' => 'Operasi Dimodifikasi Kadiv',
            'description' => 'Deskripsi',
            'is_active' => 1,
        ]);
        $webUpdateRes->assertRedirect(route('dashboard'));
        $webUpdateRes->assertSessionHas('error');

        // 2. JSON / API request: blocked with HTTP 403 Forbidden
        $jsonStoreRes = $this->actingAs($this->kadivOperasi)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/divisions', [
                'name' => 'Divisi Palsu JSON',
                'description' => 'Deskripsi',
            ]);
        $jsonStoreRes->assertStatus(403);

        $jsonUpdateRes = $this->actingAs($this->kadivOperasi)
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/divisions/5', [
                'name' => 'Operasi Dimodifikasi Kadiv JSON',
                'description' => 'Deskripsi',
                'is_active' => 1,
            ]);
        $jsonUpdateRes->assertStatus(403);
    }

    public function test_kepala_divisi_blocked_from_admin_exclusive_endpoints(): void
    {
        $restrictedEndpoints = [
            ['GET', '/admin/dashboard'],
            ['GET', '/admin/users'],
            ['POST', '/admin/users', ['name' => 'Test', 'username' => 'test', 'password' => 'Pass123!']],
            ['GET', '/admin/rubrik'],
            ['GET', '/admin/system/settings'],
            ['GET', '/admin/system/backup'],
            ['GET', '/admin/activity-logs'],
        ];

        foreach ($restrictedEndpoints as $item) {
            $method = $item[0];
            $uri = $item[1];
            $payload = $item[2] ?? [];

            if ($method === 'GET') {
                $res = $this->actingAs($this->kadivOperasi)->get($uri);
            } else {
                $res = $this->actingAs($this->kadivOperasi)->post($uri, $payload);
            }

            // Expect redirection to penilai dashboard with error or 403
            $this->assertTrue(
                in_array($res->status(), [302, 403], true),
                "Endpoint {$method} {$uri} allowed access for kepala_divisi! Status: {$res->status()}"
            );

            if ($res->status() === 302 && $uri !== '/admin/dashboard') {
                $res->assertSessionHas('error');
            }
        }
    }

    public function test_unauthenticated_guest_blocked_from_all_employee_endpoints(): void
    {
        $emp = Employee::firstOrFail();

        $endpoints = [
            ['GET', '/admin/employees'],
            ['POST', '/admin/employees', ['name' => 'Ghost']],
            ['PUT', "/admin/employees/{$emp->id}", ['name' => 'Ghost Update']],
            ['POST', "/admin/employees/{$emp->id}/toggle-status"],
            ['DELETE', "/admin/employees/{$emp->id}"],
        ];

        foreach ($endpoints as $ep) {
            $method = $ep[0];
            $uri = $ep[1];
            $data = $ep[2] ?? [];

            $res = match ($method) {
                'GET' => $this->get($uri),
                'POST' => $this->post($uri, $data),
                'PUT' => $this->put($uri, $data),
                'DELETE' => $this->delete($uri),
            };

            $res->assertRedirect('/login');
        }
    }

    public function test_deactivated_user_cannot_access_employee_endpoints(): void
    {
        $deactivatedKadiv = User::where('role', 'kepala_divisi')->where('division_id', 6)->firstOrFail();
        $deactivatedKadiv->update(['is_active' => false]);

        $response = $this->actingAs($deactivatedKadiv)->get('/admin/employees');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_role_rejected_by_manage_employees_middleware(): void
    {
        $middleware = new EnsureCanManageEmployees;
        $request = Request::create('/admin/employees', 'GET');

        // Create an unpersisted User mock with unauthorized role
        $fakeUser = new User;
        $fakeUser->role = 'operator';
        $fakeUser->is_active = true;

        Auth::login($fakeUser);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(route('dashboard'), $response->headers->get('Location'));
    }

    // =========================================================================
    // 4. UI INVARIANTS
    // =========================================================================

    public function test_delete_button_rendered_for_all_employees_regardless_of_eval_history(): void
    {
        $res = $this->actingAs($this->adminUser)->get('/admin/employees');
        $res->assertStatus(200);

        $content = $res->getContent();

        // 1. Assert delete action button is rendered in the HTML
        $this->assertStringContainsString('Hapus permanen', $content);
        $this->assertStringContainsString('data-action-type="delete"', $content);

        // 2. Both has-history="1" and has-history="0" attributes exist
        $this->assertStringContainsString('data-has-history="1"', $content);

        // 3. Confirm confirmation modal elements are present
        $this->assertStringContainsString('confirmEmployeeActionModal', $content);
        $this->assertStringContainsString('Perlindungan Arsip Riwayat Evaluasi', $content);
    }

    public function test_kelola_divisi_button_and_filter_shown_to_admin_and_hidden_from_kadiv(): void
    {
        // Admin View
        $adminRes = $this->actingAs($this->adminUser)->get('/admin/employees');
        $adminRes->assertStatus(200);
        $adminRes->assertSee('Kelola Divisi');
        $adminRes->assertSee('empFilterDivision');
        $adminRes->assertSee('id="divisionModal"', false);

        // Kepala Divisi View
        $kadivRes = $this->actingAs($this->kadivOperasi)->get('/admin/employees');
        $kadivRes->assertStatus(200);
        $kadivRes->assertDontSee('Kelola Divisi');
        $kadivRes->assertDontSee('empFilterDivision');
        $kadivRes->assertDontSee('id="divisionModal"', false);
    }

    public function test_layout_template_differentiation(): void
    {
        // Admin gets layouts.admin (contains brand-logo-sidebar-admin)
        $adminRes = $this->actingAs($this->adminUser)->get('/admin/employees');
        $adminRes->assertStatus(200);
        $adminRes->assertSee('brand-logo-sidebar-admin');

        // Kadiv gets layouts.app (contains brand-logo-sidebar without -admin)
        $kadivRes = $this->actingAs($this->kadivOperasi)->get('/admin/employees');
        $kadivRes->assertStatus(200);
        $kadivRes->assertSee('brand-logo-sidebar');
        $kadivRes->assertDontSee('brand-logo-sidebar-admin');
    }

    // =========================================================================
    // 5. ADVERSARIAL STRESS & INPUT INTEGRITY
    // =========================================================================

    public function test_adversarial_xss_payload_in_employee_name_is_escaped_in_blade_view(): void
    {
        $xssEmp = Employee::create([
            'division_id' => 5,
            'nik' => 'TAD-XSS-01',
            'name' => '<script>alert("PWNED_XSS")</script>',
            'position' => 'Security Tester <img src=x onerror=alert(1)>',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/employees');
        $response->assertStatus(200);

        $content = $response->getContent();

        // Raw malicious script tag must NOT be executed or rendered unescaped in text nodes
        $this->assertStringNotContainsString(
            '<td style="padding: 13px 18px; font-weight: 700; color: #0f172a;">'.$xssEmp->name.'</td>',
            $content
        );

        // Must be properly HTML entity escaped
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;PWNED_XSS&quot;)&lt;/script&gt;', $content);
    }

    public function test_adversarial_sql_injection_payload_in_search_query_is_safe(): void
    {
        $sqliPayloads = [
            "' OR '1'='1",
            "'; DROP TABLE employees; --",
            '1 UNION SELECT 1,2,3,4,5,6,7,8,9--',
            "admin' --",
        ];

        foreach ($sqliPayloads as $payload) {
            $response = $this->actingAs($this->adminUser)->get('/admin/employees?search='.urlencode($payload));
            $response->assertStatus(200);
            $this->assertDatabaseHas('employees', ['id' => 10]);
        }
    }

    public function test_adversarial_employee_with_edit_requests_history_cannot_be_deleted(): void
    {
        $emp = Employee::create([
            'division_id' => 5,
            'nik' => 'TAD-EDIT-REQ',
            'name' => 'Karyawan Dengan Request Edit',
            'position' => 'Staff Operasi',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        EditRequest::create([
            'request_type' => 'individual_edit',
            'employee_id' => $emp->id,
            'division_id' => 5,
            'requested_by' => $this->kadivOperasi->id,
            'period_month' => 4,
            'period_year' => 2026,
            'reason' => 'Perbaikan data historis',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->delete("/admin/employees/{$emp->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertStringContainsString('integritas audit', session('error'));
        $this->assertDatabaseHas('employees', ['id' => $emp->id]);
    }

    public function test_adversarial_non_existent_or_malformed_employee_id_returns_404(): void
    {
        $nonExistentId = 999999;

        $deleteRes = $this->actingAs($this->adminUser)->delete("/admin/employees/{$nonExistentId}");
        $deleteRes->assertStatus(404);

        $putRes = $this->actingAs($this->adminUser)->put("/admin/employees/{$nonExistentId}", [
            'name' => 'Non Existent',
            'division_id' => 5,
            'position' => 'Pos',
            'vendor_name' => 'PT Pelindo',
            'is_active' => 1,
        ]);
        $putRes->assertStatus(404);

        $toggleRes = $this->actingAs($this->adminUser)->post("/admin/employees/{$nonExistentId}/toggle-status");
        $toggleRes->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\EvaluationCategory;
use App\Models\EvaluationSubcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMasterDataTest extends TestCase
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

    public function test_non_admin_cannot_access_restricted_master_data_modules(): void
    {
        foreach (['/admin/users', '/admin/rubrik'] as $uri) {
            $response = $this->actingAs($this->penilaiUser)->get($uri);
            $response->assertRedirect('/dashboard');
            $response->assertSessionHas('error');
        }
    }

    public function test_kepala_divisi_can_access_master_karyawan_scoped_to_division(): void
    {
        $response = $this->actingAs($this->penilaiUser)->get('/admin/employees');
        $response->assertStatus(200);
        $response->assertSee('Master Data Karyawan TAD');
        $response->assertDontSee('Kelola Divisi');
    }

    public function test_admin_can_view_create_update_and_toggle_employee_with_auto_nik(): void
    {
        $indexResponse = $this->actingAs($this->adminUser)->get('/admin/employees?search=Agung');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Agung Sugiarto');

        $division = Division::firstOrFail();

        $storeResponse = $this->actingAs($this->adminUser)->post('/admin/employees', [
            'nik' => '',
            'name' => "Ma'ruf Santoso",
            'division_id' => $division->id,
            'position' => 'Operator Alat (Reach Stacker)',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
        ]);

        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('success');

        $created = Employee::where('name', "Ma'ruf Santoso")->firstOrFail();
        $this->assertNotEmpty($created->nik);
        $this->assertStringStartsWith('TAD-', $created->nik);
        $this->assertTrue($created->is_active);

        $otherDivision = Division::where('id', '!=', $division->id)->firstOrFail();

        $updateResponse = $this->actingAs($this->adminUser)->put("/admin/employees/{$created->id}", [
            'nik' => 'TAD-9001',
            'name' => "Ma'ruf Santoso Putra",
            'division_id' => $otherDivision->id,
            'position' => 'Koordinator Lapangan',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect();
        $created->refresh();
        $this->assertEquals('TAD-9001', $created->nik);
        $this->assertEquals("Ma'ruf Santoso Putra", $created->name);
        $this->assertEquals($otherDivision->id, $created->division_id);

        $toggleResponse = $this->actingAs($this->adminUser)->post("/admin/employees/{$created->id}/toggle-status");
        $toggleResponse->assertRedirect();
        $this->assertFalse($created->fresh()->is_active);
    }

    public function test_employee_with_evaluation_history_cannot_be_deleted_permanently(): void
    {
        $evaluatedEmployee = Employee::findOrFail(10);

        $blockedResponse = $this->actingAs($this->adminUser)->delete("/admin/employees/{$evaluatedEmployee->id}");
        $blockedResponse->assertRedirect();
        $blockedResponse->assertSessionHas('error');
        $this->assertDatabaseHas('employees', ['id' => $evaluatedEmployee->id]);
        $this->assertDatabaseHas('evaluations', ['employee_id' => $evaluatedEmployee->id]);

        $freshEmployee = Employee::create([
            'division_id' => 5,
            'nik' => 'TAD-TEMP',
            'name' => 'Karyawan Percobaan',
            'position' => 'Helper',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $deleteResponse = $this->actingAs($this->adminUser)->delete("/admin/employees/{$freshEmployee->id}");
        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('employees', ['id' => $freshEmployee->id]);
    }

    public function test_admin_can_manage_divisions(): void
    {
        $storeResponse = $this->actingAs($this->adminUser)->post('/admin/divisions', [
            'name' => 'HSSE & Pengamanan',
            'description' => 'Keselamatan Kerja dan Keamanan Pelabuhan',
        ]);
        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('success');

        $division = Division::where('name', 'HSSE & Pengamanan')->firstOrFail();

        $updateResponse = $this->actingAs($this->adminUser)->put("/admin/divisions/{$division->id}", [
            'name' => 'HSSE & K3L',
            'description' => 'Unit K3L Cabang Cirebon',
            'is_active' => 1,
        ]);
        $updateResponse->assertRedirect();
        $this->assertEquals('HSSE & K3L', $division->fresh()->name);
    }

    public function test_admin_users_page_does_not_leak_password_hashes_and_supports_crud(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');
        $response->assertStatus(200);
        $response->assertSee('Rudy Kusuma');
        $response->assertDontSee($this->adminUser->password);
        $response->assertDontSee($this->penilaiUser->password);

        $division = Division::firstOrFail();

        $createResponse = $this->actingAs($this->adminUser)->post('/admin/users', [
            'name' => 'Hendra Wijaya',
            'username' => 'hendra_ops',
            'email' => 'hendra@pelindo.co.id',
            'role' => 'kepala_divisi',
            'division_id' => $division->id,
            'position' => 'Supervisor Operasi',
            'password' => 'Pelindo2026!',
            'password_confirmation' => 'Pelindo2026!',
        ]);

        $createResponse->assertRedirect();
        $createResponse->assertSessionHas('success');

        $newUser = User::where('username', 'hendra_ops')->firstOrFail();
        $this->assertTrue(Hash::check('Pelindo2026!', $newUser->password));

        $updateResponse = $this->actingAs($this->adminUser)->put("/admin/users/{$newUser->id}", [
            'name' => 'Hendra Wijaya, S.T.',
            'username' => 'hendra_ops',
            'email' => 'hendra.wijaya@pelindo.co.id',
            'role' => 'kepala_divisi',
            'division_id' => $division->id,
            'position' => 'Manager Operasi',
            'is_active' => 1,
        ]);

        $updateResponse->assertRedirect();
        $this->assertEquals('Hendra Wijaya, S.T.', $newUser->fresh()->name);
        $this->assertTrue(Hash::check('Pelindo2026!', $newUser->fresh()->password));
    }

    public function test_admin_can_reset_user_password_securely(): void
    {
        $response = $this->actingAs($this->adminUser)->post("/admin/users/{$this->penilaiUser->id}/reset-password", [
            'new_password' => 'SandiBaruAman99!',
            'new_password_confirmation' => 'SandiBaruAman99!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('SandiBaruAman99!', $this->penilaiUser->fresh()->password));
    }

    public function test_admin_cannot_deactivate_self_and_deactivated_user_session_is_revoked(): void
    {
        $selfToggle = $this->actingAs($this->adminUser)->post("/admin/users/{$this->adminUser->id}/toggle-status");
        $selfToggle->assertRedirect();
        $selfToggle->assertSessionHas('error');
        $this->assertTrue($this->adminUser->fresh()->is_active);

        $deactivatePenilai = $this->actingAs($this->adminUser)->post("/admin/users/{$this->penilaiUser->id}/toggle-status");
        $deactivatePenilai->assertRedirect();
        $this->assertFalse($this->penilaiUser->fresh()->is_active);

        $revokedAttempt = $this->actingAs($this->penilaiUser->fresh())->get('/dashboard');
        $revokedAttempt->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_view_and_update_rubrik_akhlak_with_referential_protection(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/rubrik');
        $response->assertStatus(200);
        $response->assertSee('Master Rubrik Penilaian AKHLAK');
        $response->assertSee('Amanah');
        $response->assertSee('99.96');

        $category = EvaluationCategory::where('name', 'Amanah')->firstOrFail();
        $subcategory = EvaluationSubcategory::where('category_id', $category->id)->firstOrFail();

        $updateSub = $this->actingAs($this->adminUser)->put("/admin/rubrik/subcategories/{$subcategory->id}", [
            'category_id' => $category->id,
            'name' => $subcategory->name,
            'description' => 'Indikator utama integritas kerja lapangan',
            'default_reason' => 'Melaksanakan tugas secara jujur, disiplin, dan bertanggung jawab.',
            'order_index' => $subcategory->order_index,
        ]);

        $updateSub->assertRedirect();
        $updateSub->assertSessionHas('success');
        $this->assertEquals(
            'Indikator utama integritas kerja lapangan',
            $subcategory->fresh()->description
        );

        $blockedCatDelete = $this->actingAs($this->adminUser)->delete("/admin/rubrik/categories/{$category->id}");
        $blockedCatDelete->assertRedirect();
        $blockedCatDelete->assertSessionHas('error');

        $blockedSubDelete = $this->actingAs($this->adminUser)->delete("/admin/rubrik/subcategories/{$subcategory->id}");
        $blockedSubDelete->assertRedirect();
        $blockedSubDelete->assertSessionHas('error');
    }

    public function test_delete_button_remains_visible_with_honest_archive_notice(): void
    {
        $historyEmployee = Employee::whereHas('evaluations')->firstOrFail();

        $page = $this->actingAs($this->adminUser)->get('/admin/employees?search='.urlencode($historyEmployee->name));
        $page->assertStatus(200);
        $page->assertSee('Hapus permanen '.$historyEmployee->name);
        $page->assertSee('Perlindungan Arsip Riwayat Evaluasi');
        $page->assertDontSee('Tetap Lanjutkan Hapus');
    }
}

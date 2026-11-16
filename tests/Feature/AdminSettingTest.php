<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminSettingTest extends TestCase
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

    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/system/settings');

        $response->assertStatus(200);
        $response->assertSee('Konfigurasi Cabang &amp; Pejabat Penandatangan', false);
        $response->assertSee('Bambang Wijanarko, S.T., M.M.');
        $response->assertSee('Dedi Heryadi, S.E.');
        $response->assertSee('Live Preview Lembar Cetak Dinas');
    }

    public function test_non_admin_cannot_access_settings_page(): void
    {
        $endpoints = [
            ['method' => 'GET', 'uri' => '/admin/system/settings'],
            ['method' => 'POST', 'uri' => '/admin/system/settings'],
            ['method' => 'POST', 'uri' => '/admin/system/settings/reset'],
        ];

        foreach ($endpoints as $ep) {
            $response = $this->actingAs($this->penilaiUser)->call($ep['method'], $ep['uri']);
            $response->assertRedirect('/dashboard');
            $response->assertSessionHas('error');
        }
    }

    public function test_admin_can_update_settings_and_audit_log_is_recorded(): void
    {
        $payload = [
            'company_name' => 'PT Pelabuhan Indonesia (Persero) Pusat',
            'subholding_name' => 'PT Pelindo Multi Terminal Regional 2',
            'branch_name' => 'Branch Cirebon Pelabuhan',
            'branch_address' => 'Jl. Dermaga Muara Jati No. 10, Cirebon',
            'city' => 'Kota Cirebon',
            'postal_code' => '45112',
            'phone' => '(0231) 998877',
            'email' => 'sdm.cirebon@pelindo.co.id',
            'branch_manager_name' => 'Ir. Agus Sudrajat, M.M.',
            'branch_manager_nipp' => '198701012010121001',
            'branch_manager_title' => 'General Manager',
            'sdm_manager_name' => 'Siti Aminah, S.Psi., M.M.',
            'sdm_manager_nipp' => '199203152015032002',
            'sdm_manager_title' => 'Manager SDM & Hubungan Industrial',
            'document_footer_note' => 'Dokumen resmi legalitas penilaian kinerja PT Pelindo.',
        ];

        $response = $this->actingAs($this->adminUser)->post('/admin/system/settings', $payload);

        $response->assertRedirect('/admin/system/settings');
        $response->assertSessionHas('success');

        $this->assertEquals('Ir. Agus Sudrajat, M.M.', Setting::get('branch_manager_name'));
        $this->assertEquals('Siti Aminah, S.Psi., M.M.', Setting::get('sdm_manager_name'));
        $this->assertEquals('Kota Cirebon', Setting::get('city'));
        $this->assertEquals('Jl. Dermaga Muara Jati No. 10, Cirebon', Setting::get('branch_address'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.settings_updated',
            'user_name' => $this->adminUser->name,
            'target_model' => 'SystemSetting',
        ]);
    }

    public function test_settings_validation_rules(): void
    {
        $invalidPayload = [
            'company_name' => '', // required
            'branch_manager_name' => '', // required
            'sdm_manager_name' => '', // required
            'city' => '', // required
            'email' => 'invalid-email-format',
        ];

        $response = $this->actingAs($this->adminUser)->post('/admin/system/settings', $invalidPayload);

        $response->assertSessionHasErrors([
            'company_name',
            'branch_manager_name',
            'sdm_manager_name',
            'city',
            'email',
        ]);
    }

    public function test_admin_can_reset_settings_to_default(): void
    {
        // Mutate first
        Setting::set('branch_manager_name', 'Nama Sementara');
        $this->assertEquals('Nama Sementara', Setting::get('branch_manager_name'));

        // Reset
        $response = $this->actingAs($this->adminUser)->post('/admin/system/settings/reset');

        $response->assertRedirect('/admin/system/settings');
        $response->assertSessionHas('success');

        $this->assertEquals('Bambang Wijanarko, S.T., M.M.', Setting::get('branch_manager_name'));
        $this->assertEquals('Dedi Heryadi, S.E.', Setting::get('sdm_manager_name'));

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system.settings_reset',
            'user_name' => $this->adminUser->name,
        ]);
    }

    public function test_print_views_reflect_dynamic_settings(): void
    {
        Setting::set('branch_manager_name', 'Drs. Hendro Wibowo, M.Si.');
        Setting::set('sdm_manager_name', 'Ratna Sari Dewi, S.E.');
        Setting::set('branch_address', 'Jl. Samudera Raya Pelabuhan Cirebon 45111');
        Setting::set('city', 'Cirebon Pesisir');

        $evaluation = Evaluation::first();
        $this->assertNotNull($evaluation);

        // 1. Single evaluation print
        $singleResponse = $this->actingAs($this->adminUser)->get("/admin/reports/{$evaluation->id}/print");
        $singleResponse->assertStatus(200);
        $singleResponse->assertSee('Ratna Sari Dewi, S.E.');
        $singleResponse->assertSee('Jl. Samudera Raya Pelabuhan Cirebon 45111');
        $singleResponse->assertSee('Cirebon Pesisir');

        // 2. Summary monthly print
        $summaryResponse = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=monthly&year=2026&month=4');
        $summaryResponse->assertStatus(200);
        $summaryResponse->assertSee('Ratna Sari Dewi, S.E.');
        $summaryResponse->assertSee('Jl. Samudera Raya Pelabuhan Cirebon 45111');

        // 3. Annual matrix print
        $annualResponse = $this->actingAs($this->adminUser)->get('/admin/reports/print-summary?period_type=annual&year=2026');
        $annualResponse->assertStatus(200);
        $annualResponse->assertSee('Drs. Hendro Wibowo, M.Si.');
        $annualResponse->assertSee('Ratna Sari Dewi, S.E.');
    }

    public function test_setting_model_two_tier_caching_and_cache_invalidation(): void
    {
        Setting::clearCache();

        $all = Setting::getAllKeyValue();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('branch_manager_name', $all);

        // Fallback default
        $this->assertEquals('Default Value', Setting::get('non_existent_key_xyz', 'Default Value'));

        // Setting a new value
        Setting::set('custom_test_param', 'Kustom Nilai 123', 'test');
        $this->assertEquals('Kustom Nilai 123', Setting::get('custom_test_param'));

        // Updating existing value
        Setting::set('custom_test_param', 'Kustom Nilai Baru', 'test');
        $this->assertEquals('Kustom Nilai Baru', Setting::get('custom_test_param'));
    }
}

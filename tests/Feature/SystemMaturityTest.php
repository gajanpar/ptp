<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemMaturityTest extends TestCase
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

    public function test_authenticated_user_can_view_profile_page(): void
    {
        // Penilai
        $response = $this->actingAs($this->penilaiUser)->get('/profile');
        $response->assertStatus(200);
        $response->assertSee('Profil Akun &amp; Keamanan Kata Sandi', false);
        $response->assertSee($this->penilaiUser->name);
        $response->assertSee('Ubah Kata Sandi Mandiri');

        // Admin
        $adminResponse = $this->actingAs($this->adminUser)->get('/profile');
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee($this->adminUser->name);
    }

    public function test_user_can_update_password_successfully_with_valid_current_password(): void
    {
        $payload = [
            'current_password' => 'password123',
            'password' => 'PelindoBUMN2026!',
            'password_confirmation' => 'PelindoBUMN2026!',
        ];

        $response = $this->actingAs($this->penilaiUser)->put('/profile/password', $payload);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        // Refresh model from DB
        $this->penilaiUser->refresh();
        $this->assertTrue(Hash::check('PelindoBUMN2026!', $this->penilaiUser->password));

        // Verify audit log
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.password_changed_self',
            'user_name' => $this->penilaiUser->name,
            'target_model' => 'User',
            'target_id' => $this->penilaiUser->id,
        ]);
    }

    public function test_password_update_fails_with_invalid_current_password(): void
    {
        $payload = [
            'current_password' => 'wrongpassword',
            'password' => 'PelindoBUMN2026!',
            'password_confirmation' => 'PelindoBUMN2026!',
        ];

        $response = $this->actingAs($this->penilaiUser)->put('/profile/password', $payload);

        $response->assertSessionHasErrors(['current_password']);

        $this->penilaiUser->refresh();
        $this->assertTrue(Hash::check('password123', $this->penilaiUser->password));
    }

    public function test_password_update_fails_when_same_as_old_or_unconfirmed(): void
    {
        // 1. Same password
        $response1 = $this->actingAs($this->penilaiUser)->put('/profile/password', [
            'current_password' => 'password123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response1->assertSessionHasErrors(['password']);

        // 2. Unconfirmed
        $response2 = $this->actingAs($this->penilaiUser)->put('/profile/password', [
            'current_password' => 'password123',
            'password' => 'PelindoBUMN2026!',
            'password_confirmation' => 'DifferentPassword!',
        ]);
        $response2->assertSessionHasErrors(['password']);
    }

    public function test_default_password_warning_banner_appears_when_using_default_password(): void
    {
        // Initially both users have password123
        $response = $this->actingAs($this->penilaiUser)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Peringatan Keamanan Kredensial');

        $adminResponse = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('Peringatan Keamanan Administrator');

        // Once changed, warning disappears
        $this->penilaiUser->password = Hash::make('CustomNewSecurePass123!');
        $this->penilaiUser->save();

        $responseAfter = $this->actingAs($this->penilaiUser)->get('/dashboard');
        $responseAfter->assertDontSee('Peringatan Keamanan Kredensial');
    }

    public function test_custom_error_pages_render_with_pelindo_branding(): void
    {
        // 404 Not Found
        $response404 = $this->get('/non-existent-page-url-xyz-123');
        $response404->assertStatus(404);
        $response404->assertSee('Halaman Tidak Ditemukan');
        $response404->assertSee('PT Pelindo Multi Terminal');

        // View rendering test for 403, 419, 429, 500
        $view403 = view('errors.403')->render();
        $this->assertStringContainsString('403', $view403);
        $this->assertStringContainsString('Akses Modul Dibatasi', $view403);

        $view419 = view('errors.419')->render();
        $this->assertStringContainsString('419', $view419);
        $this->assertStringContainsString('Sesi Browser Telah Berakhir', $view419);

        $view429 = view('errors.429')->render();
        $this->assertStringContainsString('429', $view429);
        $this->assertStringContainsString('Batas Percobaan Terlampaui', $view429);

        $view500 = view('errors.500')->render();
        $this->assertStringContainsString('500', $view500);
        $this->assertStringContainsString('Terjadi Kendala Teknis Server', $view500);
    }

    public function test_health_check_endpoint_returns_200_ok(): void
    {
        $response = $this->get('/up');
        $response->assertStatus(200);
    }

    public function test_logging_channel_is_configured_to_daily_with_retention(): void
    {
        $defaultChannel = config('logging.default');
        $this->assertEquals('daily', $defaultChannel);

        $dailyMaxFiles = config('logging.channels.daily.max_files');
        $this->assertEquals(14, $dailyMaxFiles);
    }
}

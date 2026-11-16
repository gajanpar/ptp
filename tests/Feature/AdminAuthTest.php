<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        Artisan::call('db:seed');
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_kepala_divisi_cannot_access_admin_dashboard(): void
    {
        $user = User::where('username', 'rudy')->first();
        $this->assertEquals('kepala_divisi', $user->role);

        $response = $this->actingAs($user)->get('/admin/dashboard');
        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->assertEquals('admin', $admin->role);

        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Portal Administrator SDM / IT');
        $response->assertSee('Executive Dashboard');
        $response->assertSee('Total Tenaga Alih Daya');
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $admin = User::where('username', 'admin')->first();
        $response = $this->actingAs($admin)->get('/login');
        $response->assertRedirect(route('admin.dashboard'));
    }
}

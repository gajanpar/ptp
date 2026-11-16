<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\EvaluationScoringService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EvaluationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
        Artisan::call('db:seed');
    }

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sistem Penilaian Kinerja');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_access_dashboard_when_authenticated(): void
    {
        $user = User::where('username', 'rudy')->first();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Rudy Kusuma');
        $response->assertSee('Total Karyawan');
    }

    public function test_scoring_service_calculates_accurately(): void
    {
        $keys = range(13, 29);
        $ratingMap = array_combine($keys, array_fill(0, 17, 5));

        $calc = EvaluationScoringService::calculate($ratingMap);
        $this->assertEquals(83.30, $calc['total_score']);
        $this->assertEquals('Baik', $calc['predicate']);

        // Karyawan dengan rating 6 (99.96 -> Sangat Baik)
        $ratingMapAll6 = array_combine($keys, array_fill(0, 17, 6));
        $calc6 = EvaluationScoringService::calculate($ratingMapAll6);
        $this->assertEquals(99.96, $calc6['total_score']);
        $this->assertEquals('Sangat Baik', $calc6['predicate']);

        // Karyawan rating 2 (Fix bug L1: skor 33.32 -> Perlu Perhatian)
        $ratingMapAll2 = array_combine($keys, array_fill(0, 17, 2));
        $calc2 = EvaluationScoringService::calculate($ratingMapAll2);
        $this->assertEquals('Perlu Perhatian', $calc2['predicate']);
    }

    public function test_penilaian_index_shows_tabs_and_employees(): void
    {
        $user = User::where('username', 'rudy')->first();
        $response = $this->actingAs($user)->get('/penilaian?month=4&year=2026');
        $response->assertStatus(200);
        $response->assertSee('Belum Dinilai');
        $response->assertSee('Sudah Dinilai');
    }

    public function test_laporan_index_shows_results(): void
    {
        $user = User::where('username', 'rudy')->first();
        $response = $this->actingAs($user)->get('/laporan?month=4&year=2026');
        $response->assertStatus(200);
        $response->assertSee('Agung Sugiarto');
        $response->assertSee('86.24');
    }

    public function test_user_can_submit_new_evaluation(): void
    {
        $user = User::where('username', 'rudy')->first();
        // Ambil karyawan belum dinilai di Mei 2026, misal Dede Suhaedi (ID 16)
        $emp = Employee::where('id', 16)->first();

        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 6)); // all 6
        $reasons = array_combine($keys, array_fill(0, 17, 'Kinerja sangat memuaskan di lapangan'));

        $postData = [
            'employee_id' => $emp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ];

        $response = $this->actingAs($user)->post('/penilaian/store', $postData);
        $response->assertRedirect('/penilaian?month=5&year=2026&tab=evaluated');

        $eval = Evaluation::where('employee_id', $emp->id)->where('period_month', 5)->where('period_year', 2026)->first();
        $this->assertNotNull($eval);
        $this->assertEquals(99.96, $eval->total_score);
        $this->assertEquals('Sangat Baik', $eval->predicate);
        $this->assertEquals(17, $eval->details()->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityPenetrationTest extends TestCase
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

    /**
     * Hacking Vector 1: Broken Object Level Authorization (BOLA / IDOR)
     * Penilai Operasi mencoba menilai personel TAD milik Divisi Keuangan.
     */
    public function test_hacking_attack_bola_idor_cross_division_evaluation_is_blocked(): void
    {
        // Karyawan di Divisi Keuangan (div 7)
        $keuanganEmp = Employee::where('division_id', 7)->firstOrFail();

        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $reasons = array_combine($keys, array_fill(0, 17, 'Percobaan IDOR cross division.'));

        // Rudy (divisi 5) meluncurkan request POST penilaian untuk karyawan divisi 7
        $response = $this->actingAs($this->penilaiOperasi)->post('/penilaian/store', [
            'employee_id' => $keuanganEmp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);

        // Harus ditolak dengan HTTP 403 Forbidden
        $response->assertStatus(403);

        // Pastikan tidak ada data yang tersimpan
        $this->assertDatabaseMissing('evaluations', [
            'employee_id' => $keuanganEmp->id,
            'period_month' => 5,
            'period_year' => 2026,
        ]);
    }

    /**
     * Hacking Vector 2: Tampered Period Lock Bypass
     * Penilai mencoba submit nilai ke periode tertutup tanpa izin approval dari Admin.
     */
    public function test_hacking_attack_period_lock_bypass_tampering_is_blocked(): void
    {
        $operasiEmp = Employee::where('division_id', 5)->firstOrFail();

        // Kunci periode Mei 2026
        PeriodManagement::setStatus(5, 5, 2026, 'closed');

        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $reasons = array_combine($keys, array_fill(0, 17, 'Bypass penguncian periode.'));

        $response = $this->actingAs($this->penilaiOperasi)->post('/penilaian/store', [
            'employee_id' => $operasiEmp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Akses penilaian untuk periode ini sedang terkunci', session('error'));

        $this->assertDatabaseMissing('evaluations', [
            'employee_id' => $operasiEmp->id,
            'period_month' => 5,
            'period_year' => 2026,
        ]);
    }

    /**
     * Hacking Vector 3: Stored XSS Injection in Evaluation Reasons
     * Penilai menginputkan payload XSS pada kolom catatan/alasan.
     */
    public function test_hacking_attack_stored_xss_in_evaluation_reasons_is_neutralized(): void
    {
        $operasiEmp = Employee::where('division_id', 5)->firstOrFail();
        PeriodManagement::setStatus(5, 5, 2026, 'open');

        $xssPayload = "<script>alert('XSS_ATTACK_SUCCESS')</script><img src=x onerror=alert(1)>";

        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 6));
        $reasons = array_combine($keys, array_fill(0, 17, $xssPayload));

        $response = $this->actingAs($this->penilaiOperasi)->post('/penilaian/store', [
            'employee_id' => $operasiEmp->id,
            'month' => 5,
            'year' => 2026,
            'ratings' => $ratings,
            'reasons' => $reasons,
        ]);

        $response->assertRedirect('/penilaian?month=5&year=2026&tab=evaluated');

        $eval = Evaluation::where('employee_id', $operasiEmp->id)
            ->where('period_month', 5)
            ->where('period_year', 2026)
            ->firstOrFail();

        // Admin membuka detail laporan via JSON endpoint
        $adminJson = $this->actingAs($this->adminUser)->getJson("/admin/reports/{$eval->id}");
        $adminJson->assertStatus(200);
        $adminJson->assertJsonFragment(['reason' => $xssPayload]);

        // Verifikasi bahwa view Blade untuk print juga meng-escape payload
        $printResponse = $this->actingAs($this->adminUser)->get("/admin/reports/{$eval->id}/print");
        $printResponse->assertStatus(200);
        $printResponse->assertDontSee("<script>alert('XSS_ATTACK_SUCCESS')</script>", false);
        $printResponse->assertSee('&lt;script&gt;alert(&#039;XSS_ATTACK_SUCCESS&#039;)&lt;/script&gt;', false);
    }

    /**
     * Hacking Vector 4: CSV Formula Injection (CWE-1236)
     * Data penilai atau master yang diawali simbol formula Excel (=, +, -, @) harus disanitasi dengan tanda petik tunggal.
     */
    public function test_hacking_attack_csv_formula_injection_is_defended(): void
    {
        // Buat karyawan dengan nama berbahaya (Formula injection payload)
        $maliciousEmp = Employee::create([
            'division_id' => 5,
            'nik' => 'TAD-9999',
            'name' => "=cmd|'/C calc'!A0",
            'position' => '@SUM(1+1)*cmd',
            'vendor_name' => '+PT Injeksi',
            'is_active' => true,
        ]);

        // Berikan evaluasi
        $eval = Evaluation::create([
            'employee_id' => $maliciousEmp->id,
            'user_id' => $this->penilaiOperasi->id,
            'division_id' => 5,
            'period_month' => 4,
            'period_year' => 2026,
            'total_score' => 83.30,
            'average_score' => 5.00,
            'predicate' => 'Baik',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Unduh CSV bulanan
        $response = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=monthly&month=4&year=2026');
        $response->assertStatus(200);

        $content = $response->streamedContent();

        // Pastikan formula tidak dibiarkan telanjang, melainkan di-prefix dengan tanda petik (')
        $this->assertStringContainsString("'=cmd|'/C calc'!A0", $content);
        $this->assertStringContainsString("'@SUM(1+1)*cmd", $content);
        $this->assertStringContainsString("'+PT Injeksi", $content);
    }

    /**
     * Hacking Vector 5: Type Juggling & Array Parameter Exploitation pada Login
     */
    public function test_hacking_attack_login_type_juggling_array_username_does_not_crash(): void
    {
        $response = $this->post('/login', [
            'username' => ['injected_array' => 'admin'],
            'password' => 'password123',
            'captcha' => '12345',
        ]);

        // Harus ditolak dengan validasi error, bukan unhandled exception 500
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['username']);
    }

    /**
     * Hacking Vector 6: Single-Use Captcha & Anti-Replay Defense
     */
    public function test_hacking_attack_captcha_replay_and_session_drain_is_prevented(): void
    {
        session(['captcha_code' => 'ABC99']);

        // Request pertama dengan captcha benar tapi password salah
        $firstAttempt = $this->post('/login', [
            'username' => 'rudy',
            'password' => 'wrong_password',
            'captcha' => 'ABC99',
        ]);

        $firstAttempt->assertSessionHasErrors(['username']);

        // Sesi captcha harus sudah hangus (forget)
        $this->assertNull(session('captcha_code'));

        // Request kedua mencoba me-replay kode captcha yang sama
        $replayAttempt = $this->post('/login', [
            'username' => 'rudy',
            'password' => 'password123',
            'captcha' => 'ABC99',
        ]);

        // Harus gagal di verifikasi captcha
        $replayAttempt->assertSessionHasErrors(['captcha']);
    }

    /**
     * Hacking Vector 7: Privilege Escalation (Penilai mencoba mengakses Modul Admin)
     */
    public function test_hacking_attack_privilege_escalation_to_admin_endpoints_is_forbidden(): void
    {
        $adminEndpoints = [
            ['GET', '/admin/dashboard'],
            ['GET', '/admin/periods'],
            ['POST', '/admin/periods/bulk-toggle', ['status' => 'open', 'period_month' => 1, 'period_year' => 2026]],
            ['GET', '/admin/users'],
            ['GET', '/admin/activity-logs'],
            ['GET', '/admin/system/backup'],
            ['POST', '/admin/system/backup', []],
            ['GET', '/admin/system/settings'],
            ['POST', '/admin/system/settings', ['company_name' => 'Hacked']],
        ];

        foreach ($adminEndpoints as $ep) {
            $method = $ep[0];
            $url = $ep[1];
            $data = $ep[2] ?? [];

            $res = ($method === 'GET')
                ? $this->actingAs($this->penilaiOperasi)->get($url)
                : $this->actingAs($this->penilaiOperasi)->post($url, $data);

            $res->assertRedirect('/dashboard');
            $res->assertSessionHas('error');
        }
    }

    /**
     * Hacking Vector 8: SQL Injection Attacks in Search & Filtering
     */
    public function test_hacking_attack_sqli_payloads_in_search_and_filters_are_safe(): void
    {
        $sqliPayloads = [
            "' OR '1'='1",
            "admin'--",
            '1; DROP TABLE users;--',
            "' UNION SELECT null, null, null, null, null--",
            "1' AND SLEEP(5)--",
        ];

        foreach ($sqliPayloads as $payload) {
            // Test di Admin Users search
            $res1 = $this->actingAs($this->adminUser)->get('/admin/users?search='.urlencode($payload));
            $res1->assertStatus(200);

            // Test di Admin Employees search
            $res2 = $this->actingAs($this->adminUser)->get('/admin/employees?search='.urlencode($payload));
            $res2->assertStatus(200);

            // Test di Activity Log search
            $res3 = $this->actingAs($this->adminUser)->get('/admin/activity-logs?search='.urlencode($payload));
            $res3->assertStatus(200);
        }

        // Pastikan tabel users masih ada dan utuh
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertGreaterThan(0, User::count());
    }

    /**
     * Hacking Vector 9: Path Traversal on Backup Download & Deletion
     */
    public function test_hacking_attack_backup_path_traversal_attempts_are_blocked(): void
    {
        $traversalFilenames = [
            '../../../../etc/passwd',
            '..\\..\\..\\windows\\win.ini',
            '%2e%2e%2f%2e%2e%2fconfig.php',
            '....//....//database.sqlite',
        ];

        foreach ($traversalFilenames as $filename) {
            $downloadRes = $this->actingAs($this->adminUser)->get('/admin/system/backup/download/'.urlencode($filename));
            // Route regex constraint or controller abort prevents traversal
            $this->assertTrue(in_array($downloadRes->status(), [404, 403, 302], true));

            $deleteRes = $this->actingAs($this->adminUser)->delete('/admin/system/backup/'.urlencode($filename));
            $this->assertTrue(in_array($deleteRes->status(), [404, 403, 302], true));
        }
    }

    /**
     * Hacking Vector 10: Inactive User Session Immediate Termination
     */
    public function test_hacking_attack_inactive_user_session_is_immediately_terminated(): void
    {
        $targetUser = User::where('username', 'mansur')->firstOrFail();

        // Mansur login aktif
        $this->actingAs($targetUser);
        $activeRes = $this->get('/dashboard');
        $activeRes->assertStatus(200);

        // Administrator mendisposisi penonaktifan akun Mansur
        $targetUser->update(['is_active' => false]);

        // Request berikutnya harus langsung mendeteksi deaktifasi dan mendepak user ke login
        $blockedRes = $this->get('/dashboard');
        $blockedRes->assertRedirect('/login');
        $this->assertFalse(Auth::check());
    }
}

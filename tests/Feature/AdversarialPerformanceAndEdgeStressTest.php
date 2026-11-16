<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdversarialPerformanceAndEdgeStressTest extends TestCase
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

        RateLimiter::clear('admin|127.0.0.1');
    }

    /**
     * Requirement 1: Bcrypt hashing cost configuration and latency (< 100ms per verification).
     */
    public function test_bcrypt_hashing_cost_configuration_and_latency(): void
    {
        $configuredRounds = config('hashing.bcrypt.rounds');
        $this->assertNotNull($configuredRounds, 'Bcrypt rounds must be configured');

        // Test standard cost 10 verification latency (enterprise standard)
        $cost10Hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 10]);
        $latenciesCost10 = [];
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            $valid = password_verify('password123', $cost10Hash);
            $latenciesCost10[] = (microtime(true) - $start) * 1000;
            $this->assertTrue($valid);
        }

        $avgCost10 = array_sum($latenciesCost10) / count($latenciesCost10);
        fwrite(STDERR, sprintf("\n[BENCHMARK] Bcrypt Cost 10 verification avg: %.2f ms\n", $avgCost10));
        $this->assertLessThan(
            100.0,
            $avgCost10,
            "Cost 10 password verification average latency ({$avgCost10}ms) exceeded 100ms threshold!"
        );

        // Test configured rounds latency
        $configuredHash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => (int) $configuredRounds]);
        $latenciesConfigured = [];
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            $valid = password_verify('password123', $configuredHash);
            $latenciesConfigured[] = (microtime(true) - $start) * 1000;
            $this->assertTrue($valid);
        }

        $avgConfigured = array_sum($latenciesConfigured) / count($latenciesConfigured);
        fwrite(STDERR, sprintf("[BENCHMARK] Bcrypt Cost %s (Configured in test env) avg: %.2f ms\n", $configuredRounds, $avgConfigured));
        $this->assertLessThan(
            100.0,
            $avgConfigured,
            "Configured cost ({$configuredRounds}) verification average latency ({$avgConfigured}ms) exceeded 100ms threshold!"
        );

        // Test production cost 12 (from .env)
        $cost12Hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);
        $latenciesCost12 = [];
        for ($i = 0; $i < 3; $i++) {
            $start = microtime(true);
            $valid = password_verify('password123', $cost12Hash);
            $latenciesCost12[] = (microtime(true) - $start) * 1000;
            $this->assertTrue($valid);
        }
        $avgCost12 = array_sum($latenciesCost12) / count($latenciesCost12);
        fwrite(STDERR, sprintf("[BENCHMARK] Bcrypt Cost 12 (Production .env setting) avg: %.2f ms\n", $avgCost12));

        // Test Laravel Hash::check latency
        $start = microtime(true);
        $hashValid = Hash::check('password123', $this->adminUser->password);
        $hashLatencyMs = (microtime(true) - $start) * 1000;
        fwrite(STDERR, sprintf("[BENCHMARK] Hash::check on seeded user avg: %.2f ms\n", $hashLatencyMs));
        $this->assertTrue($hashValid);
        $this->assertLessThan(
            100.0,
            $hashLatencyMs,
            "Hash::check latency ({$hashLatencyMs}ms) exceeded 100ms threshold!"
        );
    }

    /**
     * Requirement 2: Login admin throughput and response time under normal conditions (< 2s).
     */
    public function test_login_admin_response_time_under_two_seconds(): void
    {
        $latencies = [];

        for ($iteration = 0; $iteration < 5; $iteration++) {
            RateLimiter::clear('admin|127.0.0.1');

            // 1. Fetch fresh captcha
            $this->get('/captcha-image');
            $captcha = session('captcha_code');
            $this->assertNotEmpty($captcha);

            // 2. Measure POST /login response time
            $start = microtime(true);
            $response = $this->post('/login', [
                'username' => 'admin',
                'password' => 'password123',
                'captcha' => $captcha,
            ]);
            $elapsedMs = (microtime(true) - $start) * 1000;
            $latencies[] = $elapsedMs;

            $response->assertStatus(302);
            $response->assertRedirect('/admin/dashboard');
            $this->assertAuthenticatedAs($this->adminUser);

            // Log out for next iteration
            Auth::logout();
            $this->assertNull(Auth::user());
        }

        $avgLoginMs = array_sum($latencies) / count($latencies);
        $maxLoginMs = max($latencies);

        fwrite(STDERR, sprintf("\n[BENCHMARK] Login Admin POST - Avg: %.2f ms, Max: %.2f ms\n", $avgLoginMs, $maxLoginMs));

        $this->assertLessThan(
            2000.0,
            $maxLoginMs,
            "Max login admin response time ({$maxLoginMs}ms) exceeded 2.0s threshold!"
        );

        $this->assertLessThan(
            500.0,
            $avgLoginMs,
            "Average login admin response time ({$avgLoginMs}ms) exceeded 500ms SLA target!"
        );
    }

    /**
     * Requirement 3: Page transition responsiveness across dashboard, evaluations, and employees (< 2s).
     */
    public function test_page_transition_responsiveness_under_two_seconds(): void
    {
        $transitions = [
            ['name' => 'Admin Dashboard', 'route' => '/admin/dashboard', 'user' => $this->adminUser],
            ['name' => 'Admin Dashboard (Specific Period)', 'route' => '/admin/dashboard?month=4&year=2026', 'user' => $this->adminUser],
            ['name' => 'Kadiv Dashboard', 'route' => '/dashboard', 'user' => $this->penilaiUser],
            ['name' => 'Kadiv Dashboard (Specific Period)', 'route' => '/dashboard?month=5&year=2026', 'user' => $this->penilaiUser],
            ['name' => 'Master Karyawan', 'route' => '/admin/employees', 'user' => $this->adminUser],
            ['name' => 'Period Management', 'route' => '/admin/periods', 'user' => $this->adminUser],
            ['name' => 'Rubrik Penilaian', 'route' => '/admin/rubrik', 'user' => $this->adminUser],
            ['name' => 'User Management', 'route' => '/admin/users', 'user' => $this->adminUser],
            ['name' => 'Executive Report Monthly', 'route' => '/admin/reports?period_type=monthly&year=2026&month=4', 'user' => $this->adminUser],
            ['name' => 'Executive Report Annual', 'route' => '/admin/reports?period_type=annual&year=2026', 'user' => $this->adminUser],
            ['name' => 'CSV Export Monthly', 'route' => '/admin/reports/export?period_type=monthly&year=2026&month=4', 'user' => $this->adminUser],
            ['name' => 'CSV Export Annual', 'route' => '/admin/reports/export?period_type=annual&year=2026', 'user' => $this->adminUser],
            ['name' => 'Penilaian Index', 'route' => '/penilaian?month=5&year=2026', 'user' => $this->penilaiUser],
            ['name' => 'Laporan Index', 'route' => '/laporan?month=4&year=2026', 'user' => $this->penilaiUser],
        ];

        fwrite(STDERR, "\n[BENCHMARK] Page Transition Durations:\n");
        foreach ($transitions as $trans) {
            $start = microtime(true);
            $response = $this->actingAs($trans['user'])->get($trans['route']);
            $elapsedMs = (microtime(true) - $start) * 1000;

            fwrite(STDERR, sprintf("  - %-35s: %.2f ms\n", $trans['name'], $elapsedMs));

            $response->assertStatus(200);
            $this->assertLessThan(
                2000.0,
                $elapsedMs,
                "Page transition for '{$trans['name']}' ({$trans['route']}) took {$elapsedMs}ms, exceeding 2.0s threshold!"
            );
        }
    }

    /**
     * Requirement 4: Adversarial & Edge Query Inputs - Period parameters.
     * Verify zero unhandled 500 exceptions for negative, zero, out-of-range, and string periods.
     */
    public function test_adversarial_period_query_inputs_have_zero_unhandled_exceptions(): void
    {
        $malformedPeriods = [
            ['month' => -1, 'year' => 2026],
            ['month' => 0, 'year' => 2026],
            ['month' => 13, 'year' => 2026],
            ['month' => 9999, 'year' => 2026],
            ['month' => 'invalid_month', 'year' => 2026],
            ['month' => '5', 'year' => -2026],
            ['month' => '5', 'year' => 0],
            ['month' => '5', 'year' => 9999],
            ['month' => '5', 'year' => 'invalid_year'],
            ['month' => '<script>alert(1)</script>', 'year' => "2026' OR '1'='1"],
        ];

        $routesToProbe = [
            ['uri' => '/admin/dashboard', 'user' => $this->adminUser],
            ['uri' => '/dashboard', 'user' => $this->penilaiUser],
            ['uri' => '/penilaian', 'user' => $this->penilaiUser],
            ['uri' => '/laporan', 'user' => $this->penilaiUser],
            ['uri' => '/admin/reports', 'user' => $this->adminUser],
            ['uri' => '/admin/periods', 'user' => $this->adminUser],
        ];

        foreach ($routesToProbe as $route) {
            foreach ($malformedPeriods as $period) {
                $queryString = http_build_query([
                    'month' => $period['month'],
                    'year' => $period['year'],
                ]);
                $url = "{$route['uri']}?{$queryString}";

                try {
                    $response = $this->actingAs($route['user'])->withoutExceptionHandling()->get($url);
                    $status = $response->getStatusCode();
                } catch (\Throwable $e) {
                    $this->fail("CRITICAL: Unhandled exception on {$url}: ".get_class($e).' - '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
                }

                $this->assertNotEquals(
                    500,
                    $status,
                    "CRITICAL: Unhandled 500 exception on {$url}!"
                );

                // Should be either 200 (gracefully rendered), 302 (redirected), or 4xx (handled error)
                $this->assertTrue(
                    in_array($status, [200, 302, 400, 404, 422]),
                    "Expected 200, 302, 400, 404, or 422 on {$url}, got {$status}"
                );
            }
        }
    }

    /**
     * Requirement 5: Adversarial & Edge Query Inputs - Invalid and out-of-range IDs.
     * Verify zero unhandled 500 exceptions for negative IDs, non-existent IDs, and malicious strings.
     */
    public function test_adversarial_invalid_ids_have_zero_unhandled_exceptions(): void
    {
        $invalidIds = [-1, 0, 999999, 'abc', 'null', "' OR 1=1 --", '<script>'];

        // 1. Employee show / edit / delete
        foreach ($invalidIds as $id) {
            $url = "/admin/employees/{$id}";
            $response = $this->actingAs($this->adminUser)->get($url);
            $this->assertNotEquals(500, $response->getStatusCode(), "Unhandled 500 on {$url}");

            $deleteUrl = "/admin/employees/{$id}";
            $delResponse = $this->actingAs($this->adminUser)->delete($deleteUrl);
            $this->assertNotEquals(500, $delResponse->getStatusCode(), "Unhandled 500 on DELETE {$deleteUrl}");
        }

        // 2. Evaluation show in Laporan
        foreach ($invalidIds as $id) {
            $url = "/laporan/{$id}";
            $response = $this->actingAs($this->penilaiUser)->get($url);
            $this->assertNotEquals(500, $response->getStatusCode(), "Unhandled 500 on {$url}");
            $this->assertContains($response->getStatusCode(), [404, 403, 302]);
        }

        // 3. Evaluation print in Laporan
        foreach ($invalidIds as $id) {
            $url = "/laporan/{$id}/print";
            $response = $this->actingAs($this->penilaiUser)->get($url);
            $this->assertNotEquals(500, $response->getStatusCode(), "Unhandled 500 on {$url}");
            $this->assertContains($response->getStatusCode(), [404, 403, 302]);
        }

        // 4. Admin report show
        foreach ($invalidIds as $id) {
            $url = "/admin/reports/{$id}";
            $response = $this->actingAs($this->adminUser)->get($url);
            $this->assertNotEquals(500, $response->getStatusCode(), "Unhandled 500 on {$url}");
            $this->assertContains($response->getStatusCode(), [404, 302]);
        }

        // 5. Penilaian create with invalid employee_id
        foreach ($invalidIds as $id) {
            $url = "/penilaian/create?employee_id={$id}&month=5&year=2026";
            $response = $this->actingAs($this->penilaiUser)->get($url);
            $this->assertNotEquals(500, $response->getStatusCode(), "Unhandled 500 on {$url}");
            $this->assertContains($response->getStatusCode(), [404, 403, 302]);
        }
    }

    /**
     * Requirement 6: Adversarial Report Filters and Exports.
     * Verify invalid period_type, out-of-bounds division_id, and invalid format handle gracefully.
     */
    public function test_adversarial_report_filters_and_exports_zero_500_exceptions(): void
    {
        $adversarialFilters = [
            ['period_type' => 'invalid_type', 'year' => 2026, 'month' => 4, 'division_id' => 'all'],
            ['period_type' => 'monthly', 'year' => 2026, 'month' => 4, 'division_id' => -1],
            ['period_type' => 'monthly', 'year' => 2026, 'month' => 4, 'division_id' => 99999],
            ['period_type' => 'monthly', 'year' => 2026, 'month' => 4, 'division_id' => "' OR '1'='1"],
            ['period_type' => 'annual', 'year' => 2026, 'division_id' => 'nonexistent'],
        ];

        foreach ($adversarialFilters as $filter) {
            $query = http_build_query($filter);

            // Report view
            $viewUrl = "/admin/reports?{$query}";
            $viewResponse = $this->actingAs($this->adminUser)->get($viewUrl);
            $this->assertNotEquals(500, $viewResponse->getStatusCode(), "Unhandled 500 on {$viewUrl}");

            // Export endpoint
            $exportUrl = "/admin/reports/export?{$query}";
            $exportResponse = $this->actingAs($this->adminUser)->get($exportUrl);
            $this->assertNotEquals(500, $exportResponse->getStatusCode(), "Unhandled 500 on {$exportUrl}");
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceBenchmarkTest extends TestCase
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

    /**
     * Benchmark latency and response times across all primary endpoints.
     */
    public function test_endpoint_latencies_are_within_enterprise_sla(): void
    {
        $endpoints = [
            ['method' => 'GET', 'uri' => '/login', 'user' => null, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/captcha-image', 'user' => null, 'max_ms' => 150],
            ['method' => 'GET', 'uri' => '/dashboard', 'user' => $this->penilaiUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/penilaian?month=5&year=2026', 'user' => $this->penilaiUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/laporan?month=4&year=2026', 'user' => $this->penilaiUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/dashboard', 'user' => $this->adminUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/periods', 'user' => $this->adminUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/employees', 'user' => $this->adminUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/users', 'user' => $this->adminUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/rubrik', 'user' => $this->adminUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/reports?period_type=monthly&year=2026&month=4', 'user' => $this->adminUser, 'max_ms' => 350],
            ['method' => 'GET', 'uri' => '/admin/reports?period_type=annual&year=2026', 'user' => $this->adminUser, 'max_ms' => 450],
            ['method' => 'GET', 'uri' => '/admin/reports/export?period_type=monthly&year=2026&month=4', 'user' => $this->adminUser, 'max_ms' => 300],
            ['method' => 'GET', 'uri' => '/admin/reports/export?period_type=annual&year=2026', 'user' => $this->adminUser, 'max_ms' => 350],
        ];

        foreach ($endpoints as $ep) {
            $start = microtime(true);

            $caller = $ep['user'] ? $this->actingAs($ep['user']) : $this;
            $response = $caller->call($ep['method'], $ep['uri']);

            $elapsedMs = round((microtime(true) - $start) * 1000, 2);

            $response->assertStatus(200);
            $this->assertLessThan(
                $ep['max_ms'],
                $elapsedMs,
                "Endpoint {$ep['uri']} exceeded SLA limit of {$ep['max_ms']}ms (actual: {$elapsedMs}ms)"
            );
        }
    }

    /**
     * Empirical SQL query count verification: Proves eager loading works and zero N+1 queries occur.
     */
    public function test_sql_query_counts_prevent_n_plus_one_bottlenecks(): void
    {
        // 1. Penilaian index (Listing 32+ TADs): Must not execute N+1 queries
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->penilaiUser)->get('/penilaian?month=5&year=2026');
        $response->assertStatus(200);

        $penilaianQueries = DB::getQueryLog();
        $penilaianCount = count($penilaianQueries);
        $this->assertLessThanOrEqual(
            8,
            $penilaianCount,
            "Penilaian Index executed {$penilaianCount} queries! Potential N+1 query leak detected."
        );

        // 2. Laporan index (Listing evaluations with relations)
        DB::flushQueryLog();
        $response = $this->actingAs($this->penilaiUser)->get('/laporan?month=4&year=2026');
        $response->assertStatus(200);

        $laporanQueries = DB::getQueryLog();
        $laporanCount = count($laporanQueries);
        $this->assertLessThanOrEqual(
            6,
            $laporanCount,
            "Laporan Index executed {$laporanCount} queries! Potential N+1 query leak detected."
        );

        // 3. Admin Master Employees (Listing all TADs with division eager loaded)
        DB::flushQueryLog();
        $response = $this->actingAs($this->adminUser)->get('/admin/employees');
        $response->assertStatus(200);

        $empQueries = DB::getQueryLog();
        $empCount = count($empQueries);
        $this->assertLessThanOrEqual(
            10,
            $empCount,
            "Admin Employees executed {$empCount} queries! Potential N+1 query leak detected."
        );

        // 4. Admin Reports Annual (Aggregating 12 months across all 35 employees)
        DB::flushQueryLog();
        $response = $this->actingAs($this->adminUser)->get('/admin/reports?period_type=annual&year=2026');
        $response->assertStatus(200);

        $annualQueries = DB::getQueryLog();
        $annualCount = count($annualQueries);
        $this->assertLessThanOrEqual(
            8,
            $annualCount,
            "Admin Annual Report executed {$annualCount} queries! Annual calculation should group in memory, not query per employee."
        );

        // 5. Admin Reports Monthly: Division comparison must not N+1 (single grouped query)
        DB::flushQueryLog();
        $response = $this->actingAs($this->adminUser)->get('/admin/reports?period_type=monthly&year=2026&month=4');
        $response->assertStatus(200);

        $monthlyQueries = DB::getQueryLog();
        $monthlyCount = count($monthlyQueries);
        $this->assertLessThanOrEqual(
            8,
            $monthlyCount,
            "Admin Monthly Report executed {$monthlyCount} queries! Division comparison must group in memory, not query per division."
        );

        // 5. Admin Dashboard: Consolidated queries (preventing previous 38 queries leak)
        DB::flushQueryLog();
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $adminDashboardQueries = DB::getQueryLog();
        $adminDashboardCount = count($adminDashboardQueries);
        $this->assertLessThanOrEqual(
            6,
            $adminDashboardCount,
            "Admin Dashboard executed {$adminDashboardCount} queries! Query consolidation failed; must be <= 6 queries."
        );

        // 6. Kadiv Dashboard: Consolidated queries with whereDoesntHave
        DB::flushQueryLog();
        $response = $this->actingAs($this->penilaiUser)->get('/dashboard?month=5&year=2026');
        $response->assertStatus(200);

        $kadivDashboardQueries = DB::getQueryLog();
        $kadivDashboardCount = count($kadivDashboardQueries);
        $this->assertLessThanOrEqual(
            6,
            $kadivDashboardCount,
            "Kadiv Dashboard executed {$kadivDashboardCount} queries! Must be <= 6 queries."
        );
    }

    /**
     * Memory consumption benchmark: Verify peak memory stays well within optimal runtime limits.
     */
    public function test_memory_consumption_stays_optimal(): void
    {
        $initialMemory = memory_get_usage();

        // Run annual 12-month report aggregation for all divisions
        $response = $this->actingAs($this->adminUser)->get('/admin/reports?period_type=annual&year=2026');
        $response->assertStatus(200);

        // Run CSV export streaming
        $csvResponse = $this->actingAs($this->adminUser)->get('/admin/reports/export?period_type=annual&year=2026');
        $csvResponse->assertStatus(200);

        $deltaMemoryMb = round((memory_get_usage() - $initialMemory) / 1024 / 1024, 2);
        $peakMemoryMb = round(memory_get_peak_usage() / 1024 / 1024, 2);

        $this->assertLessThan(
            12.0,
            $deltaMemoryMb,
            "Memory delta {$deltaMemoryMb}MB exceeded maximum allowable increase of 12MB."
        );

        $this->assertLessThan(
            80.0,
            $peakMemoryMb,
            "Peak memory usage {$peakMemoryMb}MB exceeded maximum framework threshold of 80MB."
        );
    }

    /**
     * Database transaction throughput: Stress test submitting multiple evaluations sequentially.
     */
    public function test_evaluation_store_transaction_throughput(): void
    {
        $keys = range(13, 29);
        $ratings = array_combine($keys, array_fill(0, 17, 5));
        $reasons = array_combine($keys, array_fill(0, 17, 'Uji coba stress test performa transaksi.'));

        $employees = Employee::where('division_id', 5)->where('is_active', true)->take(5)->get();

        $start = microtime(true);

        foreach ($employees as $emp) {
            $response = $this->actingAs($this->penilaiUser)->post('/penilaian/store', [
                'employee_id' => $emp->id,
                'month' => 5,
                'year' => 2026,
                'ratings' => $ratings,
                'reasons' => $reasons,
            ]);

            $response->assertRedirect('/penilaian?month=5&year=2026&tab=evaluated');
        }

        $totalDurationMs = round((microtime(true) - $start) * 1000, 2);
        $avgPerTxMs = round($totalDurationMs / count($employees), 2);

        // Assert average transaction time (insert evaluation header + 17 detail rows) is under 150ms
        $this->assertLessThan(
            150.0,
            $avgPerTxMs,
            "Average evaluation transaction time {$avgPerTxMs}ms exceeded threshold of 150ms."
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdversarialDashboardStressTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $kadivUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('username', 'admin')->firstOrFail();
        $this->kadivUser = User::where('username', 'rudy')->firstOrFail();
    }

    /**
     * Requirement 1: Empirically verify that /admin/dashboard does not execute more than 6 database queries under full seeded data.
     */
    public function test_admin_dashboard_query_count_under_seeded_data(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Admin Dashboard executed {$count} queries under seeded data (limit is <= 6). Queries: ".json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement 2: Empirically verify that /dashboard (Kadiv) does not execute more than 6 database queries under full seeded data.
     */
    public function test_kadiv_dashboard_query_count_under_seeded_data(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->kadivUser)->get('/dashboard?month=5&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Kadiv Dashboard executed {$count} queries under seeded data (limit is <= 6). Queries: ".json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement 2b: Empirically verify Kadiv dashboard query count when period has existing evaluations.
     */
    public function test_kadiv_dashboard_query_count_with_existing_evaluations(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->kadivUser)->get('/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Kadiv Dashboard with existing evaluations executed {$count} queries! Limit is <= 6. Queries: ".json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement 3a: Edge Condition - Period with Zero Evaluations.
     */
    public function test_dashboards_with_zero_evaluations(): void
    {
        // Period 1/2025 has zero evaluations
        $this->assertEquals(0, Evaluation::where('period_month', 1)->where('period_year', 2025)->count());

        // Admin Dashboard with 0 evaluations
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=1&year=2025');
        $response->assertStatus(200);
        $response->assertSee('Belum Ada Data');

        $adminQueries = DB::getQueryLog();
        $adminCount = count($adminQueries);
        $this->assertLessThanOrEqual(
            6,
            $adminCount,
            "Admin Dashboard with zero evaluations executed {$adminCount} queries (must be <= 6)."
        );

        // Kadiv Dashboard with 0 evaluations
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->kadivUser)->get('/dashboard?month=1&year=2025');
        $response->assertStatus(200);
        $response->assertSee('Belum Ada Evaluasi Selesai');

        $kadivQueries = DB::getQueryLog();
        $kadivCount = count($kadivQueries);
        $this->assertLessThanOrEqual(
            6,
            $kadivCount,
            "Kadiv Dashboard with zero evaluations executed {$kadivCount} queries (must be <= 6)."
        );
    }

    /**
     * Requirement 3d: Edge Condition - Evaluation referencing an inactive division causes N+1 lazy load leak.
     */
    public function test_admin_dashboard_inactive_division_lazy_load_leak(): void
    {
        // Deactivate division of Rudy (division id 5)
        $division = Division::find(5);
        $division->update(['is_active' => false]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Admin Dashboard with deactivated division executed {$count} queries due to lazy load! Queries: ".json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement 3b: Edge Condition - Period with pending edit requests.
     */
    public function test_admin_dashboard_with_pending_edit_requests(): void
    {
        $division = Division::first();
        $employee = Employee::first();

        // Seed 5 pending edit requests
        for ($i = 0; $i < 5; $i++) {
            EditRequest::create([
                'requested_by' => $this->kadivUser->id,
                'division_id' => $division->id,
                'employee_id' => $employee->id,
                'evaluation_id' => null,
                'request_type' => 'division_access',
                'period_month' => 4,
                'period_year' => 2026,
                'reason' => "Adversarial stress test edit request #{$i}",
                'status' => 'pending',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Admin Dashboard with pending edit requests executed {$count} queries! Limit is <= 6. Queries logged: ".json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement 3c: Edge Condition - All active divisions with varied data.
     */
    public function test_admin_dashboard_all_active_divisions(): void
    {
        $activeDivisionsCount = Division::where('is_active', true)->count();
        $this->assertGreaterThan(0, $activeDivisionsCount);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $this->assertLessThanOrEqual(6, count($queries));
    }

    /**
     * Requirement 4: Measure memory usage delta across multiple sequential dashboard calls.
     */
    public function test_memory_usage_stability_across_sequential_calls(): void
    {
        gc_collect_cycles();
        $initialMemory = memory_get_usage();

        // Sequential calls to Admin Dashboard
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
            $response->assertStatus(200);
        }

        // Sequential calls to Kadiv Dashboard
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->kadivUser)->get('/dashboard?month=5&year=2026');
            $response->assertStatus(200);
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();

        $deltaMb = round(($finalMemory - $initialMemory) / 1024 / 1024, 2);
        $peakMb = round($peakMemory / 1024 / 1024, 2);

        // Memory delta across 20 full dashboard renders should not leak heavily (< 8MB)
        $this->assertLessThan(
            8.0,
            $deltaMb,
            "Memory leaked {$deltaMb}MB across 20 sequential dashboard requests! Possible uncollected objects or static accumulation."
        );

        // Peak memory should remain well below serverless limit (e.g. 80MB)
        $this->assertLessThan(
            80.0,
            $peakMb,
            "Peak memory reached {$peakMb}MB, exceeding 80MB limit."
        );
    }

    /**
     * Requirement 3e: Adversarial Stress - Zero RouteNotFoundException or 500 errors across all 12 months in both open and closed states.
     */
    public function test_zero_500_or_route_not_found_across_all_periods_and_lock_states(): void
    {
        $years = [2025, 2026];
        foreach ($years as $year) {
            for ($month = 1; $month <= 12; $month++) {
                // Test in locked state
                PeriodManagement::updateOrCreate(
                    ['division_id' => $this->kadivUser->division_id, 'period_month' => $month, 'period_year' => $year],
                    ['status' => 'closed', 'updated_by' => $this->adminUser->id]
                );

                $responseAdminClosed = $this->actingAs($this->adminUser)->get("/admin/dashboard?month={$month}&year={$year}");
                $responseAdminClosed->assertStatus(200);

                $responseKadivClosed = $this->actingAs($this->kadivUser)->get("/dashboard?month={$month}&year={$year}");
                $responseKadivClosed->assertStatus(200);

                // Test in open state
                PeriodManagement::updateOrCreate(
                    ['division_id' => $this->kadivUser->division_id, 'period_month' => $month, 'period_year' => $year],
                    ['status' => 'open', 'updated_by' => $this->adminUser->id]
                );

                $responseAdminOpen = $this->actingAs($this->adminUser)->get("/admin/dashboard?month={$month}&year={$year}");
                $responseAdminOpen->assertStatus(200);

                $responseKadivOpen = $this->actingAs($this->kadivUser)->get("/dashboard?month={$month}&year={$year}");
                $responseKadivOpen->assertStatus(200);
            }
        }
    }

    /**
     * Requirement 1b & 2c: Scoped view composer eliminates system_settings query on cold views.
     */
    public function test_cold_view_cache_query_counts_exclude_system_settings(): void
    {
        Artisan::call('view:clear');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $adminQueries = DB::getQueryLog();
        $this->assertLessThanOrEqual(6, count($adminQueries));
        foreach ($adminQueries as $q) {
            $this->assertStringNotContainsString('system_settings', $q['query'], 'Admin dashboard queried system_settings!');
        }

        Artisan::call('view:clear');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->kadivUser)->get('/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $kadivQueries = DB::getQueryLog();
        $this->assertLessThanOrEqual(6, count($kadivQueries));
        foreach ($kadivQueries as $q) {
            $this->assertStringNotContainsString('system_settings', $q['query'], 'Kadiv dashboard queried system_settings!');
        }
    }

    /**
     * Requirement 1c: Extreme Adversarial Scenario - Multiple deactivated divisions AND multiple pending requests simultaneously.
     */
    public function test_combined_stress_pending_requests_and_inactive_divisions_together(): void
    {
        // Deactivate 2 divisions
        Division::whereIn('id', [4, 5])->update(['is_active' => false]);

        $division = Division::where('is_active', true)->first();
        $employee = Employee::first();

        // Seed 10 pending edit requests
        for ($i = 0; $i < 10; $i++) {
            EditRequest::create([
                'requested_by' => $this->kadivUser->id,
                'division_id' => $division->id,
                'employee_id' => $employee->id,
                'evaluation_id' => null,
                'request_type' => 'division_access',
                'period_month' => 4,
                'period_year' => 2026,
                'reason' => "Mass stress edit request #{$i}",
                'status' => 'pending',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Admin Dashboard under combined extreme stress executed {$count} queries (limit <= 6). Queries: ".json_encode(array_column($queries, 'query'), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Requirement 2d: Edge Condition - Kadiv user with null division_id.
     */
    public function test_kadiv_dashboard_without_division(): void
    {
        $userWithoutDivision = User::create([
            'name' => 'Kadiv No Division',
            'username' => 'kadiv_nodiv',
            'password' => bcrypt('password'),
            'role' => 'kepala_divisi',
            'division_id' => null,
            'is_active' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($userWithoutDivision)->get('/dashboard?month=5&year=2026');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        $count = count($queries);

        $this->assertLessThanOrEqual(
            6,
            $count,
            "Kadiv Dashboard without division executed {$count} queries (limit <= 6)."
        );
    }

    /**
     * Empirical Telemetry Collector: Records exact query counts and SQL statements across all scenarios.
     */
    public function test_dump_query_telemetry(): void
    {
        $telemetry = [];

        // Scenario 1: Admin Dashboard Seeded (month 4, 2026)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $telemetry['admin_seeded'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        // Scenario 2: Admin Dashboard with 5 Pending Requests
        $division = Division::first();
        $employee = Employee::first();
        for ($i = 0; $i < 5; $i++) {
            EditRequest::create([
                'requested_by' => $this->kadivUser->id,
                'division_id' => $division->id,
                'employee_id' => $employee->id,
                'request_type' => 'division_access',
                'period_month' => 4,
                'period_year' => 2026,
                'reason' => "Telemetry test #{$i}",
                'status' => 'pending',
            ]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $telemetry['admin_with_pending_requests'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        // Scenario 3: Admin Dashboard with Inactive Division
        Division::find(5)->update(['is_active' => false]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->adminUser)->get('/admin/dashboard?month=4&year=2026');
        $telemetry['admin_with_inactive_division'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        // Scenario 4: Admin Dashboard Zero Evaluations (month 1, 2025)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->adminUser)->get('/admin/dashboard?month=1&year=2025');
        $telemetry['admin_zero_evaluations'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        // Scenario 5: Kadiv Dashboard Seeded Open (month 5, 2026)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->kadivUser)->get('/dashboard?month=5&year=2026');
        $telemetry['kadiv_seeded_open'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        // Scenario 6: Kadiv Dashboard Seeded Closed (month 4, 2026)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->kadivUser)->get('/dashboard?month=4&year=2026');
        $telemetry['kadiv_seeded_closed'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        // Scenario 7: Kadiv Dashboard Zero Evaluations (month 1, 2025)
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->kadivUser)->get('/dashboard?month=1&year=2025');
        $telemetry['kadiv_zero_evaluations'] = [
            'count' => count(DB::getQueryLog()),
            'queries' => array_column(DB::getQueryLog(), 'query'),
        ];

        $reportPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'spktad_query_telemetry.json';
        file_put_contents($reportPath, json_encode($telemetry, JSON_PRETTY_PRINT));
        $this->assertFileExists($reportPath);
    }
}

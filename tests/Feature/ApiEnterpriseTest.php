<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCategory;
use App\Models\EvaluationSubcategory;
use App\Models\PeriodManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $evaluator;

    protected Division $divOps;

    protected Division $divTeknik;

    protected Employee $employeeOps;

    protected Employee $employeeTeknik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->divOps = Division::create(['name' => 'Divisi Operasi', 'is_active' => true]);
        $this->divTeknik = Division::create(['name' => 'Divisi Teknik', 'is_active' => true]);

        $this->admin = User::create([
            'username' => 'admin_sdm',
            'name' => 'Admin SDM Cabang',
            'email' => 'admin@pelindo.co.id',
            'password' => Hash::make('Secret123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->evaluator = User::create([
            'username' => 'kadiv_ops',
            'name' => 'Kepala Divisi Operasi',
            'email' => 'kadiv.ops@pelindo.co.id',
            'password' => Hash::make('Secret123!'),
            'role' => 'kepala_divisi',
            'division_id' => $this->divOps->id,
            'is_active' => true,
        ]);

        $this->employeeOps = Employee::create([
            'nik' => 'TAD-OPS-001',
            'name' => 'Budi Santoso',
            'division_id' => $this->divOps->id,
            'position' => 'Operator Crane Dermaga',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        $this->employeeTeknik = Employee::create([
            'nik' => 'TAD-TEK-001',
            'name' => 'Agus Teknik',
            'division_id' => $this->divTeknik->id,
            'position' => 'Teknisi Mekanikal',
            'vendor_name' => 'PT Pelindo Daya Sejahtera',
            'is_active' => true,
        ]);

        // Seed basic rubric
        $catAmanah = EvaluationCategory::create(['name' => 'Amanah', 'order_index' => 1]);
        EvaluationSubcategory::create([
            'category_id' => $catAmanah->id,
            'name' => 'Integritas & Kejujuran',
            'order_index' => 1,
            'max_score' => 5.88,
        ]);
        EvaluationSubcategory::create([
            'category_id' => $catAmanah->id,
            'name' => 'Tanggung Jawab Lapangan',
            'order_index' => 2,
            'max_score' => 5.88,
        ]);

        // Open current period for divOps
        PeriodManagement::setStatus($this->divOps->id, 5, 2026, 'open', $this->admin->id);
    }

    public function test_user_can_login_via_api_and_receive_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'kadiv_ops',
            'password' => 'Secret123!',
            'device_name' => 'Android Evaluator PTP',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'code' => 200,
            ])
            ->assertJsonStructure([
                'data' => [
                    'token_type',
                    'access_token',
                    'user' => [
                        'id',
                        'name',
                        'username',
                        'email',
                        'role',
                        'division_id',
                    ],
                    'abilities',
                ],
            ]);
    }

    public function test_user_can_login_via_api_using_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'kadiv.ops@pelindo.co.id',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'username' => 'kadiv_ops',
                        'email' => 'kadiv.ops@pelindo.co.id',
                    ],
                ],
            ]);
    }

    public function test_inactive_user_cannot_login_via_api(): void
    {
        $this->evaluator->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'kadiv_ops',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 403,
            ]);
    }

    public function test_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'kadiv_ops',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'code' => 401,
            ]);
    }

    public function test_authenticated_user_can_fetch_me_profile(): void
    {
        $token = $this->evaluator->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'username' => 'kadiv_ops',
                    'role' => 'kepala_divisi',
                    'division_id' => $this->divOps->id,
                ],
            ]);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $token = $this->evaluator->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        auth()->forgetGuards();

        // Subsequent call must fail with 401
        $subsequent = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $subsequent->assertStatus(401);
    }

    public function test_sla_cutoff_status_endpoint(): void
    {
        $token = $this->evaluator->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/periods/current/sla-status?month=5&year=2026');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'period' => [
                        'month' => 5,
                        'year' => 2026,
                    ],
                    'cutoff' => [
                        'day_of_month' => 25,
                    ],
                    'enforcement' => [
                        'is_locked' => false,
                        'can_input' => true,
                    ],
                ],
            ]);
    }

    public function test_evaluator_cannot_see_pds_contract_recommendations(): void
    {
        Evaluation::create([
            'employee_id' => $this->employeeOps->id,
            'user_id' => $this->evaluator->id,
            'division_id' => $this->divOps->id,
            'period_month' => 5,
            'period_year' => 2026,
            'total_score' => 88.5,
            'average_score' => 5.2,
            'predicate' => 'Sangat Baik',
            'status' => 'submitted',
        ]);

        $evaluatorToken = $this->evaluator->createToken('evaluator')->plainTextToken;
        $evaluatorResponse = $this->withHeader('Authorization', 'Bearer '.$evaluatorToken)
            ->getJson('/api/v1/dashboard/metrics?month=5&year=2026');

        $evaluatorResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'contract_recommendations' => null,
                ],
            ]);
    }

    public function test_admin_can_see_pds_contract_recommendations(): void
    {
        Evaluation::create([
            'employee_id' => $this->employeeOps->id,
            'user_id' => $this->evaluator->id,
            'division_id' => $this->divOps->id,
            'period_month' => 5,
            'period_year' => 2026,
            'total_score' => 88.5,
            'average_score' => 5.2,
            'predicate' => 'Sangat Baik',
            'status' => 'submitted',
        ]);

        $adminToken = $this->admin->createToken('admin')->plainTextToken;
        $adminResponse = $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson('/api/v1/dashboard/metrics?month=5&year=2026');

        $adminResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'contract_recommendations' => [
                        'perpanjang' => ['count' => 1],
                    ],
                ],
            ]);
    }

    public function test_evaluations_unassessed_queue(): void
    {
        $token = $this->evaluator->createToken('evaluator')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/evaluations/unassessed?month=5&year=2026');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_unassessed' => 1,
                    'employees' => [
                        [
                            'id' => $this->employeeOps->id,
                            'name' => 'Budi Santoso',
                        ],
                    ],
                ],
            ]);
    }

    public function test_evaluation_submit_with_cross_division_idor_prevention(): void
    {
        $token = $this->evaluator->createToken('evaluator')->plainTextToken;

        // Try evaluating employee from teknik (different division)
        $subcategories = EvaluationSubcategory::all();
        $ratings = [];
        foreach ($subcategories as $sub) {
            $ratings[$sub->id] = 5;
        }

        $idorAttempt = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/evaluations/submit', [
                'employee_id' => $this->employeeTeknik->id,
                'month' => 5,
                'year' => 2026,
                'ratings' => $ratings,
            ]);

        // Must be rejected with 403 Forbidden
        $idorAttempt->assertStatus(403);

        // Submit for own division employee
        $validAttempt = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/evaluations/submit', [
                'employee_id' => $this->employeeOps->id,
                'month' => 5,
                'year' => 2026,
                'ratings' => $ratings,
            ]);

        $validAttempt->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'employee_id' => $this->employeeOps->id,
                    'employee_name' => 'Budi Santoso',
                ],
            ]);

        $this->assertDatabaseHas('evaluations', [
            'employee_id' => $this->employeeOps->id,
            'period_month' => 5,
            'period_year' => 2026,
        ]);
    }

    public function test_batch_evaluation_submit(): void
    {
        $token = $this->evaluator->createToken('evaluator')->plainTextToken;

        $subcategories = EvaluationSubcategory::all();
        $ratings = [];
        foreach ($subcategories as $sub) {
            $ratings[$sub->id] = 6;
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/evaluations/batch-submit', [
                'month' => 5,
                'year' => 2026,
                'evaluations' => [
                    [
                        'employee_id' => $this->employeeOps->id,
                        'ratings' => $ratings,
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_submitted' => 1,
                    'total_success' => 1,
                    'total_failed' => 0,
                ],
            ]);
    }

    public function test_batch_evaluation_submit_enforces_sla_period_lock_and_validates_subcategories(): void
    {
        $token = $this->evaluator->createToken('evaluator')->plainTextToken;

        // 1. Reject invalid subcategory IDs even in an open period (05/2026)
        $badSubRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/evaluations/batch-submit', [
                'month' => 5,
                'year' => 2026,
                'evaluations' => [
                    [
                        'employee_id' => $this->employeeOps->id,
                        'ratings' => [999998 => 5, 999999 => 5],
                    ],
                ],
            ]);

        $badSubRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_success' => 0,
                    'total_failed' => 1,
                ],
            ]);

        // 2. Lock period 04/2026 and verify batch-submit enforces SLA lock (no bypass)
        PeriodManagement::setStatus($this->divOps->id, 4, 2026, 'closed', $this->admin->id);
        $validRatings = [];
        foreach (EvaluationSubcategory::all() as $sub) {
            $validRatings[$sub->id] = 5;
        }

        $lockedRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/evaluations/batch-submit', [
                'month' => 4,
                'year' => 2026,
                'evaluations' => [
                    [
                        'employee_id' => $this->employeeOps->id,
                        'ratings' => $validRatings,
                    ],
                ],
            ]);

        $lockedRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_success' => 0,
                    'total_failed' => 1,
                ],
            ]);
        $this->assertDatabaseMissing('evaluations', [
            'employee_id' => $this->employeeOps->id,
            'period_month' => 4,
            'period_year' => 2026,
        ]);
    }
}

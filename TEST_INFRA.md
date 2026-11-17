# Test Infrastructure Specification: SPKTAD Modernization (R1 - R6)

**Project**: Sistem Penilaian Kinerja Tenaga Alih Daya (SPKTAD)  
**Entity**: PT Pelabuhan Indonesia (Persero) - Branch Cirebon  
**Environment**: Laravel 13 on PHP 8.4 / Vercel Serverless (sin1 Edge) / Supabase PostgreSQL  
**Author**: `e2e_test_writer_1` (Role: `teamwork_test_writer`)  
**Date**: 2026-11-10  

---

## 1. Architectural Overview

The test infrastructure provides an automated, opaque-box, multi-tiered verification harness for SPKTAD covering requirements R1 through R6:
- **R1**: Performance optimization of login latency (< 2s) and page transitions (< 2s), query consolidation, and Bcrypt tuning.
- **R2**: Master Karyawan controls (delete action for Admin and Kepala Divisi, audit protection) and UI button separation (CSV vs Excel, Print vs Save PDF).
- **R3**: Comprehensive automated logic and functional test coverage across all application modules.
- **R4**: Security hardening, credential migration from code to Vercel env, Supabase NIK encryption, BOLA/IDOR prevention, and git safety.
- **R5**: PT Pelindo corporate UI/UX standardization (corporate blue palette `#005b9f`/`#00223B`/`#00a3e0`, table layout, visual indicators).
- **R6**: Tri-workspace synchronization and strict deployment protocols (`ptp:main`).

---

## 2. The 4-Tier Test Suite Hierarchy

The primary test implementation resides in `tests/Feature/SpktadModernizationE2ETest.php` and executes under PHPUnit 12 / Laravel Feature Testing:

```
+-------------------------------------------------------------------------------+
|                        SPKTAD MODERNIZATION E2E SUITE                         |
+-------------------------------------------------------------------------------+
| Tier 1: Feature Coverage (Core Requirements Verification)                     |
|  - T1.1: R1 Login Latency Benchmark & Authentication Redirect                 |
|  - T1.2: R1 Dashboard Query Count Consolidation Limit                         |
|  - T1.3: R2 Master Karyawan Admin Delete Unassessed Employee                 |
|  - T1.4: R2 Master Karyawan Admin Delete Evaluated Employee Audit Protection  |
|  - T1.5: R2 Master Karyawan Action Buttons Rendered in DOM                    |
|  - T1.6: R2 Master Karyawan Kepala Divisi Division Authorization              |
|  - T1.7: R2 Separate Export CSV Format (text/csv, .csv, UTF-8 BOM)            |
|  - T1.8: R2 Separate Export Excel Format (application/vnd.ms-excel, .xls/.xlsx) |
|  - T1.9: R2 Separate Print (window.print()) vs Save PDF Layout                |
|  - T1.10: R4 Security Headers & Config Credential Sanitization                |
|  - T1.11: R5 Corporate Pelindo Branding Color Tokens & Headers                |
+-------------------------------------------------------------------------------+
| Tier 2: Boundary & Corner Cases                                               |
|  - T2.1: Negative Access - Guest/Unauthenticated Delete Blocked               |
|  - T2.2: Negative Access - Cross-Division Employee Deletion Blocked           |
|  - T2.3: Negative Access - Cross-Division Edit Request IDOR Blocked           |
|  - T2.4: Negative Access - Deactivated Account Login Rejected                 |
|  - T2.5: Negative Access - Invalid Captcha Code Replay Prevention             |
|  - T2.6: Boundary - Rate Limiting Lockout After 5 Failed Attempts             |
|  - T2.7: Boundary - Exporting Report With Empty Dataset (Zero 500 Errors)     |
|  - T2.8: Boundary - Exporting Large Dataset Across All Divisions              |
|  - T2.9: Boundary - Employee Deletion With Edit Request History Blocked        |
+-------------------------------------------------------------------------------+
| Tier 3: Cross-Feature Combinations                                            |
|  - T3.1: Full Employee Lifecycle (Create -> Evaluate -> Audit Block ->         |
|          Deactivate -> Edit Request -> Approval)                              |
|  - T3.2: Multi-Step Navigation, Multi-Format Export & Session Integrity       |
|  - T3.3: Period Lock -> Evaluation Block -> Period Unlock -> Acceptance Flow   |
+-------------------------------------------------------------------------------+
| Tier 4: Real-World Operational Scenarios                                      |
|  - T4.1: End-to-End Administrator Operational Cycle (Login -> Metrics ->      |
|          Employees -> Backup -> Monthly Export -> Print Record -> Logout)     |
|  - T4.2: End-to-End Kepala Divisi Operational Cycle (Login -> Dashboard ->    |
|          17-Point AKHLAK Evaluation -> Report -> Edit Request -> Logout)      |
+-------------------------------------------------------------------------------+
```

---

## 3. Test Execution Protocols

### 3.1 Targeted E2E Test Suite Run
Execute the newly introduced 4-tier modernization test suite:
```powershell
Set-Location "C:\Users\Gajanpar\.vscode\ptp"
php artisan test --filter=SpktadModernizationE2ETest
```

### 3.2 Full Application Regression Test Suite
Execute all 17 feature test suites + unit tests (188 total tests):
```powershell
Set-Location "C:\Users\Gajanpar\.vscode\ptp"
php artisan test --compact
```

### 3.3 Live Public Deployment Opaque-Box Check
Execute the PowerShell verification harness against live staging/production:
```powershell
powershell -ExecutionPolicy Bypass -File tests\verify_public_deploy.ps1 -TargetUrl "https://spktad.vercel.app"
```

---

## 4. Test Hermeticity & State Isolation

Every test case in `SpktadModernizationE2ETest` adheres to strict isolation rules:
1. **Database Reset**: Uses `Illuminate\Foundation\Testing\RefreshDatabase` with SQLite `:memory:` database.
2. **Fresh Migration & Seeding**: Runs `Artisan::call('migrate')` and `Artisan::call('db:seed')` during `setUp()` to instantiate authoritative Pelindo baseline data.
3. **Hermetic Rate Limiter**: Proactively clears throttle keys (`login:admin:127.0.0.1`, `login:rudy:127.0.0.1`, `login:rindam:127.0.0.1`) prior to test execution to prevent cross-test rate limit contamination.
4. **Captchas**: Captchas are generated per test session using `withSession(['captcha_code' => '...'])` to guarantee single-use validity without external dependencies.

---

## 5. Tri-Workspace Parity

In compliance with Requirement R6, all test code and infrastructure documents are mirrored across all three project workspaces:
- **Primary Workspace**: `C:\Users\Gajanpar\.vscode\ptp`
- **Worktree Root**: `C:\Users\Gajanpar\.gemini\antigravity\worktrees\ptp\prepare_real_data_transition`
- **Nested Copy**: `C:\Users\Gajanpar\.gemini\antigravity\worktrees\ptp\prepare_real_data_transition\.vscode\ptp`

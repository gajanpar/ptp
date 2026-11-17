# Changelog

All notable changes to the **SPKTAD (Sistem Penilaian Kinerja Tenaga Alih Daya) - PTP Nonpetikemas Branch Cirebon** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.9.0] - 2026-11-03

### Added
- **Pilar 4: Transisi Data Riil & Pembersihan Data Transaksi Dummy**:
  - `ProductionSeeder` (`database/seeders/ProductionSeeder.php`): Seeder resmi mode produksi murni untuk PT Pelindo Multi Terminal Branch Cirebon yang menginisiasi 3 Divisi aktif, 4 Akun Resmi (Admin, Rudy Kusuma, Mansur, Rindam Agalvani), 12 Kategori & 17 Subkategori Rubrik AKHLAK, Pengaturan Sistem Cabang, dan 35 Personel Master TAD Riil dengan NIK, Posisi, dan Mitra Vendor PT Pelindo Daya Sejahtera (PDS). Bersih 100% dari transaksi dummy (0 evaluations, 0 details, 0 test edit requests).
  - `CleanDummyDataCommand` (`app/Console/Commands/CleanDummyDataCommand.php` / `php artisan ptp:clean-dummy`): Perintah CLI pembersihan transaksi dummy pengujian (evaluations, details, edit_requests) secara atomik dalam transaksi database dengan dukungan mode simulasi `--dry-run`, konfirmasi interaktif, dan integrasi otomatis dengan Data Quality Audit pasca pembersihan.
  - `DataQualityService` (`app/Services/DataQualityService.php`): Framework audit kualitas data korporat mencakup 5 dimensi utama: Kelengkapan (Completeness), Keunikan (Uniqueness), Validitas Format & Skala (Validity), Integritas Relasional Kunci Asing (Referential Integrity), dan Status Higienitas Data (Hygiene Status).
  - `ValidateDataQualityCommand` (`app/Console/Commands/ValidateDataQualityCommand.php` / `php artisan ptp:data-quality`): Antarmuka CLI diagnostik kesehatan basis data dengan visualisasi tabel ringkas dan opsi ekspor JSON (`--json`) untuk pipeline CI/CD.
  - `DataTransitionAndQualityTest` (`tests/Feature/DataTransitionAndQualityTest.php`): 5 pengujian otomatis (31 assertions) memverifikasi inisiasi ProductionSeeder, eksekusi pembersihan dummy, proteksi dry-run, dan pendeteksian anomali kualitas data.
- **Pengerasan Keamanan Siber & Uji Penetrasi (Hacking-Side Hardening)**:
  - **Pencegahan BOLA / IDOR**: Penambahan validasi hak akses divisi pada `PenilaianController@store` yang secara ketat menolak manipulasi ID karyawan lintas divisi (`HTTP 403 Forbidden`).
  - **Pencegahan Tampered Period Lock Bypass**: Verifikasi status periode terkunci dan izin approval pengajuan pada penyimpanan formulir evaluasi.
  - **Pencegahan Stored XSS**: Penambahan fungsi sanitasi `escapeHtml` pada modal rincian evaluasi di `resources/views/admin/reports/index.blade.php` sehingga payload script yang dimasukkan pada catatan penilai tidak dapat tereksekusi di browser Administrator.
  - **Pencegahan CSV Formula Injection (CWE-1236)**: Sanitasi otomatis seluruh sel teks yang diawali karakter formula Excel (`=`, `+`, `-`, `@`, `\t`, `\r`) dengan menambahkan prefix petik tunggal (`'`) pada `Admin/ReportController@export`.
  - **Pencegahan Type Juggling / Array Input Attack**: Penguatan penanganan parameter username pada `AuthController@login` sebelum diteruskan ke fungsi transliterasi dan rate limiting.
  - `SecurityPenetrationTest` (`tests/Feature/SecurityPenetrationTest.php`): 10 kasus uji penetrasi siber komprehensif (80 assertions) memverifikasi ketahanan terhadap IDOR, pembajakan periode, injeksi XSS, eksploitasi formula CSV, serangan brute force captcha, eskalasi hak akses (PrivEsc), SQL injection, path traversal, dan penghentian sesi akun nonaktif.
- **Dokumentasi Resmi Standar Perusahaan (Format DOCX & PDF)**:
  - `SRS_SPKTAD_Pelindo_Cirebon_v2.0.docx` & `.pdf`: Dokumen Spesifikasi Kebutuhan Perangkat Lunak resmi standar IEEE 830 / ISO-IEC-IEEE 29148 setebal 28 kebutuhan fungsional rinci, arsitektur data, formula matematis AKHLAK, matriks pengujian empiris, dan lembar pengesahan General Manager & Manager SDM.
  - `PANDUAN_TRANSISI_DATA_REAL_DAN_PEMBERSIHAN_DUMMY_SPKTAD.docx` & `.pdf`: Dokumen Standar Operasional Prosedur (SOP No. `SOP-IT-PTP-CRB-2026-004`) panduan pelaksanaan migrasi, checklist 35 personel TAD riil, tahapan eksekusi pembersihan dummy, dan berita acara cutover Tahap 2 ke Tahap 3.

---

## [2.8.0] - 2026-11-02

### Added
- **Pilar 1: Keamanan Akun & Kredensial Mandiri (`/profile`)**:
  - `ProfileController` (`app/Http/Controllers/ProfileController.php`) & View `resources/views/profile/index.blade.php`:
    - Tampilan ringkasan identitas kedinasan (Username, NIK, Posisi, Divisi, Role, Status Akun).
    - Formulir Ubah Kata Sandi Mandiri dengan validasi kata sandi lama (`current_password`), konfirmasi kata sandi baru (min 8 karakter), show/hide toggle, dan larangan memakai kata sandi yang sama.
    - Pencatatan aktivitas ke `activity_logs` dengan aksi `user.password_changed_self`.
    - Peringatan keamanan kredensial dinamis (*Default Password Security Nudge*) pada dashboard penilai & admin jika masih terdeteksi menggunakan kata sandi default `password123`.
    - Tautan navigasi "Profil Saya" pada sidebar penilai dan avatar topbar yang interaktif di kedua layout (`app.blade.php` & `admin.blade.php`).
- **Pilar 2: Penanganan Kendala & Halaman Error Korporat Resmi (`resources/views/errors/`)**:
  - Template induk ber-kop Pelindo Navy `#00223B` (`errors/layout.blade.php`) yang sepenuhnya mandiri (*self-contained inline CSS*) tanpa ketergantungan database atau aset eksternal.
  - Halaman error kustom:
    - `403.blade.php`: Akses Modul Dibatasi (dengan tombol kembali ke dashboard).
    - `404.blade.php`: Halaman Tidak Ditemukan (dengan tombol kembali ke dashboard).
    - `419.blade.php`: Sesi Browser Kadaluarsa CSRF (dengan penjelasan ramah dan tombol Login Ulang Langsung).
    - `429.blade.php`: Batas Percobaan Terlampaui / Lockout 15 Menit Anti-Brute-Force.
    - `500.blade.php`: Kendala Server Internal (dengan opsi muat ulang halaman & kembali ke beranda).
- **Pilar 3: Ketahanan & Pemeliharaan Server Produksi**:
  - Rotasi Log Harian Otomatis (`LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=14`) mencegah penumpukan berkas log berukuran gigabytes di server.
  - Pengerasan Web Server (*Defense-in-Depth*): Berkas `.htaccess` pada root dan `public/` memblokir akses langsung publik ke ekstensi sensitif (`.sqlite`, `.env`, `.db`, `.log`, `.git`, `.json`, `.lock`) serta direktori `database` dan `storage/app/backups`.
  - Verifikasi kesiapan rute diagnostic health check `/up` yang merespons status HTTP 200 OK untuk monitoring Uptime server cabang.
- **Feature Test Suite (`tests/Feature/SystemMaturityTest.php`)**:
  - 8 pengujian otomatis komprehensif (37 assertions) memverifikasi profil mandiri, validasi ganti kata sandi, deteksi password default, rendering halaman error korporat, health check `/up`, dan rotasi log.

---

## [2.7.0] - 2026-11-02

### Added
- **Dynamic Branch Settings & Official Signatory Configuration Module (`/admin/system/settings`)**:
  - Migration `create_system_settings_table` and Model `Setting` (`app/Models/Setting.php`):
    - Two-tier caching architecture (runtime static array memory + Laravel persistent cache `system_settings_dict`) with automatic cache invalidation on mutate/delete for zero-overhead query performance (`agent-orchestration-multi-agent-optimize`).
    - Pre-seeded with official Pelindo Multi Terminal Branch Cirebon parameters.
    - Global Blade view composer in `AppServiceProvider` providing `$branchSettings` array across all views.
  - Interactive Admin Settings Console (`app/Http/Controllers/Admin/SettingController.php` & `resources/views/admin/system/settings.blade.php`):
    - Pelindo Navy (`#00223B`) and Pelindo Blue (`#0072CE`) styling with responsive 2-column layout.
    - Seksi 1: Identitas & Legalitas Pelabuhan (BUMN Induk, Subholding, Nama Cabang, Alamat Kantor, Kota Kedudukan, Kode Pos, Telepon, Email).
    - Seksi 2: Pejabat Penandatangan Resmi (Branch Manager / General Manager & Manager SDM & Umum beserta NIPP/NIK dan Jabatan Resmi).
    - Seksi 3: Catatan Kaki & Legal Disclaimer Dokumen Dinas.
    - **Live Dual-Signature & Letterhead Preview Box**: Interactive client-side preview reacting immediately to admin inputs, showing how signatures and letterhead will render on A4 paper before saving (`ui-ux-designer` & `ui-visual-validator`).
    - "Reset Standar Cirebon" with confirmation modal to revert parameters to official defaults.
    - Business Analyst insight banner explaining legal compliance requirements (SLA vendor alih daya, Berita Acara BAEK, audit internal SPI/BPK).
  - Dynamic Integration to Official Print Views:
    - Updated `admin/reports/print_single.blade.php`, `admin/reports/print_summary.blade.php`, `admin/reports/print_summary_annual.blade.php`, and `user/laporan/print.blade.php`.
    - Eliminated blank dotted underlines `( ............ )` in favor of dynamic official signatory names, NIPP numbers, and official letterheads.
  - Audit Trail Integration:
    - Automatically records `system.settings_updated` with JSON property diff of changed parameters, and `system.settings_reset` in `activity_logs`.
  - Feature Test Suite (`tests/Feature/AdminSettingTest.php`):
    - 7 comprehensive tests (51 assertions) covering authorization, non-admin isolation, setting updates, validation rules, reset action, print reflection, and two-tier cache behavior.

---

## [2.6.0] - 2026-11-02

### Added
- **Database Backup & Disaster Recovery / Maintenance Console (`/admin/system/backup`)**:
  - Enterprise `BackupService` (`app/Services/BackupService.php`):
    - Atomic snapshot engine using SQLite `VACUUM INTO` outside transactions with safe binary file-copy fallback during active test transactions.
    - Automated SHA-256 cryptographic checksum calculation and integrity verification.
    - Retention pruning policy automatically keeping the 10 most recent backups and reclaiming storage.
    - Path normalization resolving Windows backslash path escaping issues (`str_replace('\\', '/', ...)`).
    - Strict basename traversal protection (`cleanName === filename`, blocking `..`, `/`, `\`) on resolution and deletion (**Fix Bug K2/K8/Security audit**).
  - Admin Backup Management Console (`app/Http/Controllers/Admin/BackupController.php` & `resources/views/admin/system/backup.blade.php`):
    - Corporate Pelindo UI with KPI cards: Total Backup Archives, Total Storage Consumed, and Free Disk Space.
    - Interactive "Buat Snapshot Baru" trigger with confirmation modal.
    - Backup archive listing with filename, file size, SHA-256 hash preview, timestamp, download button, and safe deletion.
    - Disaster Recovery standard operating procedure (SOP) panel explaining step-by-step restore instructions.
    - Active navigation item "Cadangan & Pemulihan" added to admin sidebar (`layouts/admin.blade.php`).
  - Automated Backup CLI Command (`php artisan ptp:backup {--clean} {--keep=10}`):
    - Standalone artisan command for cron/task scheduler integration.
    - Generates verified snapshots, logs to `activity_logs`, and provides console progress feedback.
  - Audit Trail Integration:
    - Automatically records `system.backup_created`, `system.backup_downloaded`, and `system.backup_deleted` in `activity_logs`.
  - Feature Test Suite (`tests/Feature/AdminBackupTest.php`):
    - 7 comprehensive tests (42 assertions) covering admin authorization, non-admin isolation, snapshot creation, secure download, deletion, HTTP & service-level path traversal attack blocking, and CLI artisan command execution.

---

## [2.5.0] - 2026-11-02

### Added
- **System Audit Trail & Activity Logging Module (`/admin/activity-logs`)**:
  - Migration and model `ActivityLog` storing action name, actor user, role snapshot, human-readable description, target model & ID, client IP, and contextual JSON diff properties.
  - Automatic event logging hooked into:
    - Period toggles (individual and bulk) in `PeriodController`.
    - Approval and rejection of access/edit requests in `EditRequestController`.
    - User account creation, profile update, password reset, status toggle, and deletion in `UserController`.
    - TAD employee creation, update, status toggle, deletion, and division creation/update in `EmployeeController`.
  - Admin view with statistics cards, search, multi-factor filtering (action group, role, date), and interactive modal JSON diff viewer.
  - Active sidebar navigation item "Log Aktivitas Cabang" in `layouts/admin.blade.php`.
- **Automated Period Auto-Lock CLI Scheduler (`php artisan period:auto-lock`)**:
  - Artisan command to safely close expired past evaluation periods across all operational divisions, completely replacing the legacy insecure webroot script `close_all_periods.php` (**Fix Bug K1**).
  - Supports `--dry-run`, `--month`, and `--year` flags.
  - Configured recurring schedule in `routes/console.php` running monthly on the 1st at 00:00 WIB.
- **Score Reconciliation & Data Sanitizer Command (`php artisan ptp:reconcile-scores`)**:
  - Artisan command scanning evaluations to eliminate historical score drifts (e.g. legacy rating 2 drift $1.98 \to 1.96$).
  - Recalculates detail scores, sum total scores, average scores, and re-evaluates official predicates according to SRS standards with audit trail logging.
- **Enterprise Security Headers Middleware**:
  - Added `SecurityHeadersMiddleware` setting `X-Frame-Options: SAMEORIGIN` (anti-clickjacking), `X-Content-Type-Options: nosniff`, `X-XSS-Protection`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, and conditional HSTS.
  - Globally registered in `bootstrap/app.php` web middleware stack.
- **Feature Test Suite (`tests/Feature/EnterpriseAuditAndSchedulerTest.php`)**:
  - 6 comprehensive tests asserting security headers, automated audit log generation, admin activity log UI, non-admin isolation, auto-lock scheduler, and score reconciliation.

---

## [2.4.0] - 2026-11-02

### Added
- **Executive Branch Reporting Module (`/admin/reports`)**:
  - Dual-mode executive reporting: Monthly branch evaluation rollup and Annual 12-month accumulative matrix.
  - Dynamic filters for period (Month, Year) and division (`all` vs specific division).
  - KPI summary metrics: Realization count, target TAD, completion rate %, branch average score, and predicate distribution.
  - Interactive modal breakdown for 17 AKHLAK behavioral indicators with JSON API endpoint.
- **Official Pelindo Cirebon Letterhead Printable Views**:
  - `print_single.blade.php`: Individual TAD evaluation sheet with 17-aspect breakdown, total score, predicate, and signatures.
  - `print_summary.blade.php`: Monthly branch summary ranking table and dual signatures.
  - `print_summary_annual.blade.php`: Landscape A4 annual matrix across 12 months with grand average and executive signatures.
- **Secure Local CSV Export with UTF-8 BOM**:
  - Local streamed CSV download replacing vulnerable legacy external Google Apps Script calls (**Fix Bug K2 & K8**).
  - Injected 3-byte UTF-8 BOM (`\xEF\xBB\xBF`) ensuring perfect character rendering in Microsoft Excel for Indonesian names with apostrophes (e.g. *Ma'ruf*).
- **Server-Side SVG Graphic Captcha & Rate Limiter Security**:
  - Obfuscated server-rendered SVG image (`image/svg+xml`) with random bezier interference curves, noise dots, and rotated characters (**Fix Bug K3**).
  - Single-use anti-replay token invalidation (`session()->forget('captcha_code')`).
  - Brute-force throttling via Laravel `RateLimiter`: 5 failed attempts locks user out for 15 minutes.
  - Instant AJAX refresh endpoint (`/refresh-captcha`) without page reload.
- **Feature Test Suite (`tests/Feature/AdminReportAndSecurityTest.php`)**: 7 tests covering SVG captcha, rate limiter lockout, reports, modal JSON, print views, and CSV export.

---

## [2.3.0] - 2026-11-02

### Added
- **Master Data TAD & Divisions (`/admin/employees`, `/admin/divisions`)**:
  - Full CRUD for TAD employees with auto-generation of unique `TAD-XXXX` codes if NIK is empty.
  - Soft deactivation toggle protecting historical evaluation integrity against cascade deletion (**Fix Bug L3 & B5**).
  - Fast-action modal for operational divisions management.
- **Master Users & Evaluators (`/admin/users`)**:
  - CRUD for penilai/evaluator accounts with safe data escaping (**Fix Bug K7**).
  - Secure admin password reset with automatic revocation of active sessions on other devices (**Fix Bug K6**).
  - Self-lockout prevention ensuring active administrator session cannot deactivate itself.
  - Real-time active account enforcement per request via `EnsureUserIsActive` middleware.
- **Master Rubrik AKHLAK (`/admin/rubrik`)**:
  - Calibration console for 12 AKHLAK categories and 17 behavioral indicator subcategories.
  - Weight multiplier display ($R_1=0.98$ to $R_6=5.88$, max $99.96 \approx 100$) and predicate threshold standards.
- **Feature Test Suite (`tests/Feature/AdminMasterDataTest.php`)**: 8 tests covering master data access, CRUD, referential protection, and security.

---

## [2.2.0] - 2026-11-02

### Added
- **Period Management & Approval Engine (`/admin/periods`, `/admin/requests`)**:
  - 12-month $\times$ division matrix with real-time Open/Closed status indicators.
  - Single division toggle and bulk toggle ("Buka Semua" / "Tutup Semua") for any month.
  - Approval queue for division period access (`division_access`) and individual evaluation edit (`individual_edit`).
  - Approval automatically syncs `period_management` to `open`.
  - Rejection with mandatory auditor notes.
- **Feature Test Suite (`tests/Feature/AdminPeriodAndApprovalTest.php`)**: 8 tests covering matrix, bulk toggle, approval, and rejection.

---

## [2.1.0] - 2026-11-02

### Added
- **Admin Foundation & Executive Dashboard (`/admin/dashboard`)**:
  - Route isolation via `EnsureUserIsAdmin` middleware blocking non-admin accounts.
  - Pelindo corporate branding: Deep Navy (`#00223B`), Pelindo Blue (`#0072CE`), pure SVG icons.
  - Executive branch dashboard: 35 TAD count, 3 operational divisions, real-time evaluation progress per division, predicate distribution, and pending approval queue counter.
- **Feature Test Suite (`tests/Feature/AdminAuthTest.php`)**: 4 tests verifying admin auth guard and dashboard metrics.

---

## [1.0.0] - 2026-11-01

### Added
- **Clean Project Scaffold & Architecture**:
  - Laravel 11 rebuild replacing legacy vulnerable PHP native codebase.
  - Relational schema: `divisions`, `users`, `employees`, `evaluation_categories`, `evaluation_subcategories`, `period_management`, `evaluations`, `evaluation_details`, `edit_requests`.
  - Standardized AKHLAK scoring formula ($R_1=0.98, \dots, R_6=5.88$, max $99.96 \approx 100$) in `EvaluationScoringService` (**Fix Bug L1 & L2**).
- **Evaluator / Penilai Experience (User Role)**:
  - Responsive Dashboard with real-time evaluation progress bar (SRS Bab 3).
  - Assessment index with period filter and tab division (Belum Dinilai vs Sudah Dinilai) (SRS Bab 4).
  - 17-aspect AKHLAK evaluation form with 1–6 color gradient scale, mandatory reasons, and `localStorage` anti-data-loss autosave drafts (SRS Bab 4.5).
  - Evaluation reporting, detail aspect modal, and official print layout (SRS Bab 5).
  - Access request dialogs for locked periods and completed evaluations (SRS Bab 4.2).
- **Automated Test Suite**: Initial unit and feature tests in `EvaluationTest.php`.

<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RubrikController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EditRequestController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/captcha-image', [AuthController::class, 'captchaImage'])->name('captcha.image');
Route::get('/refresh-captcha', [AuthController::class, 'refreshCaptcha'])->name('captcha.refresh');

// Protected User/Penilai Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Penilaian Karyawan
    Route::get('/penilaian', [PenilaianController::class, 'index'])->name('penilaian.index');
    Route::get('/penilaian/create', [PenilaianController::class, 'create'])->name('penilaian.create');
    Route::get('/penilaian/edit/{id}', [PenilaianController::class, 'edit'])->name('penilaian.edit');
    Route::post('/penilaian/store', [PenilaianController::class, 'store'])->name('penilaian.store');

    // Laporan
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/detail/{id}', [LaporanController::class, 'show'])->name('laporan.detail');
    Route::get('/laporan/print/{id}', [LaporanController::class, 'print'])->name('laporan.print');

    // Requests (Ajukan Akses & Ajukan Edit)
    Route::post('/request-access/division', [EditRequestController::class, 'requestDivisionAccess'])->name('request.division');
    Route::post('/request-access/individual', [EditRequestController::class, 'requestIndividualEdit'])->name('request.individual');

    // Profil Akun & Keamanan Kata Sandi Mandiri
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
});

// Master Data Karyawan TAD (Accessible to Admin and Kepala Divisi)
Route::middleware(['auth', 'can_manage_employees'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::post('/employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('employees.toggle-status');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
});

// Protected Admin Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // Manajemen Periode Penilaian
    Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
    Route::post('/periods/toggle', [PeriodController::class, 'toggle'])->name('periods.toggle');
    Route::post('/periods/bulk-toggle', [PeriodController::class, 'bulkToggle'])->name('periods.bulk-toggle');

    // Pengajuan Akses & Edit Penilaian
    Route::get('/requests', [App\Http\Controllers\Admin\EditRequestController::class, 'index'])->name('requests.index');
    Route::post('/requests/{id}/approve', [App\Http\Controllers\Admin\EditRequestController::class, 'approve'])->name('requests.approve');
    Route::post('/requests/{id}/reject', [App\Http\Controllers\Admin\EditRequestController::class, 'reject'])->name('requests.reject');

    // Master Divisi Operasional (Admin Only)
    Route::post('/divisions', [EmployeeController::class, 'storeDivision'])->name('divisions.store');
    Route::put('/divisions/{division}', [EmployeeController::class, 'updateDivision'])->name('divisions.update');

    // Master Data Penilai / Pengguna
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Master Rubrik AKHLAK
    Route::get('/rubrik', [RubrikController::class, 'index'])->name('rubrik.index');
    Route::post('/rubrik/categories', [RubrikController::class, 'storeCategory'])->name('rubrik.categories.store');
    Route::put('/rubrik/categories/{category}', [RubrikController::class, 'updateCategory'])->name('rubrik.categories.update');
    Route::delete('/rubrik/categories/{category}', [RubrikController::class, 'destroyCategory'])->name('rubrik.categories.destroy');
    Route::post('/rubrik/subcategories', [RubrikController::class, 'storeSubcategory'])->name('rubrik.subcategories.store');
    Route::put('/rubrik/subcategories/{subcategory}', [RubrikController::class, 'updateSubcategory'])->name('rubrik.subcategories.update');
    Route::delete('/rubrik/subcategories/{subcategory}', [RubrikController::class, 'destroySubcategory'])->name('rubrik.subcategories.destroy');

    // Laporan Eksekutif Cabang & Export
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/print-summary', [ReportController::class, 'printSummary'])->name('reports.print-summary');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{id}', [ReportController::class, 'show'])->name('reports.show')->whereNumber('id');
    Route::get('/reports/{id}/print', [ReportController::class, 'printSingle'])->name('reports.print-single')->whereNumber('id');

    // Audit Trail & Activity Logs
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    // Cadangan Basis Data & Pemulihan (Disaster Recovery)
    Route::get('/system/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/system/backup', [BackupController::class, 'store'])->name('backup.store');
    Route::get('/system/backup/download/{filename}', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/system/backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy');

    // Pengaturan Cabang & Pejabat Penandatangan Dinamis
    Route::get('/system/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/system/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/system/settings/reset', [SettingController::class, 'reset'])->name('settings.reset');
});

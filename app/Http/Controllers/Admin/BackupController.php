<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EditRequest;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Services\BackupService;
use App\Services\DataQualityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
        protected DataQualityService $dataQualityService
    ) {}

    /**
     * Display the disaster recovery, backup management, and One-Tap Official Release console.
     */
    public function index(): View
    {
        $backups = $this->backupService->listBackups();

        $totalBytes = array_sum(array_column($backups, 'size'));
        $totalFormatted = BackupService::formatBytes($totalBytes);

        // Approximate free disk space of storage directory
        $freeDiskBytes = disk_free_space(storage_path());
        $freeDiskFormatted = $freeDiskBytes !== false ? BackupService::formatBytes((int) $freeDiskBytes) : 'Tersedia';

        $audit = $this->dataQualityService->auditAll();
        $evaluationCount = Evaluation::count();
        $editRequestCount = EditRequest::count();
        $activeEmployeeCount = Employee::where('is_active', true)->count();

        return view('admin.system.backup', [
            'backups' => $backups,
            'totalCount' => count($backups),
            'totalSizeFormatted' => $totalFormatted,
            'freeDiskFormatted' => $freeDiskFormatted,
            'latestBackup' => $backups[0] ?? null,
            'audit' => $audit,
            'evaluationCount' => $evaluationCount,
            'editRequestCount' => $editRequestCount,
            'activeEmployeeCount' => $activeEmployeeCount,
        ]);
    }

    /**
     * Execute One-Tap Official Release pipeline (backup, master sanitization, purge/reconcile, and quality audit).
     */
    public function officialRelease(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'release_mode' => ['required', 'string', 'in:purge_and_ready,sanitize_and_reconcile'],
            'confirmation_code' => ['required', 'string'],
        ]);

        if (strtoupper(trim($validated['confirmation_code'])) !== 'RILIS-RESMI') {
            return back()->with('error', 'Kode konfirmasi tidak sesuai. Ketik RILIS-RESMI untuk melanjutkan.');
        }

        try {
            $result = $this->dataQualityService->executeOfficialRelease(
                $validated['release_mode'],
                $this->backupService
            );

            $sanitizedTotal = $result['sanitized_employees'] + $result['sanitized_divisions'] + $result['sanitized_users'];
            $healthScore = $result['audit']['health_score'];
            $summaryStatus = $result['audit']['summary_status'];

            if ($result['mode'] === 'purge_and_ready') {
                $message = sprintf(
                    'One-Tap Official Release berhasil! Snapshot %s dibuat, %d data master dirapikan, %d penilaian & %d rincian serta %d pengajuan uji coba dibersihkan. Health Score Data: %s%% (%s).',
                    $result['backup_filename'],
                    $sanitizedTotal,
                    $result['purged_evaluations'],
                    $result['purged_details'],
                    $result['purged_requests'],
                    $healthScore,
                    $summaryStatus
                );
            } else {
                $message = sprintf(
                    'One-Tap Official Release (Rekonsiliasi) berhasil! Snapshot %s dibuat, %d data master dirapikan, %d evaluasi direkonsiliasi, %d pengajuan ditolak dibersihkan. Health Score Data: %s%% (%s).',
                    $result['backup_filename'],
                    $sanitizedTotal,
                    $result['reconciled_evaluations'],
                    $result['purged_requests'],
                    $healthScore,
                    $summaryStatus
                );
            }

            return back()->with('success', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengeksekusi One-Tap Official Release: '.$e->getMessage());
        }
    }

    /**
     * Trigger immediate database snapshot creation.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backupService->createBackup();
            // Automatically retain latest 10 archives
            $this->backupService->pruneOldBackups(10);

            return back()->with('success', "Snapshot cadangan {$backup['filename']} ({$backup['size_formatted']}) berhasil dibuat dan diverifikasi.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal membuat cadangan basis data: '.$e->getMessage());
        }
    }

    /**
     * Safely download an archive file with strict path traversal prevention.
     */
    public function download(string $filename): BinaryFileResponse
    {
        try {
            $path = $this->backupService->getBackupPath($filename);

            ActivityLog::record(
                'system.backup_downloaded',
                "Mengunduh berkas cadangan database: {$filename}",
                'SystemBackup',
                null,
                ['filename' => $filename]
            );

            return response()->download($path, $filename, [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }
    }

    /**
     * Safely delete a backup file.
     */
    public function destroy(string $filename): RedirectResponse
    {
        try {
            $this->backupService->deleteBackup($filename);

            return back()->with('success', "Berkas cadangan {$filename} berhasil dihapus dari sistem.");
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}

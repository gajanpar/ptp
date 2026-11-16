<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(protected BackupService $backupService) {}

    /**
     * Display the disaster recovery and database backup management dashboard.
     */
    public function index(): View
    {
        $backups = $this->backupService->listBackups();

        $totalBytes = array_sum(array_column($backups, 'size'));
        $totalFormatted = BackupService::formatBytes($totalBytes);

        // Approximate free disk space of storage directory
        $freeDiskBytes = disk_free_space(storage_path());
        $freeDiskFormatted = $freeDiskBytes !== false ? BackupService::formatBytes((int) $freeDiskBytes) : 'Tersedia';

        return view('admin.system.backup', [
            'backups' => $backups,
            'totalCount' => count($backups),
            'totalSizeFormatted' => $totalFormatted,
            'freeDiskFormatted' => $freeDiskFormatted,
            'latestBackup' => $backups[0] ?? null,
        ]);
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

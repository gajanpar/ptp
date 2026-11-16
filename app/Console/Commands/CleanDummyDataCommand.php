<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\EditRequest;
use App\Models\Evaluation;
use App\Models\EvaluationDetail;
use App\Services\DataQualityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDummyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ptp:clean-dummy 
                            {--force : Jalankan pembersihan tanpa prompt konfirmasi interaktif}
                            {--dry-run : Simulasi pengecekan tanpa menghapus data sesungguhnya}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membersihkan seluruh data transaksi dummy/uji coba (evaluations, details, edit_requests) untuk persiapan transisi ke Tahap 3 (Data Riil Go-Live)';

    /**
     * Execute the console command.
     */
    public function handle(DataQualityService $dataQualityService): int
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>====================================================================</>');
        $this->line('<fg=cyan;options=bold>  PT PELINDO MULTI TERMINAL BRANCH CIREBON - SPKTAD GO-LIVE ENGINE  </>');
        $this->line('<fg=cyan;options=bold>  Pembersihan Transaksi Dummy & Persiapan Transisi Data Riil        </>');
        $this->line('<fg=cyan;options=bold>====================================================================</>');
        $this->newLine();

        $evalCount = Evaluation::count();
        $detailCount = EvaluationDetail::count();
        $editReqCount = EditRequest::count();
        $dummyLogsCount = ActivityLog::where('action', 'like', '%test%')->orWhere('description', 'like', '%dummy%')->count();

        $this->table(
            ['Tabel Transaksi', 'Kategori Data', 'Jumlah Entri Terdeteksi', 'Tindakan Transisi'],
            [
                ['evaluations', 'Transaksi Penilaian Karyawan', $evalCount, '<fg=yellow>Dibersihkan (Purge)</>'],
                ['evaluation_details', 'Rincian 17 Butir Indikator AKHLAK', $detailCount, '<fg=yellow>Dibersihkan (Purge)</>'],
                ['edit_requests', 'Pengajuan Izin Akses & Edit Periode', $editReqCount, '<fg=yellow>Dibersihkan (Purge)</>'],
                ['activity_logs', 'Log Audit Dummy / Pengujian', $dummyLogsCount, '<fg=yellow>Dibersihkan (Purge)</>'],
            ]
        );

        if ($evalCount === 0 && $detailCount === 0 && $editReqCount === 0) {
            $this->info('Basis data sudah dalam kondisi bersih (0 transaksi dummy). Basis data siap untuk produksi!');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN MODE] Tidak ada perubahan data yang disimpan ke basis data.');

            return Command::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Apakah Anda yakin ingin menghapus seluruh data penilaian dummy di atas untuk memasuki tahap produksi riil?', false)) {
                $this->warn('Operasi pembersihan dibatalkan oleh pengguna.');

                return Command::FAILURE;
            }
        }

        $this->info('Memulai pembersihan data transaksi dummy secara aman...');

        DB::transaction(function () {
            $purgedEvaluations = Evaluation::count();
            $purgedDetails = EvaluationDetail::count();
            $purgedEditRequests = EditRequest::count();

            // Hapus detail rincian penilaian
            EvaluationDetail::query()->delete();

            // Hapus evaluasi
            Evaluation::query()->delete();

            // Hapus pengajuan edit
            EditRequest::query()->delete();

            // Catat audit trail pembersihan resmi
            ActivityLog::record(
                'system.dummy_data_purged',
                'Pembersihan seluruh data transaksi dummy penilaian dan pengajuan edit untuk persiapan go-live Tahap 3',
                'System',
                null,
                [
                    'purged_evaluations' => $purgedEvaluations,
                    'purged_details' => $purgedDetails,
                    'purged_edit_requests' => $purgedEditRequests,
                    'executed_at' => now()->toIso8601String(),
                ]
            );
        });

        $this->info('Transaksi dummy berhasil dibersihkan.');

        // Jalankan audit kualitas data pasca pembersihan
        $this->line('Menjalankan audit kualitas data (Data Quality Audit) pasca pembersihan...');
        $auditResult = $dataQualityService->auditAll();

        $this->newLine();
        $this->line("Health Score Data: <fg=green;options=bold>{$auditResult['health_score']}% ({$auditResult['summary_status']})</>");
        $this->line("Aturan Kualitas Data Lolos: <fg=green>{$auditResult['passed_rules']}/{$auditResult['total_rules']}</>");

        if ($auditResult['is_production_ready']) {
            $this->info('STATUS: BASIS DATA BERSIH & 100% SIAP UNTUK IMPLEMENTASI PRODUKSI (TAHAP 3)!');

            return Command::SUCCESS;
        }

        $this->warn('STATUS: Terdapat catatan data quality yang perlu ditinjau sebelum go-live.');

        return Command::SUCCESS;
    }
}

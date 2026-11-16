<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('period:auto-lock {--dry-run : Simulate execution without mutating database} {--month= : Specific month (1-12)} {--year= : Specific year}')]
#[Description('Safely lock expired evaluation periods across divisions to enforce assessment deadlines')]
class AutoLockPeriods extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $targetMonth = $this->option('month') ? (int) $this->option('month') : null;
        $targetYear = $this->option('year') ? (int) $this->option('year') : null;

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $this->info('Menjalankan prosedur penguncian periode otomatis (Auto-Lock Scheduler)...');
        if ($isDryRun) {
            $this->warn('[DRY-RUN MODE] Tidak ada perubahan yang akan disimpan ke database.');
        }

        $divisions = Division::where('is_active', true)->get();
        $lockedCount = 0;
        $monthNames = EvaluationScoringService::MONTH_NAMES;

        // If target month and year are specified
        if ($targetMonth && $targetYear) {
            $periodsToEvaluate = [['month' => $targetMonth, 'year' => $targetYear]];
        } else {
            // Check all months up to last month of current year
            $periodsToEvaluate = [];
            for ($m = 1; $m < $currentMonth; $m++) {
                $periodsToEvaluate[] = ['month' => $m, 'year' => $currentYear];
            }
            // And previous year
            for ($m = 1; $m <= 12; $m++) {
                $periodsToEvaluate[] = ['month' => $m, 'year' => $currentYear - 1];
            }
        }

        foreach ($periodsToEvaluate as $p) {
            $m = $p['month'];
            $y = $p['year'];

            foreach ($divisions as $div) {
                $currentStatus = PeriodManagement::getStatus($div->id, $m, $y);

                if ($currentStatus === 'open') {
                    $mName = $monthNames[$m] ?? "Bulan $m";
                    $this->line("Mengunci periode {$mName} {$y} untuk divisi {$div->name}...");

                    if (! $isDryRun) {
                        PeriodManagement::setStatus($div->id, $m, $y, 'closed');

                        ActivityLog::record(
                            'period.auto_locked',
                            "Otomatis mengunci periode kedaluwarsa {$mName} {$y} untuk divisi {$div->name}",
                            'PeriodManagement',
                            $div->id,
                            [
                                'division_id' => $div->id,
                                'division_name' => $div->name,
                                'month' => $m,
                                'year' => $y,
                                'source' => 'scheduler_auto_lock',
                            ]
                        );
                    }

                    $lockedCount++;
                }
            }
        }

        $this->info("Prosedur selesai. Total periode berhasil dikunci: {$lockedCount}.");

        return 0;
    }
}

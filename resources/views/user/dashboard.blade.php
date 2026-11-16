@extends('layouts.app', ['title' => 'Dashboard Penilai'])

@section('content')
<!-- Page Header & Evaluator Context -->
<div class="dashboard-header-bar animate-fade-up">
    <div>
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
            <h1 style="font-size: var(--font-scale-h1, 24px); font-weight: 800; color: #0F172A; margin: 0; letter-spacing: -0.4px;">
                Dashboard Evaluator
            </h1>
            <span class="badge-clean badge-clean-navy">
                {{ $user->division->name ?? 'Divisi Operasional' }}
            </span>
        </div>
        <p style="font-size: var(--font-scale-caption, 13px); color: #64748B; margin: 0;">
            Portal evaluasi kinerja Tenaga Alih Daya (TAD) &mdash; <strong>{{ $monthName }} {{ $year }}</strong>
            <span style="color: #CBD5E1; margin: 0 6px;">&bull;</span>
            <span>Batas Cutoff: <strong style="color: #0F172A;">{{ $slaCutoffDate }}</strong></span>
            @if($isCutoffPassed)
                <span class="badge-clean badge-clean-neutral" style="margin-inline-start: 4px; font-size: 10px;">Periode Berakhir</span>
            @else
                <span class="badge-clean badge-clean-warning" style="margin-inline-start: 4px; font-size: 10px;">Sisa {{ $slaDaysRemaining }} Hari</span>
            @endif
        </p>
    </div>

    <!-- Period Filter & Action Shortcut -->
    <div class="dashboard-filter-group">
        <form id="evaluatorPeriodFilterForm" method="GET" action="{{ route('dashboard') }}" style="display: flex; align-items: center; gap: 8px; background: #FFFFFF; padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border-hairline, #E2E8F0); box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; gap: 6px; color: #475569; font-size: 12px; font-weight: 600;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <label for="evaluatorFilterMonth">Periode:</label>
            </div>
            <select id="evaluatorFilterMonth" name="month" aria-label="Pilih bulan periode" onchange="document.getElementById('evaluatorPeriodFilterForm').submit()" class="form-control-custom" style="padding: 5px 8px; font-size: 12.5px; width: auto; border-radius: 6px; border: 1px solid #64748B; cursor: pointer;">
                @foreach([1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'] as $m => $name)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <select id="evaluatorFilterYear" name="year" aria-label="Pilih tahun periode" onchange="document.getElementById('evaluatorPeriodFilterForm').submit()" class="form-control-custom" style="padding: 5px 8px; font-size: 12.5px; width: auto; border-radius: 6px; border: 1px solid #64748B; cursor: pointer;">
                @for($y = 2025; $y <= 2028; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <noscript>
                <button type="submit" style="background: var(--brand-accent, #005FA8); color: #FFFFFF; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer;">
                    Pilih
                </button>
            </noscript>
        </form>

        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" style="background: var(--brand-navy, #00223B); color: #FFFFFF; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0, 34, 59, 0.15);">
            <span>Mulai Penilaian</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
    </div>
</div>

<!-- SLA Countdown Pengingat Tegas Penilai -->
@if($totalPending > 0 && $periodStatus !== 'closed')
    <div class="animate-fade-up" style="background: var(--brand-accent-subtle, #EBF5FF); border: 1px solid rgba(0, 114, 206, 0.25); border-inline-start: 4px solid var(--brand-accent, #0072CE); border-radius: 8px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--brand-accent, #0072CE)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div>
                <strong style="color: #0F2E4A; font-size: 13px;">Pengingat Batas Waktu Evaluasi:</strong>
                <span style="color: #1E3A5F; font-size: 12.5px;">Periode <strong>{{ $monthName }} {{ $year }}</strong> akan dikunci otomatis pada <strong>{{ $slaCutoffDate }}</strong>. Anda masih memiliki <strong>{{ $totalPending }} karyawan</strong> yang perlu dinilai.</span>
            </div>
        </div>
        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" class="action-chip-link action-chip-accent" style="font-size: 11.5px;">
            Tuntaskan Sekarang &rarr;
        </a>
    </div>
@endif

@if(\Illuminate\Support\Facades\Hash::check('password123', Auth::user()->password))
    <div class="animate-fade-up" style="background: var(--semantic-warning-bg, #FFFBEB); border: 1px solid var(--semantic-warning-border, #FDE68A); border-inline-start: 4px solid #D97706; border-radius: 8px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <strong style="color: #92400E; font-size: 13px;">Peringatan Keamanan Kredensial:</strong>
                <span style="color: #B45309; font-size: 12.5px;">Akun penilai Anda masih menggunakan kata sandi bawaan sistem. Segera ganti kata sandi Anda.</span>
            </div>
        </div>
        <a href="{{ route('profile.index') }}" class="action-chip-link action-chip-warning" style="font-size: 11.5px;">
            Ganti Kata Sandi &rarr;
        </a>
    </div>
@endif

<!-- 4 Calibrated Metric KPI Cards -->
<div class="dashboard-kpi-grid">
    <!-- 1. Total Karyawan Divisi -->
    <div class="card-interactive animate-fade-up delay-1" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Total Karyawan Divisi" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Total Karyawan Divisi</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: #F1F5F9; color: var(--brand-navy, #00223B); display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalEmployees }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $totalEmployees }}</span>
            <span style="font-size: 12.5px; color: #64748B; font-weight: 500;">karyawan TAD</span>
        </div>
        <div style="margin-top: 10px; font-size: 11.5px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 8px;">
            Unit: <strong>{{ $division->name ?? 'Internal' }}</strong>
        </div>
    </div>

    <!-- 2. Sudah Dinilai -->
    <div class="card-interactive animate-fade-up delay-2" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Selesai Dinilai" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Selesai Dinilai</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--semantic-success-bg, #ECFDF5); color: var(--semantic-success-text, #059669); display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalEvaluated }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $totalEvaluated }}</span>
            <span style="font-size: 12.5px; color: #64748B;">/ {{ $totalEmployees }} TAD</span>
            <span class="badge-clean {{ $progressPercentage == 100 ? 'badge-clean-success' : 'badge-clean-accent' }}" style="margin-inline-start: auto;">
                {{ $progressPercentage }}%
            </span>
        </div>
        <div style="margin-top: 10px;">
            <div style="width: 100%; height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                <div style="width: {{ $progressPercentage }}%; height: 100%; background: {{ $progressPercentage == 100 ? 'var(--semantic-success-text, #059669)' : 'var(--brand-accent, #0072CE)' }}; border-radius: 999px; transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);"></div>
            </div>
        </div>
    </div>

    <!-- 3. Belum Dinilai (Action Trigger) -->
    <div class="card-interactive animate-fade-up delay-3" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Menunggu Penilaian" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Menunggu Penilaian</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: {{ $totalPending > 0 ? 'var(--semantic-warning-bg, #FFFBEB)' : '#F1F5F9' }}; color: {{ $totalPending > 0 ? 'var(--semantic-warning-text, #D97706)' : '#64748B' }}; display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalPending }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: {{ $totalPending > 0 ? '#92400E' : '#0F172A' }}; line-height: 1;">{{ $totalPending }}</span>
            <span style="font-size: 12.5px; color: #64748B;">karyawan</span>
            @if($totalPending > 0)
                <span class="badge-clean badge-clean-warning" style="margin-inline-start: auto;">Perlu Tindakan</span>
            @else
                <span class="badge-clean badge-clean-success" style="margin-inline-start: auto;">Tuntas</span>
            @endif
        </div>
        <div style="margin-top: 10px; font-size: 11.5px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 8px;">
            {{ $totalPending > 0 ? 'Segera input evaluasi sebelum batas cutoff' : 'Semua penilaian divisi telah selesai' }}
        </div>
    </div>

    <!-- 4. Rata-Rata Nilai Divisi (dengan MoM Trend) -->
    <div class="card-interactive animate-fade-up delay-4" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Rata-Rata Skor Divisi" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Rata-Rata Skor Divisi</span>
            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--brand-accent-subtle, #EBF5FF); color: var(--brand-accent, #0072CE); display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;">
            <span class="tabular-nums kpi-val" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">
                {{ $averageScore > 0 ? number_format($averageScore, 1) : '-' }}
            </span>
            <span style="font-size: 13px; color: #64748B; font-weight: 600;">/ 100</span>

            <!-- MoM Trend Divisi -->
            @if($momDelta !== null)
                <span class="badge-clean {{ $momDelta >= 0 ? 'badge-clean-success' : 'badge-clean-danger' }}" style="margin-inline-start: auto;" title="Perubahan dibanding bulan {{ $prevMonthName }}">
                    {{ $momDelta >= 0 ? '+' : '' }}{{ $momDelta }} vs {{ $prevMonthName }}
                </span>
            @else
                <span class="badge-clean badge-clean-navy" style="margin-inline-start: auto;">
                    {{ $periodStatus === 'closed' ? 'Terkunci' : 'Terbuka' }}
                </span>
            @endif
        </div>
        <div style="margin-top: 10px; font-size: 11.5px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 8px;">
            Status Sistem: <strong>{{ $systemStatus }}</strong>
        </div>
    </div>
</div>

<!-- SECTION: Diagnostik AKHLAK Divisi Ini -->
<div class="card-interactive animate-fade-up delay-2" style="padding: 24px; margin-bottom: 28px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
        <div>
            <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                Diagnostik Mutu AKHLAK &mdash; {{ $division->name ?? 'Divisi' }}
            </h3>
            <p style="font-size: 12.5px; color: #64748B; margin: 0;">Rata-rata pencapaian 6 nilai inti bawahan TAD di unit kerja Anda</p>
        </div>
        @if($weakestPillar)
            <span class="badge-clean badge-clean-warning">
                Fokus Bimbingan: {{ $weakestPillar['name'] }} ({{ $weakestPillar['score'] }}%)
            </span>
        @endif
    </div>

    @if($totalEvaluated === 0)
        <div style="padding: 24px; text-align: center; color: #64748B; font-size: 13px;">
            <div style="font-weight: 700; color: #0F172A; margin-bottom: 4px;">Belum Ada Evaluasi Selesai</div>
            <div>Belum ada nilai evaluasi yang tersimpan untuk divisi Anda pada bulan {{ $monthName }} {{ $year }}.</div>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px 24px;">
            @foreach($akhlakStats as $pillar)
                <div style="padding: 8px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-size: 13px; font-weight: 600; color: #334155;">{{ $pillar['name'] }}</span>
                        <div style="display: flex; align-items: baseline; gap: 4px;">
                            <strong class="tabular-nums" style="font-size: 13.5px; color: #0F172A;">{{ $pillar['score_100'] }}%</strong>
                            <span style="font-size: 11px; color: #64748B;">({{ $pillar['raw_avg'] }}/5.88)</span>
                        </div>
                    </div>
                    <div style="width: 100%; height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                        <div style="width: {{ $pillar['score_100'] }}%; height: 100%; background: {{ $weakestPillar && $weakestPillar['name'] == $pillar['name'] ? '#D97706' : 'var(--brand-accent, #0072CE)' }}; border-radius: 999px; transition: width 0.6s ease;"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($weakestPillar)
            <div style="margin-top: 16px; padding: 12px 14px; background: #FFFBEB; border-radius: 8px; border: 1px solid #FDE68A; font-size: 12.5px; color: #92400E;">
                <strong>Catatan Kepala Divisi:</strong> Pilar <u>{{ $weakestPillar['name'] }}</u> tercatat paling rendah di divisi Anda ({{ $weakestPillar['score'] }}%). Fokuskan supervisi dan pengarahan harian pada aspek kedisiplinan dan tanggung jawab ini.
            </div>
        @endif
    @endif
</div>

<!-- SECTION: Top & Bottom Performers Internal Divisi -->
@if($totalEvaluated > 0)
<div class="card-interactive animate-fade-up delay-3" style="padding: 24px; margin-bottom: 28px;">
    <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
        <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
            Peringkat Kinerja Tim Divisi
        </h3>
        <p style="font-size: 12.5px; color: #64748B; margin: 0;">Pemetaan performa bawahan langsung untuk apresiasi dan pembinaan</p>
    </div>

    <div class="performers-grid">
        <!-- Top Tim -->
        <div>
            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #059669;"></span>
                <strong style="font-size: 12.5px; color: #0F172A; text-transform: uppercase;">Kinerja Tertinggi Divisi</strong>
            </div>
            <table class="table-interactive" style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <tbody>
                    @foreach($topPerformers->take(3) as $eval)
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 8px 6px;">
                                <div style="font-weight: 700; color: #0F172A;">{{ $eval->employee->name }}</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $eval->employee->position }}</div>
                            </td>
                            <td style="padding: 8px 6px; text-align: end;">
                                <span class="tabular-nums" style="font-weight: 800; color: #0F172A;">{{ $eval->total_score }}</span>
                                <div><span class="badge-clean badge-clean-navy" style="font-size: 9px; padding: 2px 5px;">{{ $eval->predicate }}</span></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Perlu Bimbingan -->
        <div>
            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #DC2626;"></span>
                <strong style="font-size: 12.5px; color: #0F172A; text-transform: uppercase;">Perlu Pembinaan Khusus</strong>
            </div>
            <table class="table-interactive" style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <tbody>
                    @foreach($bottomPerformers->take(3) as $eval)
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 8px 6px;">
                                <div style="font-weight: 700; color: #0F172A;">{{ $eval->employee->name }}</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $eval->employee->position }}</div>
                            </td>
                            <td style="padding: 8px 6px; text-align: end;">
                                <span class="tabular-nums" style="font-weight: 800; color: #991B1B;">{{ $eval->total_score }}</span>
                                <div><span class="badge-clean {{ $eval->predicate === 'Perlu Perhatian' ? 'badge-clean-danger' : 'badge-clean-warning' }}" style="font-size: 9px; padding: 2px 5px;">{{ $eval->predicate }}</span></div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Golden Grid: Action Queue (62%) vs Status Divisi & Predikat (38%) -->
<div class="golden-grid" style="margin-bottom: 28px;">
    <!-- Left Column (62%): Prioritas Antrian Penilaian TAD (High Utility) -->
    <div class="card-interactive animate-fade-up delay-2" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <div>
                <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                    Antrian Penilaian Karyawan TAD
                </h3>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">Karyawan di divisi Anda yang belum dilakukan input penilaian bulan {{ $monthName }} {{ $year }}</p>
            </div>
            <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" class="action-chip-link action-chip-accent">
                Buka Matriks Penilaian &rarr;
            </a>
        </div>

        @if($pendingEmployees->isEmpty())
            <div style="padding: 24px 20px; text-align: center; background: linear-gradient(135deg, #F0FDF4, #ECFDF5); border-radius: 12px; border: 1px solid #A7F3D0;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #DCFCE7; color: #15803D; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(21, 128, 61, 0.15);">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h4 style="font-size: 16px; font-weight: 800; color: #065F46; margin: 0 0 4px 0;">Divisi 100% Tuntas Dievaluasi</h4>
                <p style="font-size: 13px; color: #047857; margin: 0 0 16px 0;">Seluruh {{ $totalEmployees }} personel Tenaga Alih Daya telah selesai dinilai untuk periode {{ $monthName }} {{ $year }}.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 16px; text-align: start;">
                    <div style="background: #FFFFFF; padding: 10px 14px; border-radius: 8px; border: 1px solid #D1FAE5;">
                        <div style="font-size: 11px; color: #047857; font-weight: 600; text-transform: uppercase;">Rata-Rata Divisi</div>
                        <div style="font-size: 18px; font-weight: 800; color: #065F46;" class="tabular-nums">{{ number_format($averageScore, 1) }}</div>
                    </div>
                    <div style="background: #FFFFFF; padding: 10px 14px; border-radius: 8px; border: 1px solid #D1FAE5;">
                        <div style="font-size: 11px; color: #047857; font-weight: 600; text-transform: uppercase;">Skor Tertinggi</div>
                        <div style="font-size: 18px; font-weight: 800; color: #065F46;" class="tabular-nums">{{ $topPerformers->first()->total_score ?? '-' }}</div>
                    </div>
                    <div style="background: #FFFFFF; padding: 10px 14px; border-radius: 8px; border: 1px solid #D1FAE5;">
                        <div style="font-size: 11px; color: #047857; font-weight: 600; text-transform: uppercase;">Kepatuhan SLA</div>
                        <div style="font-size: 18px; font-weight: 800; color: #065F46;">Tepat Waktu</div>
                    </div>
                </div>

                <a href="{{ route('laporan.index', ['month' => $month, 'year' => $year]) }}" class="action-chip-link action-chip-accent" style="padding: 8px 18px; font-size: 13px;">
                    <span>Lihat Rekapitulasi &amp; Cetak Nilai Divisi</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table-interactive" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #E2E8F0; color: #64748B; font-size: 11.5px; text-transform: uppercase;">
                            <th style="padding: 10px 12px; text-align: start;">Nama &amp; NIK</th>
                            <th style="padding: 10px 12px; text-align: start;">Posisi / Jabatan</th>
                            <th style="padding: 10px 12px; text-align: center;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingEmployees as $emp)
                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                <td style="padding: 12px;">
                                    <div style="font-weight: 700; color: #0F172A;">{{ $emp->name }}</div>
                                    <div style="font-size: 11.5px; color: #64748B;" class="tabular-nums">NIK: {{ $emp->nik }}</div>
                                </td>
                                <td style="padding: 12px; color: #334155; font-size: 12.5px;">
                                    <div>{{ $emp->position }}</div>
                                    <div style="font-size: 11px; color: #64748B;">{{ $emp->vendor }}</div>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    @if($periodStatus === 'closed')
                                        <span class="badge-clean badge-clean-neutral" title="Periode divisi telah dikunci oleh administrator">
                                             Terkunci
                                        </span>
                                    @else
                                        <a href="{{ route('penilaian.create', ['employee_id' => $emp->id, 'month' => $month, 'year' => $year]) }}" class="action-chip-link action-chip-accent" style="padding: 6px 14px;">
                                            + Input Nilai
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($totalPending > 5)
                <div style="margin-top: 14px; text-align: center; font-size: 12px; color: #64748B;">
                    Dan {{ $totalPending - 5 }} karyawan TAD lainnya belum dinilai.
                </div>
            @endif
        @endif
    </div>

    <!-- Right Column (38%): Status Informasi & Ringkasan Mutu Divisi -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Corporate Info Card Pelindo -->
        <div class="card-interactive animate-fade-up delay-3" style="background: var(--brand-navy, #00223B); color: #FFFFFF; padding: 24px; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #38BDF8;">
                    Pedoman Evaluasi
                </span>
                <span class="badge-clean" style="background: rgba(255,255,255,0.12); color: #FFFFFF;">
                    AKHLAK BUMN
                </span>
            </div>
            <h4 style="font-size: 15px; font-weight: 700; color: #FFFFFF; margin: 0 0 8px 0;">
                Kepatuhan Evaluasi Bulanan
            </h4>
            <p style="font-size: 12.5px; color: #CBD5E1; line-height: 1.5; margin: 0 0 18px 0;">
                Penilaian dilakukan secara objektif mengacu pada indikator Nilai Inti AKHLAK Kementerian BUMN &amp; PT Pelabuhan Indonesia (Persero).
            </p>

            <div style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 12px 14px; margin-bottom: 18px;">
                <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #CBD5E1; margin-bottom: 6px;">
                    <span>Kelengkapan Divisi:</span>
                    <strong style="color: #38BDF8;">{{ $progressPercentage }}%</strong>
                </div>
                <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.15); border-radius: 999px; overflow: hidden;">
                    <div style="width: {{ $progressPercentage }}%; height: 100%; background: #38BDF8; border-radius: 999px; transition: width 0.8s ease;"></div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #CBD5E1;">
                <span>Status Kunci: <strong style="color: #FFFFFF;">{{ $periodStatus === 'closed' ? 'Terkunci' : 'Aktif / Terbuka' }}</strong></span>
                @if($periodStatus === 'closed')
                    <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" style="color: #38BDF8; font-weight: 600; text-decoration: none;">Ajukan Buka &rarr;</a>
                @endif
            </div>
        </div>

        <!-- Predikat Breakdown Divisi -->
        <div class="card-interactive animate-fade-up delay-4" style="padding: 20px;">
            <h4 style="font-size: 13.5px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">
                Distribusi Mutu Divisi Ini
            </h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                    <span style="color: #334155; font-weight: 600;">Sangat Baik (&ge; 85)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: var(--brand-navy, #00223B);">{{ $predicateCounts['Sangat Baik'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                    <span style="color: #334155; font-weight: 600;">Baik (70 - 84.9)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: var(--brand-accent, #0072CE);">{{ $predicateCounts['Baik'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                    <span style="color: #334155; font-weight: 600;">Cukup Baik (55 - 69.9)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: #64748B;">{{ $predicateCounts['Cukup Baik'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0;">
                    <span style="color: #334155; font-weight: 600;">Perlu Perhatian (&lt; 55)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: #DC2626;">{{ $predicateCounts['Perlu Perhatian'] }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Lightweight Number Counter Animation (Respects prefers-reduced-motion)
    document.addEventListener('DOMContentLoaded', function() {
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        const counters = document.querySelectorAll('.counter');
        const duration = 500;

        counters.forEach(counter => {
            const target = +counter.getAttribute('data-target') || 0;
            if (target === 0) return;
            const startTime = performance.now();

            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeProgress = 1 - (1 - progress) * (1 - progress);
                counter.innerText = Math.floor(easeProgress * target);

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.innerText = target;
                }
            }
            requestAnimationFrame(updateCounter);
        });
    });
</script>
@endsection

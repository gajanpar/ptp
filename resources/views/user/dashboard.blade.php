@extends('layouts.app', ['title' => 'Dashboard Penilai'])

@php
    $prevStepMonth = $month === 1 ? 12 : $month - 1;
    $prevStepYear = $month === 1 ? $year - 1 : $year;
    $nextStepMonth = $month === 12 ? 1 : $month + 1;
    $nextStepYear = $month === 12 ? $year + 1 : $year;
    $nowMonth = (int) date('n');
    $nowYear = (int) date('Y');

    $akhlakChartData = collect($akhlakStats ?? [])->map(fn ($p) => [
        'name' => $p['name'],
        'score' => (float) ($p['score_100'] ?? 0),
        'raw' => (float) ($p['raw_avg'] ?? 0),
    ])->values();
@endphp

@section('content')
<!-- Page Header & Evaluator Context (Standar Nasional SPBE) -->
<div class="dashboard-header-bar animate-fade-up">
    <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
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
                <span class="badge-clean badge-clean-neutral" style="margin-inline-start: 6px; font-size: 10px;">Periode Berakhir</span>
            @else
                <span class="badge-clean badge-clean-accent" style="margin-inline-start: 6px; font-size: 10px;">Sisa {{ $slaDaysRemaining }} Hari</span>
            @endif
        </p>
    </div>

    <!-- Quick Period Stepper & Action Shortcut -->
    <div class="dashboard-filter-group" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <nav class="spbe-period-stepper" aria-label="Navigasi cepat bulan periode">
            <a href="{{ route('dashboard', ['month' => $prevStepMonth, 'year' => $prevStepYear]) }}" class="spbe-stepper-btn" title="Bulan sebelumnya">
                <span aria-hidden="true">&lsaquo;</span>
                <span>Lalu</span>
            </a>
            <a href="{{ route('dashboard', ['month' => $nowMonth, 'year' => $nowYear]) }}" class="spbe-stepper-btn {{ ($month === $nowMonth && $year === $nowYear) ? 'is-current' : '' }}" title="Lompat ke bulan berjalan">
                Bulan Ini
            </a>
            <a href="{{ route('dashboard', ['month' => $nextStepMonth, 'year' => $nextStepYear]) }}" class="spbe-stepper-btn" title="Bulan berikutnya">
                <span>Depan</span>
                <span aria-hidden="true">&rsaquo;</span>
            </a>
        </nav>

        <form id="evaluatorPeriodFilterForm" method="GET" action="{{ route('dashboard') }}" style="display: flex; align-items: center; gap: 8px; background: #FFFFFF; padding: 5px 10px; border-radius: 8px; border: 1px solid var(--border-hairline, #E2E8F0); box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; gap: 5px; color: #475569; font-size: 12px; font-weight: 600;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <label for="evaluatorFilterMonth">Periode:</label>
            </div>
            <select id="evaluatorFilterMonth" name="month" aria-label="Pilih bulan periode" onchange="document.getElementById('evaluatorPeriodFilterForm').submit()" class="form-control-custom" style="padding: 4px 8px; font-size: 12.5px; width: auto; border-radius: 6px; border: 1px solid #CBD5E1; cursor: pointer;">
                @foreach([1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'] as $m => $name)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <select id="evaluatorFilterYear" name="year" aria-label="Pilih tahun periode" onchange="document.getElementById('evaluatorPeriodFilterForm').submit()" class="form-control-custom" style="padding: 4px 8px; font-size: 12.5px; width: auto; border-radius: 6px; border: 1px solid #CBD5E1; cursor: pointer;">
                @for($y = 2024; $y <= 2028; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <noscript>
                <button type="submit" style="background: #00223B; color: #FFFFFF; border: none; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                    Pilih
                </button>
            </noscript>
        </form>

        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" class="spbe-btn-primary">
            <span>Mulai Penilaian</span>
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
    </div>
</div>

<!-- SLA Countdown Pengingat Penilai (Tenang & Informatif) -->
@if($totalPending > 0 && $periodStatus !== 'closed')
    <div class="animate-fade-up" style="background: #F8FAFC; border: 1px solid #CBD5E1; border-inline-start: 4px solid #00223B; border-radius: 8px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00223B" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <div>
                <strong style="color: #0F172A; font-size: 13px;">Pengingat Batas Waktu Evaluasi:</strong>
                <span style="color: #475569; font-size: 12.5px;">Periode <strong>{{ $monthName }} {{ $year }}</strong> berakhir pada <strong>{{ $slaCutoffDate }}</strong>. Masih ada <strong>{{ $totalPending }} karyawan</strong> menunggu penilaian.</span>
            </div>
        </div>
        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" class="action-chip-link action-chip-neutral" style="font-size: 11.5px;">
            Buka Daftar Penilaian &rarr;
        </a>
    </div>
@endif

@if(\Illuminate\Support\Facades\Hash::check('password123', Auth::user()->password))
    <div class="animate-fade-up" style="background: #F8FAFC; border: 1px solid #CBD5E1; border-inline-start: 4px solid #475569; border-radius: 8px; padding: 12px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#334155" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <strong style="color: #0F172A; font-size: 13px;">Peringatan Keamanan Kredensial:</strong>
                <span style="color: #475569; font-size: 12.5px;">Akun penilai Anda masih menggunakan kata sandi bawaan sistem. Segera ganti kata sandi Anda.</span>
            </div>
        </div>
        <a href="{{ route('profile.index') }}" class="action-chip-link action-chip-neutral" style="font-size: 11.5px;">
            Ganti Kata Sandi &rarr;
        </a>
    </div>
@endif

<!-- 4 Calibrated Institutional KPI Cards -->
<div class="dashboard-kpi-grid">
    <!-- 1. Total Karyawan Divisi -->
    <div class="card-interactive animate-fade-up delay-1" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Total Karyawan Divisi" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Total Karyawan Divisi</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalEmployees }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $totalEmployees }}</span>
            <span style="font-size: 12.5px; color: #64748B; font-weight: 500;">karyawan TAD</span>
        </div>
        <div style="margin-top: 10px; font-size: 11.5px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 8px;">
            Unit: <strong style="color: #0F172A;">{{ $division->name ?? 'Internal' }}</strong>
        </div>
    </div>

    <!-- 2. Selesai Dinilai -->
    <div class="card-interactive animate-fade-up delay-2" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Selesai Dinilai" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Selesai Dinilai</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalEvaluated }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $totalEvaluated }}</span>
            <span style="font-size: 12.5px; color: #64748B;">/ {{ $totalEmployees }} TAD</span>
            <span class="badge-clean {{ $progressPercentage == 100 ? 'badge-clean-navy' : 'badge-clean-neutral' }}" style="margin-inline-start: auto;">
                {{ $progressPercentage }}%
            </span>
        </div>
        <div style="margin-top: 10px;">
            <div style="width: 100%; height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                <div style="width: {{ $progressPercentage }}%; height: 100%; background: #00223B; border-radius: 999px; transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);"></div>
            </div>
        </div>
    </div>

    <!-- 3. Menunggu Penilaian -->
    <div class="card-interactive animate-fade-up delay-3" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Menunggu Penilaian" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Menunggu Penilaian</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalPending }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $totalPending }}</span>
            <span style="font-size: 12.5px; color: #64748B;">karyawan</span>
            @if($totalPending > 0)
                <span class="badge-clean badge-clean-accent" style="margin-inline-start: auto;">Berjalan</span>
            @else
                <span class="badge-clean badge-clean-navy" style="margin-inline-start: auto;">Tuntas</span>
            @endif
        </div>
        <div style="margin-top: 10px; font-size: 11.5px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 8px;">
            {{ $totalPending > 0 ? 'Segera input evaluasi sebelum batas cutoff' : 'Semua penilaian divisi telah selesai' }}
        </div>
    </div>

    <!-- 4. Rata-Rata Skor Divisi -->
    <div class="card-interactive animate-fade-up delay-4" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Rata-Rata Skor Divisi" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Rata-Rata Skor Divisi</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;">
            <span class="tabular-nums kpi-val" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">
                {{ $averageScore > 0 ? number_format($averageScore, 1) : '-' }}
            </span>
            <span style="font-size: 13px; color: #64748B; font-weight: 600;">/ 100</span>

            @if($momDelta !== null)
                <span class="badge-clean {{ $momDelta >= 0 ? 'badge-clean-success' : 'badge-clean-danger' }}" style="margin-inline-start: auto;" title="Perubahan dibanding bulan {{ $prevMonthName }}">
                    {{ $momDelta >= 0 ? '+' : '' }}{{ $momDelta }} vs {{ $prevMonthName }}
                </span>
            @else
                <span class="badge-clean badge-clean-neutral" style="margin-inline-start: auto;">
                    {{ $periodStatus === 'closed' ? 'Terkunci' : 'Terbuka' }}
                </span>
            @endif
        </div>
        <div style="margin-top: 10px; font-size: 11.5px; color: #64748B; border-top: 1px solid #F1F5F9; padding-top: 8px;">
            Status Sistem: <strong style="color: #0F172A;">{{ $systemStatus }}</strong>
        </div>
    </div>
</div>

<!-- SECTION VISUALISASI: Tren Skor Divisi 12 Bulan + Diagnostik AKHLAK Divisi -->
<div class="golden-grid" style="margin-bottom: 24px;">
    <!-- Left Column (62%): Diagnostik 6 Nilai Inti AKHLAK Divisi (Bar / Radar Switcher) -->
    <div class="spbe-chart-card animate-fade-up delay-2">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
            <div>
                <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                    Diagnostik Mutu AKHLAK &mdash; {{ $division->name ?? 'Divisi' }}
                </h3>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">Rata-rata pencapaian 6 nilai inti TAD di unit kerja Anda</p>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                @if($weakestPillar)
                    <span class="badge-clean badge-clean-neutral">
                        Fokus Bimbingan: {{ $weakestPillar['name'] }} ({{ $weakestPillar['score'] }}%)
                    </span>
                @endif
                <div class="spbe-segmented-toggle" role="group" aria-label="Pilih mode tampilan grafik AKHLAK divisi">
                    <button type="button" class="spbe-segmented-btn is-active" id="userAkhlakBarBtn" onclick="switchUserAkhlakMode('bar')">Batang</button>
                    <button type="button" class="spbe-segmented-btn" id="userAkhlakRadarBtn" onclick="switchUserAkhlakMode('radar')">Radar</button>
                </div>
            </div>
        </div>

        @if($totalEvaluated === 0)
            <div style="padding: 32px 20px; text-align: center; color: #64748B; font-size: 13px; background: #F8FAFC; border-radius: 8px; border: 1px dashed #CBD5E1;">
                <div style="font-weight: 700; color: #0F172A; margin-bottom: 4px;">Belum Ada Evaluasi Selesai</div>
                <div>Belum ada nilai evaluasi yang tersimpan untuk divisi Anda pada bulan {{ $monthName }} {{ $year }}.</div>
            </div>
        @else
            <div class="spbe-chart-canvas-wrap is-compact" style="margin-bottom: 16px;">
                <canvas id="userAkhlakChart"
                        aria-label="Grafik diagnostik 6 nilai inti AKHLAK divisi"
                        data-pillars="{{ json_encode($akhlakChartData) }}"></canvas>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px 18px; padding-top: 12px; border-top: 1px solid #F1F5F9;">
                @foreach($akhlakStats as $pillar)
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <span style="font-size: 12.5px; font-weight: 600; color: #334155;">{{ $pillar['name'] }}</span>
                            <span class="tabular-nums" style="font-size: 12.5px; font-weight: 700; color: #0F172A;">
                                {{ $pillar['score_100'] }}% <span style="font-size: 11px; color: #64748B; font-weight: 500;">({{ $pillar['raw_avg'] }})</span>
                            </span>
                        </div>
                        <div style="width: 100%; height: 5px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                            <div style="width: {{ $pillar['score_100'] }}%; height: 100%; background: #00223B; border-radius: 999px;"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Right Column (38%): Line Chart Tren Rata-Rata Skor Divisi 12 Bulan -->
    <div class="spbe-chart-card animate-fade-up delay-3" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
                <div>
                    <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                        Tren Skor Divisi ({{ $year }})
                    </h3>
                    <p style="font-size: 12.5px; color: #64748B; margin: 0;">Pergeseran nilai rata-rata divisi selama 12 bulan</p>
                </div>
                <span class="badge-clean badge-clean-neutral">{{ $year }}</span>
            </div>

            <div class="spbe-chart-canvas-wrap">
                <canvas id="userTrendChart"
                        aria-label="Grafik garis tren skor divisi 12 bulan"
                        data-trend="{{ json_encode($monthlyTrend ?? []) }}"
                        data-active-month="{{ $month }}"></canvas>
            </div>
        </div>

        @php
            $userFilledMonths = collect($monthlyTrend ?? [])->filter(fn ($i) => $i['avg_score'] !== null);
            $userAnnualAvg = $userFilledMonths->isNotEmpty() ? round($userFilledMonths->avg('avg_score'), 1) : 0;
        @endphp
        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9; display: flex; justify-content: space-between; font-size: 11.5px; color: #64748B;">
            <span>Bulan Terisi: <strong style="color: #0F172A;">{{ $userFilledMonths->count() }}/12</strong></span>
            <span>Rata-Rata Tahunan: <strong style="color: #00223B;">{{ $userAnnualAvg > 0 ? number_format($userAnnualAvg, 1) : '-' }}</strong></span>
        </div>
    </div>
</div>

<!-- Golden Grid: Action Queue (62%) vs Status Divisi & Predikat (38%) -->
<div class="golden-grid" style="margin-bottom: 28px;">
    <!-- Left Column (62%): Prioritas Antrian Penilaian TAD (High Utility) -->
    <div class="card-interactive animate-fade-up delay-2" style="padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                    Antrian Penilaian Karyawan TAD
                </h3>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">Karyawan di divisi Anda yang belum dilakukan input penilaian bulan {{ $monthName }} {{ $year }}</p>
            </div>
            <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" class="action-chip-link action-chip-neutral">
                Buka Matriks Penilaian &rarr;
            </a>
        </div>

        @if($pendingEmployees->isEmpty())
            <div style="padding: 24px 20px; text-align: center; background: #F8FAFC; border-radius: 12px; border: 1px solid #CBD5E1;">
                <div style="width: 44px; height: 44px; border-radius: 50%; background: #00223B; color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px;">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h4 style="font-size: 16px; font-weight: 800; color: #0F172A; margin: 0 0 4px 0;">Divisi 100% Tuntas Dievaluasi</h4>
                <p style="font-size: 13px; color: #475569; margin: 0 0 16px 0;">Seluruh {{ $totalEmployees }} personel Tenaga Alih Daya telah selesai dinilai untuk periode {{ $monthName }} {{ $year }}.</p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 16px; text-align: start;">
                    <div style="background: #FFFFFF; padding: 10px 14px; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <div style="font-size: 11px; color: #64748B; font-weight: 600; text-transform: uppercase;">Rata-Rata Divisi</div>
                        <div style="font-size: 18px; font-weight: 800; color: #0F172A;" class="tabular-nums">{{ $averageScore > 0 ? number_format($averageScore, 1) : '-' }}</div>
                    </div>
                    <div style="background: #FFFFFF; padding: 10px 14px; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <div style="font-size: 11px; color: #64748B; font-weight: 600; text-transform: uppercase;">Total Dinilai</div>
                        <div style="font-size: 18px; font-weight: 800; color: #0F172A;" class="tabular-nums">{{ $totalEvaluated }} TAD</div>
                    </div>
                    <div style="background: #FFFFFF; padding: 10px 14px; border-radius: 8px; border: 1px solid #E2E8F0;">
                        <div style="font-size: 11px; color: #64748B; font-weight: 600; text-transform: uppercase;">Kepatuhan SLA</div>
                        <div style="font-size: 18px; font-weight: 800; color: #0F172A;">Tepat Waktu</div>
                    </div>
                </div>

                <a href="{{ route('laporan.index', ['month' => $month, 'year' => $year]) }}" class="spbe-btn-primary" style="padding: 8px 18px;">
                    <span>Lihat Rekapitulasi &amp; Cetak Nilai Divisi</span>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
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
                                    <div style="font-size: 11px; color: #64748B;">{{ $emp->vendor_name ?? $emp->vendor ?? 'PT Pelindo Daya Sejahtera' }}</div>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    @if($periodStatus === 'closed')
                                        <span class="badge-clean badge-clean-neutral" title="Periode divisi telah dikunci oleh administrator">
                                            Terkunci
                                        </span>
                                    @else
                                        <a href="{{ route('penilaian.create', ['employee_id' => $emp->id, 'month' => $month, 'year' => $year]) }}" class="spbe-btn-primary" style="padding: 6px 14px; font-size: 12px;">
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
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #CBD5E1;">
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
                    <strong style="color: #FFFFFF;">{{ $progressPercentage }}%</strong>
                </div>
                <div style="width: 100%; height: 6px; background: rgba(255,255,255,0.15); border-radius: 999px; overflow: hidden;">
                    <div style="width: {{ $progressPercentage }}%; height: 100%; background: #FFFFFF; border-radius: 999px; transition: width 0.8s ease;"></div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #CBD5E1;">
                <span>Status Kunci: <strong style="color: #FFFFFF;">{{ $periodStatus === 'closed' ? 'Terkunci' : 'Aktif / Terbuka' }}</strong></span>
                @if($periodStatus === 'closed')
                    <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" style="color: #FFFFFF; font-weight: 600; text-decoration: underline;">Ajukan Buka &rarr;</a>
                @endif
            </div>
        </div>

        <!-- Predikat Breakdown Divisi (Semantic colors reserved for Predicate classifications) -->
        <div class="card-interactive animate-fade-up delay-4" style="padding: 20px;">
            <h4 style="font-size: 13.5px; font-weight: 700; color: #0F172A; margin: 0 0 12px 0;">
                Distribusi Mutu Divisi Ini
            </h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                    <span class="badge-clean badge-clean-success" style="font-size: 10.5px;">Sangat Baik (&ge; 85)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: #0F172A;">{{ $predicateCounts['Sangat Baik'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                    <span class="badge-clean badge-clean-accent" style="font-size: 10.5px;">Baik (70 - 84.9)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: #0F172A;">{{ $predicateCounts['Baik'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid #F1F5F9;">
                    <span class="badge-clean badge-clean-warning" style="font-size: 10.5px;">Cukup Baik (55 - 69.9)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: #0F172A;">{{ $predicateCounts['Cukup Baik'] }}</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; padding: 6px 0;">
                    <span class="badge-clean badge-clean-danger" style="font-size: 10.5px;">Perlu Perhatian (&lt; 55)</span>
                    <span class="tabular-nums" style="font-weight: 700; color: #0F172A;">{{ $predicateCounts['Perlu Perhatian'] }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
<script>
    var userAkhlakChartInstance = null;

    function renderUserAkhlakChart(mode) {
        var canvas = document.getElementById('userAkhlakChart');
        if (!canvas || typeof Chart === 'undefined') return;

        var pillars = [];
        try {
            pillars = JSON.parse(canvas.getAttribute('data-pillars') || '[]');
        } catch (e) {
            return;
        }
        if (!pillars.length) return;

        if (userAkhlakChartInstance) {
            userAkhlakChartInstance.destroy();
        }

        var labels = pillars.map(function (p) { return p.name; });
        var scores = pillars.map(function (p) { return p.score; });

        if (mode === 'radar') {
            userAkhlakChartInstance = new Chart(canvas.getContext('2d'), {
                type: 'radar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Capaian (%)',
                        data: scores,
                        backgroundColor: 'rgba(0, 34, 59, 0.14)',
                        borderColor: '#00223B',
                        borderWidth: 2,
                        pointBackgroundColor: '#00223B',
                        pointRadius: 3.5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            ticks: { stepSize: 25, font: { size: 10 }, color: '#64748B', backdropColor: 'transparent' },
                            pointLabels: { font: { size: 11, weight: '600' }, color: '#334155' },
                            grid: { color: '#E2E8F0' },
                            angleLines: { color: '#E2E8F0' }
                        }
                    }
                }
            });
        } else {
            userAkhlakChartInstance = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Capaian (%)',
                        data: scores,
                        backgroundColor: '#00223B',
                        borderRadius: 5,
                        barThickness: 18
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) { return ' ' + ctx.parsed.x + '%'; }
                            }
                        }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 100,
                            grid: { color: '#F1F5F9' },
                            ticks: { font: { size: 11 }, color: '#64748B', callback: function (v) { return v + '%'; } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 11.5, weight: '600' }, color: '#334155' }
                        }
                    }
                }
            });
        }
    }

    function switchUserAkhlakMode(mode) {
        var barBtn = document.getElementById('userAkhlakBarBtn');
        var radarBtn = document.getElementById('userAkhlakRadarBtn');
        if (barBtn && radarBtn) {
            barBtn.classList.toggle('is-active', mode === 'bar');
            radarBtn.classList.toggle('is-active', mode === 'radar');
        }
        renderUserAkhlakChart(mode);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!reduceMotion) {
            var counters = document.querySelectorAll('.counter');
            var duration = 450;
            counters.forEach(function (counter) {
                var target = +counter.getAttribute('data-target') || 0;
                if (target === 0) return;
                var startTime = performance.now();
                function updateCounter(currentTime) {
                    var elapsed = currentTime - startTime;
                    var progress = Math.min(elapsed / duration, 1);
                    var easeProgress = 1 - (1 - progress) * (1 - progress);
                    counter.innerText = Math.floor(easeProgress * target);
                    if (progress < 1) {
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.innerText = target;
                    }
                }
                requestAnimationFrame(updateCounter);
            });
        }

        if (typeof Chart === 'undefined') {
            return;
        }

        Chart.defaults.font.family = "'Plus Jakarta Sans', 'Inter', sans-serif";
        Chart.defaults.color = '#475569';

        renderUserAkhlakChart('bar');

        var trendCanvas = document.getElementById('userTrendChart');
        if (trendCanvas) {
            try {
                var trendData = JSON.parse(trendCanvas.getAttribute('data-trend') || '[]');
                var activeMonth = parseInt(trendCanvas.getAttribute('data-active-month') || '0', 10);
                new Chart(trendCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: trendData.map(function (d) { return d.label; }),
                        datasets: [{
                            label: 'Rata-Rata Divisi',
                            data: trendData.map(function (d) { return d.avg_score; }),
                            borderColor: '#00223B',
                            backgroundColor: 'rgba(0, 34, 59, 0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.28,
                            spanGaps: true,
                            pointRadius: trendData.map(function (d) { return d.month === activeMonth ? 6 : 3.5; }),
                            pointBackgroundColor: trendData.map(function (d) { return d.month === activeMonth ? '#005FA8' : '#00223B'; }),
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 1.5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var item = trendData[ctx.dataIndex];
                                        if (ctx.parsed.y === null) return ' Belum ada data';
                                        return ' Skor: ' + ctx.parsed.y + ' (' + item.count + ' TAD)';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { min: 40, max: 100, grid: { color: '#F1F5F9' }, ticks: { stepSize: 15, font: { size: 11 }, color: '#64748B' } },
                            x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#475569' } }
                        }
                    }
                });
            } catch (e) {}
        }
    });
</script>
@endpush

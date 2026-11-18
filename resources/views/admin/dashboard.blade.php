@extends('layouts.admin', ['title' => 'Executive Dashboard'])

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

    $divisionChartData = collect($divisionStats ?? [])->map(fn ($s) => [
        'name' => $s['division']->name ?? 'Divisi',
        'evaluated' => (int) ($s['evaluated_count'] ?? 0),
        'pending' => (int) ($s['pending_count'] ?? 0),
        'progress' => (int) ($s['progress'] ?? 0),
    ])->values();

    $monthMap = $monthNames ?? [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
@endphp

@section('content')
<!-- Page Header & Filter Context (Standar Nasional SPBE) -->
<div class="animate-fade-up dashboard-header-bar" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
            <h1 style="font-size: var(--font-scale-h1, 24px); font-weight: 800; color: #0F172A; margin: 0; letter-spacing: -0.4px;">
                Executive Dashboard
            </h1>
            <span class="badge-clean badge-clean-navy">
                Cabang Cirebon
            </span>
        </div>
        <p style="font-size: var(--font-scale-caption, 13px); color: #64748B; margin: 0;">
            Monitoring terintegrasi kinerja Tenaga Alih Daya (TAD) &mdash; <strong>{{ $monthName }} {{ $year }}</strong>
            <span style="color: #CBD5E1; margin: 0 6px;">&bull;</span>
            <span>Batas Waktu Pengisian: <strong style="color: #0F172A;">{{ $slaCutoffDate }}</strong></span>
            @if($isCutoffPassed)
                <span class="badge-clean badge-clean-neutral" style="margin-inline-start: 6px; font-size: 10px;">Cutoff Berakhir</span>
            @else
                <span class="badge-clean badge-clean-accent" style="margin-inline-start: 6px; font-size: 10px;">Sisa {{ $slaDaysRemaining }} Hari</span>
            @endif
        </p>
    </div>

    <!-- Quick Period Stepper & Filter -->
    <div class="dashboard-filter-group" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <nav class="spbe-period-stepper" aria-label="Navigasi cepat bulan periode">
            <a href="{{ route('admin.dashboard', ['month' => $prevStepMonth, 'year' => $prevStepYear]) }}" class="spbe-stepper-btn" title="Bulan sebelumnya">
                <span aria-hidden="true">&lsaquo;</span>
                <span>Lalu</span>
            </a>
            <a href="{{ route('admin.dashboard', ['month' => $nowMonth, 'year' => $nowYear]) }}" class="spbe-stepper-btn {{ ($month === $nowMonth && $year === $nowYear) ? 'is-current' : '' }}" title="Lompat ke bulan berjalan">
                Bulan Ini
            </a>
            <a href="{{ route('admin.dashboard', ['month' => $nextStepMonth, 'year' => $nextStepYear]) }}" class="spbe-stepper-btn" title="Bulan berikutnya">
                <span>Depan</span>
                <span aria-hidden="true">&rsaquo;</span>
            </a>
        </nav>

        <form id="adminPeriodFilterForm" method="GET" action="{{ route('admin.dashboard') }}" style="display: flex; align-items: center; gap: 8px; background: #FFFFFF; padding: 5px 10px; border-radius: 8px; border: 1px solid var(--border-hairline, #E2E8F0); box-shadow: 0 1px 2px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; gap: 5px; color: #475569; font-size: 12px; font-weight: 600;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <label for="adminFilterMonth">Periode:</label>
            </div>
            <select id="adminFilterMonth" name="month" aria-label="Pilih bulan periode" onchange="document.getElementById('adminPeriodFilterForm').submit()" class="form-control-custom" style="padding: 4px 8px; font-size: 12.5px; width: auto; border-radius: 6px; border: 1px solid #CBD5E1; cursor: pointer;">
                @foreach($monthMap as $m => $name)
                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <select id="adminFilterYear" name="year" aria-label="Pilih tahun periode" onchange="document.getElementById('adminPeriodFilterForm').submit()" class="form-control-custom" style="padding: 4px 8px; font-size: 12.5px; width: auto; border-radius: 6px; border: 1px solid #CBD5E1; cursor: pointer;">
                @for($y = 2024; $y <= 2028; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <noscript>
                <button type="submit" style="background: #00223B; color: #FFFFFF; border: none; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                    Terapkan
                </button>
            </noscript>
        </form>

        <a href="{{ route('admin.reports.index', ['month' => $month, 'year' => $year]) }}" class="spbe-btn-neutral" style="padding: 7px 14px; font-size: 12.5px;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <span>Laporan Eksekutif</span>
        </a>

        <a href="{{ route('admin.backup.index') }}#oneTapReleaseSection" class="spbe-btn-neutral" style="padding: 7px 14px; font-size: 12.5px; border-color: #15803d; color: #166534; background: #f0fdf4;" title="Buka Pusat Kontrol One-Tap Official Release">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/></svg>
            <span>One-Tap Go-Live</span>
        </a>
    </div>
</div>

@if(\Illuminate\Support\Facades\Hash::check('password123', Auth::user()->password))
    <div class="animate-fade-up" style="background: #F8FAFC; border: 1px solid #CBD5E1; border-inline-start: 4px solid #00223B; border-radius: 8px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00223B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <div>
                <strong style="color: #0F172A; font-size: 13px;">Peringatan Keamanan Administrator:</strong>
                <span style="color: #475569; font-size: 12.5px;">Akun Super Administrator Anda masih menggunakan kata sandi bawaan sistem. Segera perbarui di menu profil.</span>
            </div>
        </div>
        <a href="{{ route('profile.index') }}" class="action-chip-link action-chip-neutral" style="font-size: 11.5px;">
            Ganti Kata Sandi &rarr;
        </a>
    </div>
@endif

<!-- 4 Calibrated Institutional KPI Cards (Restrained Monochrome + Predikat Badges) -->
<div class="dashboard-kpi-grid">
    <!-- 1. Total Tenaga Alih Daya -->
    <div class="card-interactive animate-fade-up delay-1" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Total Tenaga Alih Daya" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Total Tenaga Alih Daya</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $totalEmployees }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $totalEmployees }}</span>
            <span style="font-size: 12.5px; color: #64748B; font-weight: 500;">karyawan</span>
        </div>
        <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #64748B; padding-top: 6px; border-top: 1px solid #F1F5F9;">
            <span>Mitra: <strong style="color: #0F172A;">PT PDS</strong></span>
            <span class="badge-clean badge-clean-neutral" style="font-size: 10.5px;">{{ $totalDivisions }} Divisi</span>
        </div>
    </div>

    <!-- 2. Kelengkapan Evaluasi -->
    <div class="card-interactive animate-fade-up delay-2" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Kelengkapan Evaluasi" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Kelengkapan Evaluasi</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums counter kpi-val" data-target="{{ $branchProgress }}" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $branchProgress }}</span>
            <span style="font-size: 18px; font-weight: 700; color: #0F172A;">%</span>
            <span class="badge-clean {{ $branchProgress == 100 ? 'badge-clean-navy' : 'badge-clean-neutral' }}" style="margin-inline-start: auto;">
                {{ $branchProgress == 100 ? 'Lengkap' : 'Berjalan' }}
            </span>
        </div>
        <div style="margin-top: 10px;">
            <div style="width: 100%; height: 6px; background: #E2E8F0; border-radius: 999px; overflow: hidden;">
                <div style="width: {{ $branchProgress }}%; height: 100%; background: #00223B; border-radius: 999px; transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 11.5px; color: #64748B; margin-top: 6px;">
                <span>Dinilai: <strong style="color: #0F172A;">{{ $totalEvaluated }}</strong></span>
                <span>Belum: <strong style="color: #0F172A;">{{ $totalPending }}</strong></span>
            </div>
        </div>
    </div>

    <!-- 3. Rata-Rata Skor Kinerja (Tanpa Rentang Min/Max) -->
    <div class="card-interactive animate-fade-up delay-3" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Rata-Rata Skor Kinerja" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Rata-Rata Skor Kinerja</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;">
            <span class="tabular-nums kpi-val" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">
                {{ $averageScore > 0 ? number_format($averageScore, 1) : '-' }}
            </span>
            <span style="font-size: 13px; color: #64748B; font-weight: 600;">/ 100</span>

            @php
                $avgPredBadge = match ($averagePredicate) {
                    'Sangat Baik' => 'badge-clean-success',
                    'Baik' => 'badge-clean-accent',
                    'Cukup Baik' => 'badge-clean-warning',
                    'Perlu Perhatian' => 'badge-clean-danger',
                    default => 'badge-clean-neutral',
                };
            @endphp
            @if($momDelta !== null)
                <span class="badge-clean {{ $momDelta >= 0 ? 'badge-clean-success' : 'badge-clean-danger' }}" style="margin-inline-start: auto;" title="Perubahan dibanding bulan {{ $prevMonthName }}">
                    {{ $momDelta >= 0 ? '+' : '' }}{{ $momDelta }} vs {{ $prevMonthName }}
                </span>
            @else
                <span class="badge-clean {{ $avgPredBadge }}" style="margin-inline-start: auto;">
                    {{ $averagePredicate }}
                </span>
            @endif
        </div>
        <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #64748B; padding-top: 6px; border-top: 1px solid #F1F5F9;">
            <span>Predikat: <strong style="color: #0F172A;">{{ $averagePredicate }}</strong></span>
            <span>Sampel: <strong style="color: #0F172A;">{{ $totalEvaluated }} / {{ $totalEmployees }} TAD</strong></span>
        </div>
    </div>

    <!-- 4. Kepatuhan Periode -->
    <div class="card-interactive animate-fade-up delay-4" style="padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
            <span class="kpi-label" title="Kepatuhan Periode" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.3px;">Kepatuhan Periode</span>
            <div class="spbe-kpi-icon">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <span class="tabular-nums kpi-val" style="font-size: var(--font-scale-hero, 34px); font-weight: 800; color: #0F172A; line-height: 1;">{{ $lockedDivisionsCount }}</span>
            <span style="font-size: 15px; color: #64748B; font-weight: 600;">/ {{ $totalDivisions }} Divisi</span>
            @if($pendingRequestsCount > 0)
                <a href="{{ route('admin.requests.index') }}" class="action-chip-link action-chip-neutral" style="margin-inline-start: auto;" title="Tinjau Permohonan">
                    {{ $pendingRequestsCount }} Pending
                </a>
            @else
                <span class="badge-clean badge-clean-neutral" style="margin-inline-start: auto;">Terkunci</span>
            @endif
        </div>
        <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #64748B; padding-top: 6px; border-top: 1px solid #F1F5F9;">
            <span>Batas SLA: <strong style="color: #0F172A;">25 {{ $monthName }} {{ $year }}</strong></span>
            <a href="{{ route('admin.periods.index', ['year' => $year]) }}" style="color: #00223B; text-decoration: none; font-weight: 700;">Kelola &rarr;</a>
        </div>
    </div>
</div>

<!-- VISUALISASI 1 & 2: Tren Rata-Rata Skor 12 Bulan + Distribusi Predikat & Rekomendasi Kontrak -->
<div class="golden-grid" style="margin-bottom: 24px;">
    <!-- Kolom Kiri (62%): Chart.js Line Chart Tren 12 Bulan -->
    <div class="spbe-chart-card animate-fade-up delay-2">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
            <div>
                <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                    Tren Rata-Rata Skor Kinerja 12 Bulan ({{ $year }})
                </h3>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">Pergerakan indeks mutu TAD sepanjang tahun kalender beserta garis acuan SLA (80,0)</p>
            </div>
            <span class="badge-clean badge-clean-neutral">Tahun {{ $year }}</span>
        </div>

        <div class="spbe-chart-canvas-wrap">
            <canvas id="adminTrendChart"
                    aria-label="Grafik garis tren rata-rata skor kinerja 12 bulan"
                    data-trend="{{ json_encode($monthlyTrend ?? []) }}"
                    data-active-month="{{ $month }}"></canvas>
        </div>

        @php
            $filledMonths = collect($monthlyTrend ?? [])->filter(fn ($item) => $item['avg_score'] !== null);
            $annualMean = $filledMonths->isNotEmpty() ? round($filledMonths->avg('avg_score'), 1) : 0;
            $annualEvalTotal = collect($monthlyTrend ?? [])->sum('count');
        @endphp
        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: 12px; color: #64748B;">
            <span>Bulan Terisi Data: <strong style="color: #0F172A;">{{ $filledMonths->count() }} dari 12 Bulan</strong></span>
            <span>Akumulasi Evaluasi {{ $year }}: <strong style="color: #0F172A;">{{ $annualEvalTotal }} Rekaman</strong></span>
            <span>Rata-Rata Tahunan: <strong style="color: #00223B;">{{ $annualMean > 0 ? number_format($annualMean, 1) : '-' }}</strong></span>
        </div>
    </div>

    <!-- Kolom Kanan (38%): Distribusi Mutu (Predikat) & Rekomendasi Kontrak PDS -->
    <div class="spbe-chart-card animate-fade-up delay-3" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
                <div>
                    <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                        Distribusi Predikat &amp; Retensi
                    </h3>
                    <p style="font-size: 12.5px; color: #64748B; margin: 0;">Proporsi predikat &amp; kesiapan kontrak PT PDS</p>
                </div>
                <span class="badge-clean badge-clean-navy" style="font-size: 10px;">Vendor SLA</span>
            </div>

            <!-- Distribusi 4 Predikat (Semantic colors allowed here for Predikat) -->
            @php
                $predStyles = [
                    'Sangat Baik' => ['color' => '#047857', 'badge' => 'badge-clean-success'],
                    'Baik' => ['color' => '#005FA8', 'badge' => 'badge-clean-accent'],
                    'Cukup Baik' => ['color' => '#92400E', 'badge' => 'badge-clean-warning'],
                    'Perlu Perhatian' => ['color' => '#B91C1C', 'badge' => 'badge-clean-danger'],
                ];
            @endphp
            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
                @foreach($predicateCounts as $predKey => $predCount)
                    @php
                        $pct = $predicatePercentages[$predKey] ?? 0;
                        $pStyle = $predStyles[$predKey] ?? ['color' => '#475569', 'badge' => 'badge-clean-neutral'];
                    @endphp
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; margin-bottom: 4px;">
                            <span class="badge-clean {{ $pStyle['badge'] }}" style="font-size: 10px; padding: 2px 8px;">{{ $predKey }}</span>
                            <span class="tabular-nums" style="font-weight: 700; color: #0F172A;">{{ $predCount }} TAD <span style="color: #64748B; font-weight: 500;">({{ $pct }}%)</span></span>
                        </div>
                        <div style="width: 100%; height: 6px; background: #F1F5F9; border-radius: 999px; overflow: hidden;">
                            <div style="width: {{ $pct }}%; height: 100%; background: {{ $pStyle['color'] }}; border-radius: 999px;"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Rekomendasi Kontrak PDS (Restrained neutral surfaces with left indicator) -->
            <div style="display: flex; flex-direction: column; gap: 8px; padding-top: 12px; border-top: 1px solid #F1F5F9;">
                <div class="spbe-contract-row" style="border-inline-start-color: #047857;">
                    <div>
                        <div style="font-size: 12.5px; font-weight: 700; color: #0F172A;">Rekomendasi Diperpanjang</div>
                        <div style="font-size: 11px; color: #64748B;">Skor Kinerja &ge; 80,0</div>
                    </div>
                    <div class="tabular-nums" style="font-size: 17px; font-weight: 800; color: #0F172A;">
                        {{ $contractRecommendations['perpanjang'] }} <span style="font-size: 11px; font-weight: 500; color: #64748B;">TAD</span>
                    </div>
                </div>

                <div class="spbe-contract-row" style="border-inline-start-color: #92400E;">
                    <div>
                        <div style="font-size: 12.5px; font-weight: 700; color: #0F172A;">Perpanjang Bersyarat</div>
                        <div style="font-size: 11px; color: #64748B;">Skor 70,0 &mdash; 79,9 (Masa Uji)</div>
                    </div>
                    <div class="tabular-nums" style="font-size: 17px; font-weight: 800; color: #0F172A;">
                        {{ $contractRecommendations['bersyarat'] }} <span style="font-size: 11px; font-weight: 500; color: #64748B;">TAD</span>
                    </div>
                </div>

                <div class="spbe-contract-row" style="border-inline-start-color: #B91C1C;">
                    <div>
                        <div style="font-size: 12.5px; font-weight: 700; color: #0F172A;">Evaluasi Pembinaan</div>
                        <div style="font-size: 11px; color: #64748B;">Skor Kinerja &lt; 70,0</div>
                    </div>
                    <div class="tabular-nums" style="font-size: 17px; font-weight: 800; color: #0F172A;">
                        {{ $contractRecommendations['evaluasi'] }} <span style="font-size: 11px; font-weight: 500; color: #64748B;">TAD</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: #64748B;">
            <span>Verifikasi: <strong style="color: #0F172A;">SDM Cabang</strong></span>
            <a href="{{ route('admin.reports.index', ['month' => $month, 'year' => $year]) }}" style="color: #00223B; text-decoration: none; font-weight: 700;">Lihat Rekapitulasi &rarr;</a>
        </div>
    </div>
</div>

<!-- VISUALISASI 3 & 4: Diagnostik 6 Pilar AKHLAK (Bar/Radar) + Progres Penilaian Periode Berjalan per Divisi -->
<div class="golden-grid" style="margin-bottom: 24px;">
    <!-- Kolom Kiri (62%): 6 Nilai Inti AKHLAK BUMN -->
    <div class="spbe-chart-card animate-fade-up delay-2">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
            <div>
                <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                    Diagnostik 6 Nilai Inti AKHLAK BUMN
                </h3>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">Skor rata-rata per pilar kompetensi seluruh TAD Cabang ({{ $monthName }} {{ $year }})</p>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                @if($weakestPillar)
                    <span class="badge-clean badge-clean-neutral" title="Pilar fokus peningkatan mutu">
                        Fokus Mutu: {{ $weakestPillar['name'] }} ({{ $weakestPillar['score'] }}%)
                    </span>
                @endif
                <div class="spbe-segmented-toggle" role="group" aria-label="Pilih mode tampilan grafik AKHLAK">
                    <button type="button" class="spbe-segmented-btn is-active" id="adminAkhlakBarBtn" onclick="switchAdminAkhlakMode('bar')">Batang</button>
                    <button type="button" class="spbe-segmented-btn" id="adminAkhlakRadarBtn" onclick="switchAdminAkhlakMode('radar')">Radar</button>
                </div>
            </div>
        </div>

        @if($totalEvaluated === 0)
            <div style="padding: 36px 20px; text-align: center; color: #64748B; font-size: 13px; background: #F8FAFC; border-radius: 8px; border: 1px dashed #CBD5E1;">
                Belum ada data evaluasi yang tersimpan untuk periode {{ $monthName }} {{ $year }}.
            </div>
        @else
            <div class="spbe-chart-canvas-wrap is-compact" style="margin-bottom: 16px;">
                <canvas id="adminAkhlakChart"
                        aria-label="Grafik diagnostik 6 pilar nilai inti AKHLAK"
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

    <!-- Kolom Kanan (38%): Progres Penilaian Periode Berjalan (Stacked Bar Chart) -->
    <div class="spbe-chart-card animate-fade-up delay-3">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9;">
            <div>
                <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                    Progres Penilaian per Divisi
                </h3>
                <p style="font-size: 12.5px; color: #64748B; margin: 0;">Sudah dinilai vs belum dinilai ({{ $monthName }} {{ $year }})</p>
            </div>
            <a href="{{ route('admin.periods.index', ['year' => $year]) }}" class="action-chip-link action-chip-neutral">
                Periode &rarr;
            </a>
        </div>

        <div class="spbe-chart-canvas-wrap is-compact" style="margin-bottom: 14px;">
            <canvas id="adminDivisionProgressChart"
                    aria-label="Grafik batang bertumpuk progres penilaian per divisi"
                    data-divisions="{{ json_encode($divisionChartData) }}"></canvas>
        </div>

        <div class="table-responsive">
            <table class="table-interactive" style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                <thead>
                    <tr style="border-bottom: 1px solid #E2E8F0; color: #64748B; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 8px 6px; text-align: start;">Divisi</th>
                        <th style="padding: 8px 6px; text-align: end;">Progres</th>
                        <th style="padding: 8px 6px; text-align: end;">Rata-Rata</th>
                        <th style="padding: 8px 6px; text-align: center;">Kunci</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($divisionStats as $stat)
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 9px 6px;">
                                <div style="font-weight: 700; color: #0F172A;">{{ $stat['division']->name }}</div>
                                <div style="font-size: 11px; color: #64748B;">{{ $stat['division']->users->where('role', 'kepala_divisi')->first()->name ?? 'Kepala Divisi' }}</div>
                            </td>
                            <td style="padding: 9px 6px; text-align: end;" class="tabular-nums">
                                <strong>{{ $stat['evaluated_count'] }}/{{ $stat['total_employees'] }}</strong>
                                <span style="font-size: 11px; color: #64748B;">({{ $stat['progress'] }}%)</span>
                            </td>
                            <td style="padding: 9px 6px; text-align: end; font-weight: 700; color: #0F172A;" class="tabular-nums">
                                {{ $stat['average_score'] > 0 ? number_format($stat['average_score'], 1) : '-' }}
                            </td>
                            <td style="padding: 9px 6px; text-align: center;">
                                @if($stat['status'] === 'closed')
                                    <span class="badge-clean badge-clean-neutral" style="font-size: 10px;">Terkunci</span>
                                @elseif($stat['status'] === 'edit_approved')
                                    <span class="badge-clean badge-clean-accent" style="font-size: 10px;">Akses Edit</span>
                                @else
                                    <span class="badge-clean badge-clean-accent" style="font-size: 10px;">Terbuka</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- VISUALISASI 5: Heatmap Status Periode & Kepatuhan (Divisi x 12 Bulan) -->
<div class="card animate-fade-up delay-3" style="margin-bottom: 24px; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0, 34, 59, 0.03);">
    <div style="padding: 18px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 style="font-size: var(--font-scale-subhead, 16px); font-weight: 700; color: #0F172A; margin: 0 0 2px 0;">
                Heatmap Status Periode &amp; Kepatuhan Evaluasi ({{ $year }})
            </h3>
            <p style="font-size: 12.5px; color: #64748B; margin: 0;">Klik sel bulan untuk meninjau dasbor periode tersebut per divisi dan kelengkapan input nilai</p>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; font-size: 11.5px; color: #475569; flex-wrap: wrap;">
            <span style="display: inline-flex; align-items: center; gap: 5px;">
                <span aria-hidden="true" style="width: 11px; height: 11px; border-radius: 3px; background: #00223B; display: inline-block;"></span>
                Terisi Nilai
            </span>
            <span style="display: inline-flex; align-items: center; gap: 5px;">
                <span aria-hidden="true" style="width: 11px; height: 11px; border-radius: 3px; background: #EBF5FF; border: 1px solid #BFDBFE; display: inline-block;"></span>
                Periode Terbuka
            </span>
            <span style="display: inline-flex; align-items: center; gap: 5px;">
                <span aria-hidden="true" style="width: 11px; height: 11px; border-radius: 3px; background: #F8FAFC; border: 1px solid #E2E8F0; display: inline-block;"></span>
                Terkunci
            </span>
        </div>
    </div>

    <div class="table-responsive" style="padding: 12px 18px 16px 18px;">
        <table class="spbe-heatmap-table">
            <thead>
                <tr>
                    <th style="text-align: start; min-width: 170px; padding-inline-start: 10px;">Divisi Operasional</th>
                    @for($m = 1; $m <= 12; $m++)
                        <th style="{{ $m === $month ? 'color: #00223B; background: #EFF6FF;' : '' }}">
                            {{ substr($monthMap[$m] ?? (string) $m, 0, 3) }}
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody>
                @forelse($periodHeatmap ?? [] as $row)
                    <tr>
                        <td style="text-align: start; padding-inline-start: 10px; font-weight: 700; color: #0F172A;">
                            {{ $row['division']->name }}
                            <div style="font-size: 11px; font-weight: 500; color: #64748B;">{{ $row['division']->employees_count }} TAD aktif</div>
                        </td>
                        @for($m = 1; $m <= 12; $m++)
                            @php
                                $cell = $row['months'][$m];
                                $isFilled = $cell['evaluated_count'] > 0;
                                $isOpen = $cell['status'] === 'open';
                                $cellClass = $isFilled
                                    ? 'spbe-heatmap-filled'
                                    : ($isOpen ? 'spbe-heatmap-open' : 'spbe-heatmap-closed');
                                if ($m === $month) {
                                    $cellClass .= ' spbe-heatmap-current';
                                }
                                $cellStatusLabel = $isOpen ? 'Terbuka' : 'Terkunci';
                            @endphp
                            <td>
                                <a href="{{ route('admin.dashboard', ['month' => $m, 'year' => $year]) }}"
                                   class="spbe-heatmap-cell {{ $cellClass }}"
                                   title="{{ $row['division']->name }} - {{ $monthMap[$m] ?? $m }} {{ $year }}: {{ $cell['evaluated_count'] }}/{{ $cell['total_employees'] }} TAD ({{ $cellStatusLabel }})">
                                    <span class="tabular-nums" style="font-weight: 700; font-size: 11.5px; line-height: 1.2;">
                                        {{ $cell['evaluated_count'] }}/{{ $cell['total_employees'] }}
                                    </span>
                                    <span class="hm-sub tabular-nums" style="font-size: 10px; margin-top: 2px;">
                                        {{ $cell['avg_score'] !== null ? number_format($cell['avg_score'], 1) : ($isOpen ? 'Buka' : 'Kunci') }}
                                    </span>
                                </a>
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" style="padding: 24px; text-align: center; color: #64748B;">Belum ada divisi aktif terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Antrian Permohonan Akses & Edit (Recent Requests) -->
<div class="card animate-fade-up delay-4" style="margin-bottom: 24px; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0, 34, 59, 0.03);">
    <div style="padding: 16px 20px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#00223B" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <h3 style="font-size: var(--font-scale-subhead, 15px); font-weight: 700; color: #0F172A; margin: 0;">
                Permohonan Akses &amp; Edit Menunggu Persetujuan
            </h3>
        </div>
        <a href="{{ route('admin.requests.index') }}" style="font-size: 12.5px; color: #00223B; font-weight: 700; text-decoration: none;">
            Lihat Semua ({{ $pendingRequestsCount }}) &rarr;
        </a>
    </div>

    @if($recentRequests->isEmpty())
        <div style="padding: 32px 20px; text-align: center; color: #64748B; font-size: 13px;">
            <svg aria-hidden="true" style="margin: 0 auto 8px auto; display: block; color: #94A3B8;" xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Seluruh periode penilaian cabang terkendali. Tidak ada permohonan akses berstatus pending.
        </div>
    @else
        <div class="table-responsive">
            <table class="table-interactive" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; color: #64748B; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 10px 16px; text-align: start;">Tanggal</th>
                        <th style="padding: 10px 16px; text-align: start;">Pemohon (Penilai)</th>
                        <th style="padding: 10px 16px; text-align: start;">Divisi</th>
                        <th style="padding: 10px 16px; text-align: start;">Tipe Permohonan</th>
                        <th style="padding: 10px 16px; text-align: start;">Subjek / Periode</th>
                        <th style="padding: 10px 16px; text-align: start;">Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentRequests as $req)
                        <tr style="border-bottom: 1px solid #F1F5F9;">
                            <td style="padding: 12px 16px; color: #64748B; font-size: 12px;" class="tabular-nums">
                                {{ $req->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB
                            </td>
                            <td style="padding: 12px 16px; font-weight: 600; color: #0F172A;">
                                {{ $req->requester->name ?? '-' }}
                            </td>
                            <td style="padding: 12px 16px; color: #475569;">
                                {{ $req->division->name ?? '-' }}
                            </td>
                            <td style="padding: 12px 16px;">
                                @if($req->request_type === 'division_access')
                                    <span class="badge-clean badge-clean-accent">Akses Divisi</span>
                                @else
                                    <span class="badge-clean badge-clean-neutral">Edit Karyawan</span>
                                @endif
                            </td>
                            <td style="padding: 12px 16px; color: #334155; font-weight: 500;">
                                {{ $req->employee->name ?? 'Semua Karyawan Divisi' }} &mdash; Bulan {{ $req->period_month }}/{{ $req->period_year }}
                            </td>
                            <td style="padding: 12px 16px; color: #64748B; font-size: 12px;">
                                {{ $req->reason }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" integrity="sha384-vsrfeLOOY6KuIYKDlmVH5UiBmgIdB1oEf7p01YgWHuqmOHfZr374+odEv96n9tNC" crossorigin="anonymous"></script>
<script>
    var adminAkhlakChartInstance = null;

    function renderAdminAkhlakChart(mode) {
        var canvas = document.getElementById('adminAkhlakChart');
        if (!canvas || typeof Chart === 'undefined') return;

        var pillars = [];
        try {
            pillars = JSON.parse(canvas.getAttribute('data-pillars') || '[]');
        } catch (e) {
            return;
        }
        if (!pillars.length) return;

        if (adminAkhlakChartInstance) {
            adminAkhlakChartInstance.destroy();
        }

        var labels = pillars.map(function (p) { return p.name; });
        var scores = pillars.map(function (p) { return p.score; });

        if (mode === 'radar') {
            adminAkhlakChartInstance = new Chart(canvas.getContext('2d'), {
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
                    plugins: {
                        legend: { display: false }
                    },
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
            adminAkhlakChartInstance = new Chart(canvas.getContext('2d'), {
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

    function switchAdminAkhlakMode(mode) {
        var barBtn = document.getElementById('adminAkhlakBarBtn');
        var radarBtn = document.getElementById('adminAkhlakRadarBtn');
        if (barBtn && radarBtn) {
            barBtn.classList.toggle('is-active', mode === 'bar');
            radarBtn.classList.toggle('is-active', mode === 'radar');
        }
        renderAdminAkhlakChart(mode);
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Lightweight Number Counter Animation (Respects prefers-reduced-motion)
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

        // 1. Line Chart Tren 12 Bulan
        var trendCanvas = document.getElementById('adminTrendChart');
        if (trendCanvas) {
            try {
                var trendData = JSON.parse(trendCanvas.getAttribute('data-trend') || '[]');
                var activeMonth = parseInt(trendCanvas.getAttribute('data-active-month') || '0', 10);
                var labels = trendData.map(function (d) { return d.label; });
                var values = trendData.map(function (d) { return d.avg_score; });
                var slaLine = trendData.map(function () { return 80; });
                var pointRadii = trendData.map(function (d) { return d.month === activeMonth ? 6 : 3.5; });
                var pointColors = trendData.map(function (d) { return d.month === activeMonth ? '#005FA8' : '#00223B'; });

                new Chart(trendCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Rata-Rata Skor Cabang',
                                data: values,
                                borderColor: '#00223B',
                                backgroundColor: 'rgba(0, 34, 59, 0.08)',
                                borderWidth: 2.5,
                                fill: true,
                                tension: 0.28,
                                spanGaps: true,
                                pointRadius: pointRadii,
                                pointHoverRadius: 7,
                                pointBackgroundColor: pointColors,
                                pointBorderColor: '#FFFFFF',
                                pointBorderWidth: 1.5
                            },
                            {
                                label: 'Target SLA Kontrak (80.0)',
                                data: slaLine,
                                borderColor: '#94A3B8',
                                borderWidth: 1.5,
                                borderDash: [6, 5],
                                fill: false,
                                pointRadius: 0,
                                pointHoverRadius: 0
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'end',
                                labels: { boxWidth: 12, usePointStyle: true, font: { size: 11.5 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        if (ctx.datasetIndex === 1) return ' Target SLA: 80.0';
                                        var item = trendData[ctx.dataIndex];
                                        if (ctx.parsed.y === null) return ' Belum ada penilaian';
                                        return ' Rata-rata: ' + ctx.parsed.y + ' (' + item.count + ' TAD)';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: 40,
                                max: 100,
                                grid: { color: '#F1F5F9' },
                                ticks: { stepSize: 15, font: { size: 11 }, color: '#64748B' }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11.5, weight: '600' }, color: '#475569' }
                            }
                        }
                    }
                });
            } catch (e) {}
        }

        // 2. AKHLAK 6 Pillars Default Mode
        renderAdminAkhlakChart('bar');

        // 3. Stacked Bar Chart Progres Penilaian per Divisi
        var divCanvas = document.getElementById('adminDivisionProgressChart');
        if (divCanvas) {
            try {
                var divData = JSON.parse(divCanvas.getAttribute('data-divisions') || '[]');
                if (divData.length) {
                    new Chart(divCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: divData.map(function (d) { return d.name; }),
                            datasets: [
                                {
                                    label: 'Sudah Dinilai',
                                    data: divData.map(function (d) { return d.evaluated; }),
                                    backgroundColor: '#00223B',
                                    borderRadius: 4
                                },
                                {
                                    label: 'Belum Dinilai',
                                    data: divData.map(function (d) { return d.pending; }),
                                    backgroundColor: '#CBD5E1',
                                    borderRadius: 4
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    align: 'end',
                                    labels: { boxWidth: 10, font: { size: 11 } }
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: { color: '#F1F5F9' },
                                    ticks: { precision: 0, font: { size: 11 }, color: '#64748B' }
                                },
                                y: {
                                    stacked: true,
                                    grid: { display: false },
                                    ticks: { font: { size: 11.5, weight: '600' }, color: '#334155' }
                                }
                            }
                        }
                    });
                }
            } catch (e) {}
        }
    });
</script>
@endpush

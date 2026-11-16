@extends('layouts.admin', ['title' => 'Laporan Eksekutif Kinerja TAD'])

@section('content')
<div class="page-title-box" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">Laporan Eksekutif Kinerja TAD</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Rekapitulasi komprehensif penilaian kinerja Tenaga Alih Daya tingkat Cabang Cirebon</p>
    </div>

    <!-- Export & Print Actions (R2 Separated Actions) -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
        @php
            $exportParams = [
                'period_type' => $periodType,
                'year' => $year,
                'month' => $month,
                'division_id' => $divisionId,
            ];
        @endphp

        <!-- 1. Download CSV -->
        <a href="{{ route('admin.reports.export', array_merge($exportParams, ['format' => 'csv'])) }}"
           onclick="triggerExportFeedback(this, 'Menyiapkan CSV...')"
           style="background: #f0f9ff; color: #0284c7; border: 1px solid #bae6fd; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(2, 132, 199, 0.08);"
           title="Unduh rekapitulasi data format CSV dengan UTF-8 BOM">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
            Download CSV
        </a>

        <!-- 2. Download Excel -->
        <a href="{{ route('admin.reports.export', array_merge($exportParams, ['format' => 'excel'])) }}"
           onclick="triggerExportFeedback(this, 'Menyiapkan Excel...')"
           style="background: #16a34a; color: #ffffff; border: 1px solid #15803d; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(22, 163, 74, 0.2);"
           title="Unduh rekapitulasi format spreadsheet Microsoft Excel (.xls)">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
            Download Excel
        </a>

        <!-- 3. Simpan PDF -->
        <a href="{{ route('admin.reports.print-summary', array_merge($exportParams, ['action' => 'save_pdf'])) }}" target="_blank"
           onclick="triggerExportFeedback(this, 'Menyusun PDF...')"
           style="background: #00223B; color: #ffffff; border: 1px solid #001728; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0, 34, 59, 0.2);"
           title="Buka tampilan siap simpan PDF dengan format A4">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="12 18 12 12"/><polyline points="9 15 12 18 15 15"/></svg>
            Simpan PDF
        </a>

        <!-- 4. Cetak (Print) -->
        <a href="{{ route('admin.reports.print-summary', array_merge($exportParams, ['action' => 'print'])) }}" target="_blank"
           style="background: #0072CE; color: #ffffff; border: 1px solid #005FA8; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0, 114, 206, 0.2);"
           title="Buka dialog cetak peramban langsung">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Cetak (Print)
        </a>
    </div>
</div>

<!-- Mode Tab: Rekap Bulanan vs Rekap Tahunan -->
<div style="display: flex; gap: 12px; margin-bottom: 20px;">
    <a href="{{ route('admin.reports.index', ['period_type' => 'monthly', 'year' => $year, 'month' => $month, 'division_id' => $divisionId]) }}"
       style="padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; {{ $periodType === 'monthly' ? 'background: #00223B; color: #ffffff;' : 'background: #ffffff; color: #475569; border: 1px solid #e2e8f0;' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Rekapitulasi Bulanan
    </a>

    <a href="{{ route('admin.reports.index', ['period_type' => 'annual', 'year' => $year, 'division_id' => $divisionId]) }}"
       style="padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; {{ $periodType === 'annual' ? 'background: #00223B; color: #ffffff;' : 'background: #ffffff; color: #475569; border: 1px solid #e2e8f0;' }}">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Rekapitulasi Tahunan (Akumulatif)
    </a>
</div>

<!-- Filter Bar -->
<div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <form method="GET" action="{{ route('admin.reports.index') }}" style="display: flex; align-items: center; flex-wrap: wrap; gap: 14px;">
        <input type="hidden" name="period_type" value="{{ $periodType }}">

        @if($periodType === 'monthly')
            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 13px; font-weight: 600; color: #475569;">Bulan:</label>
                <select name="month" onchange="this.form.submit()" class="form-control-custom" style="padding: 7px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; width: auto;">
                    @foreach($monthNames as $mNum => $mName)
                        <option value="{{ $mNum }}" {{ $month == $mNum ? 'selected' : '' }}>{{ $mName }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Tahun:</label>
            <select name="year" onchange="this.form.submit()" class="form-control-custom" style="padding: 7px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; width: auto;">
                @for($y = 2024; $y <= 2028; $y++)
                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Divisi:</label>
            <select name="division_id" onchange="this.form.submit()" class="form-control-custom" style="padding: 7px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 6px; width: auto;">
                <option value="all" {{ $divisionId === 'all' ? 'selected' : '' }}>Seluruh Divisi Cabang</option>
                @foreach($divisions as $div)
                    <option value="{{ $div->id }}" {{ (string) $divisionId === (string) $div->id ? 'selected' : '' }}>{{ $div->name }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" style="background: #0072CE; color: #ffffff; border: none; padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
            Tampilkan
        </button>
    </form>
</div>

@if($periodType === 'monthly')
    <!-- Metric Cards Bulanan -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span class="kpi-label" title="Evaluasi Selesai" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748b;">Evaluasi Selesai</span>
                <div style="width: 34px; height: 34px; border-radius: 8px; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a;">{{ $metrics['total_evaluated'] }} / {{ $metrics['target_employees'] }}</div>
            <div style="font-size: 11.5px; color: #0284c7; margin-top: 2px;">{{ $metrics['completion_rate'] }}% Progres Pengisian</div>
        </div>

        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span class="kpi-label" title="Rata-Rata Skor Cabang" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748b;">Rata-Rata Skor Cabang</span>
                <div style="width: 34px; height: 34px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #15803d;">{{ number_format($metrics['average_score'], 2) }}</div>
            <div style="font-size: 11.5px; color: #16a34a; margin-top: 2px; font-weight: 600;">Predikat: {{ $metrics['branch_predicate'] }}</div>
        </div>

        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <span class="kpi-label" title="Skor Tertinggi" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748b;">Skor Tertinggi</span>
                <div style="width: 34px; height: 34px; border-radius: 8px; background: #fefce8; color: #a16207; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
            </div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a;">
                {{ $metrics['highest'] ? number_format($metrics['highest']->total_score, 2) : '-' }}
            </div>
            <div style="font-size: 11.5px; color: #64748b; margin-top: 2px; overflow-wrap: anywhere; line-height: 1.3;" title="{{ $metrics['highest'] ? $metrics['highest']->employee->name : '' }}">
                {{ $metrics['highest'] ? $metrics['highest']->employee->name : 'Belum ada data' }}
            </div>
        </div>

        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div class="kpi-label" title="Distribusi Predikat" tabindex="0" style="font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 8px;">Distribusi Predikat</div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                <span style="color: #15803d; font-weight: 600;">Sangat Baik:</span>
                <span style="font-weight: 700;">{{ $metrics['predicates']['Sangat Baik'] }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                <span style="color: #0369a1; font-weight: 600;">Baik:</span>
                <span style="font-weight: 700;">{{ $metrics['predicates']['Baik'] }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px;">
                <span style="color: #b91c1c; font-weight: 600;">Perlu Perhatian:</span>
                <span style="font-weight: 700;">{{ $metrics['predicates']['Perlu Perhatian'] }}</span>
            </div>
        </div>
    </div>

    <!-- Komparasi Kinerja Antar Divisi -->
    <div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 18px 22px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 12px 0;">Komparasi Nilai Rata-Rata Antar Divisi ({{ $currentMonthName }} {{ $year }})</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
            @foreach($divisionComparisons as $divComp)
                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; background: #fafafa;">
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a;">{{ $divComp['division']->name }}</div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-top: 6px;">
                        <span style="font-size: 20px; font-weight: 800; color: #0284c7;">
                            {{ $divComp['avg_score'] > 0 ? number_format($divComp['avg_score'], 2) : '-' }}
                        </span>
                        <span style="font-size: 11.5px; font-weight: 600; color: #64748b;">
                            {{ $divComp['count'] }} dinilai
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Data Table Bulanan -->
    <div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
                Peringkat &amp; Rekapitulasi Penilaian Karyawan ({{ $currentMonthName }} {{ $year }})
            </h3>
            <span style="font-size: 12px; color: #64748b;">Total {{ $evaluations->count() }} personel selesai dinilai</span>
        </div>

        @if($evaluations->isEmpty())
            <div style="padding: 48px 20px; text-align: center; color: #64748b; font-size: 14px;">
                Belum ada rekaman penilaian pada periode bulan dan tahun ini.
            </div>
        @else
            <div class="table-responsive">
                <table class="table-pelindo data-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 13px 18px; text-align: center; width: 45px;">Rank</th>
                            <th style="padding: 13px 18px; text-align: start;">NIK TAD</th>
                            <th style="padding: 13px 18px; text-align: start;">Nama Karyawan TAD</th>
                            <th style="padding: 13px 18px; text-align: start;">Divisi Penempatan</th>
                            <th style="padding: 13px 18px; text-align: start;">Jabatan / Posisi</th>
                            <th style="padding: 13px 18px; text-align: end;">Total Skor</th>
                            <th style="padding: 13px 18px; text-align: center;">Predikat</th>
                            <th style="padding: 13px 18px; text-align: start;">Pejabat Penilai</th>
                            <th style="padding: 13px 18px; text-align: center; width: 140px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($evaluations as $idx => $eval)
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 13px 18px; text-align: center; font-weight: 800; color: {{ $idx < 3 ? '#d97706' : '#64748b' }};">
                                    #{{ $idx + 1 }}
                                </td>
                                <td style="padding: 13px 18px; font-family: monospace; font-weight: 700; color: #0369a1;">
                                    {{ $eval->employee->nik }}
                                </td>
                                <td style="padding: 13px 18px; font-weight: 700; color: #0f172a;">
                                    {{ $eval->employee->name }}
                                </td>
                                <td style="padding: 13px 18px;">
                                    <span style="background: #f1f5f9; color: #0f172a; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                        {{ $eval->employee->division->name ?? '-' }}
                                    </span>
                                </td>
                                <td style="padding: 13px 18px; color: #334155;">
                                    {{ $eval->employee->position }}
                                </td>
                                <td style="padding: 13px 18px; text-align: end; font-size: 15px; font-weight: 800; color: #0f172a;">
                                    {{ number_format($eval->total_score, 2) }}
                                </td>
                                <td style="padding: 13px 18px; text-align: center;">
                                    @if($eval->predicate === 'Sangat Baik')
                                        <span class="badge-predicate badge-predicate-sangat-baik" style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;">Sangat Baik</span>
                                    @elseif($eval->predicate === 'Baik')
                                        <span class="badge-predicate badge-predicate-baik" style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;">Baik</span>
                                    @elseif($eval->predicate === 'Cukup Baik')
                                        <span class="badge-predicate badge-predicate-cukup" style="background: #fef9c3; color: #a16207; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;">Cukup Baik</span>
                                    @else
                                        <span class="badge-predicate badge-predicate-kurang" style="background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;">Perlu Perhatian</span>
                                    @endif
                                </td>
                                <td style="padding: 13px 18px; color: #475569; font-size: 12px;">
                                    {{ $eval->evaluator->name ?? '-' }}
                                </td>
                                <td style="padding: 13px 18px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="{{ route('admin.reports.print-single', $eval->id) }}" target="_blank" title="Cetak form resmi" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 5px 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        </a>

                                        <button type="button" onclick="showEvaluationDetail({{ $eval->id }})" title="Lihat rincian 17 aspek" style="background: #eff6ff; color: #0284c7; border: 1px solid #bae6fd; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; cursor: pointer;">
                                            Rincian
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@else
    <!-- Tampilan Rekapitulasi Tahunan (Annual) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 12.5px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Total Personel Dinilai</div>
            <div style="font-size: 26px; font-weight: 800; color: #0f172a;">{{ $annualData['evaluated_employees'] }} / {{ $annualData['total_employees'] }}</div>
            <div style="font-size: 11.5px; color: #0284c7; margin-top: 2px;">TAD aktif tahun {{ $year }}</div>
        </div>

        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 12.5px; font-weight: 600; color: #64748b; margin-bottom: 4px;">Rata-Rata Tahunan Cabang</div>
            <div style="font-size: 26px; font-weight: 800; color: #15803d;">{{ number_format($annualData['grand_average'], 2) }}</div>
            <div style="font-size: 11.5px; color: #16a34a; margin-top: 2px; font-weight: 600;">Predikat: {{ $annualData['grand_predicate'] }}</div>
        </div>

        <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 8px;">Distribusi Predikat Tahunan</div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
                <span style="color: #15803d; font-weight: 600;">Sangat Baik:</span>
                <span style="font-weight: 700;">{{ $annualData['predicate_summary']['Sangat Baik'] }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px;">
                <span style="color: #0369a1; font-weight: 600;">Baik:</span>
                <span style="font-weight: 700;">{{ $annualData['predicate_summary']['Baik'] }}</span>
            </div>
        </div>
    </div>

    <!-- Data Table Tahunan -->
    <div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
            <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
                Rekapitulasi Akumulasi 12 Bulan (Tahun {{ $year }})
            </h3>
            <span style="font-size: 12px; color: #64748b;">Menampilkan seluruh personel TAD terdaftar</span>
        </div>

        <div class="table-responsive">
            <table class="table-pelindo data-table" style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 12px 14px; text-align: center; width: 40px;">No</th>
                        <th style="padding: 12px 14px; text-align: start;">NIK</th>
                        <th style="padding: 12px 14px; text-align: start; min-width: 160px;">Nama Karyawan TAD</th>
                        <th style="padding: 12px 14px; text-align: start;">Divisi</th>
                        @for($m = 1; $m <= 12; $m++)
                            <th style="padding: 10px 6px; text-align: center; min-width: 48px;">{{ substr($monthNames[$m], 0, 3) }}</th>
                        @endfor
                        <th style="padding: 12px 14px; text-align: end;">Bulan</th>
                        <th style="padding: 12px 14px; text-align: end;">Rata-Rata</th>
                        <th style="padding: 12px 14px; text-align: center;">Predikat Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($annualData['rows'] as $idx => $row)
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 11px 14px; text-align: center; font-weight: 600; color: #64748b;">
                                {{ $idx + 1 }}
                            </td>
                            <td style="padding: 11px 14px; font-family: monospace; font-weight: 700; color: #0369a1;">
                                {{ $row['employee']->nik }}
                            </td>
                            <td style="padding: 11px 14px; font-weight: 700; color: #0f172a;">
                                {{ $row['employee']->name }}
                            </td>
                            <td style="padding: 11px 14px;">
                                <span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                                    {{ $row['employee']->division->name ?? '-' }}
                                </span>
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                <td style="padding: 10px 6px; text-align: center; font-size: 11px;">
                                    @if(isset($row['monthly_scores'][$m]))
                                        <span style="font-weight: 700; color: #0f172a;">{{ number_format($row['monthly_scores'][$m], 1) }}</span>
                                    @else
                                        <span style="color: #cbd5e1;">-</span>
                                    @endif
                                </td>
                            @endfor
                            <td style="padding: 11px 14px; text-align: end; font-weight: 700; color: #0284c7;">
                                {{ $row['evaluated_count'] }}/12
                            </td>
                            <td style="padding: 11px 14px; text-align: end; font-size: 13.5px; font-weight: 800; color: #0f172a;">
                                {{ $row['annual_average'] > 0 ? number_format($row['annual_average'], 2) : '-' }}
                            </td>
                            <td style="padding: 11px 14px; text-align: center;">
                                @if($row['predicate'] === 'Sangat Baik')
                                    <span class="badge-predicate badge-predicate-sangat-baik" style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700;">Sangat Baik</span>
                                @elseif($row['predicate'] === 'Baik')
                                    <span class="badge-predicate badge-predicate-baik" style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700;">Baik</span>
                                @elseif($row['predicate'] === 'Cukup Baik')
                                    <span class="badge-predicate badge-predicate-cukup" style="background: #fef9c3; color: #a16207; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700;">Cukup Baik</span>
                                @elseif($row['predicate'] === 'Perlu Perhatian')
                                    <span class="badge-predicate badge-predicate-kurang" style="background: #fee2e2; color: #b91c1c; padding: 3px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 700;">Perlu Perhatian</span>
                                @else
                                    <span style="color: #94a3b8; font-style: italic;">Belum Ada</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<!-- Modal Detail Evaluasi (17 Aspek AKHLAK) -->
<div id="evaluationDetailModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 720px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 16px;">
            <div>
                <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0;" id="modalEmployeeName">Rincian Penilaian Karyawan</h3>
                <p style="font-size: 12.5px; color: #64748b; margin: 2px 0 0 0;" id="modalEmployeeMeta">Detail 17 butir indikator perilaku AKHLAK</p>
            </div>
            <button type="button" onclick="closeModalById('evaluationDetailModal')" aria-label="Tutup rincian penilaian" style="background: none; border: none; font-size: 22px; color: #64748b; cursor: pointer;"><span aria-hidden="true">&times;</span></button>
        </div>

        <div id="modalEvaluationContent" style="font-size: 13px;">
            <div style="text-align: center; padding: 30px; color: #64748b;">Memuat data...</div>
        </div>

        <div class="modal-actions-sticky" style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
            <a id="modalPdfBtn" href="#" target="_blank" style="background: #dc2626; color: #ffffff; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Simpan PDF
            </a>
            <a id="modalPrintBtn" href="#" target="_blank" style="background: #005b9f; color: #ffffff; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak (Print)
            </a>
            <button type="button" onclick="closeModalById('evaluationDetailModal')" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Tutup</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function closeModalById(id) {
        document.getElementById(id).classList.remove('active');
    }

    function triggerExportFeedback(el, loadingLabel) {
        if (!el || el.classList.contains('is-exporting')) return;
        var originalHtml = el.innerHTML;
        el.classList.add('is-exporting');
        el.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display:inline-block; width:12px; height:12px; border:2px solid currentColor; border-right-color:transparent; border-radius:50%; animation:spin .75s linear infinite;"></span> ' + (loadingLabel || 'Menyiapkan...');
        el.style.pointerEvents = 'none';
        setTimeout(function() {
            el.classList.remove('is-exporting');
            el.innerHTML = originalHtml;
            el.style.pointerEvents = '';
        }, 3000);
    }

    function showEvaluationDetail(evalId) {
        var modal = document.getElementById('evaluationDetailModal');
        var content = document.getElementById('modalEvaluationContent');
        var pdfBtn = document.getElementById('modalPdfBtn');
        var printBtn = document.getElementById('modalPrintBtn');
        if (pdfBtn) pdfBtn.href = "{{ url('/admin/reports') }}/" + evalId + "/print?action=save_pdf";
        if (printBtn) printBtn.href = "{{ url('/admin/reports') }}/" + evalId + "/print?action=print";

        modal.classList.add('active');
        content.innerHTML = '<div style="text-align: center; padding: 30px; color: #64748b;">Memuat rincian penilaian...</div>';

        fetch("{{ url('/admin/reports') }}/" + evalId, {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            if (!data || !data.evaluation) throw new Error('Data tidak valid');
            var ev = data.evaluation;
            var emp = ev.employee || {};
            document.getElementById('modalEmployeeName').textContent = (emp.name || 'Eks-Karyawan') + ' (' + (emp.nik || '-') + ')';
            document.getElementById('modalEmployeeMeta').textContent = (emp.position || '-') + ' • Divisi ' + (emp.division ? emp.division.name : '-') + ' • Periode: Bulan ' + ev.period_month + '/' + ev.period_year;

            var html = '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">'
                     + '<div><span style="font-size: 12px; color: #64748b;">Total Skor: </span><strong style="font-size: 18px; color: #0f172a;">' + parseFloat(ev.total_score).toFixed(2) + '</strong> • <span style="font-size: 12px; color: #64748b;">Predikat: </span><strong style="color: #0284c7;">' + escapeHtml(ev.predicate) + '</strong></div>'
                     + '<div style="font-size: 12px; color: #64748b;">Penilai: <strong>' + escapeHtml(ev.evaluator ? ev.evaluator.name : '-') + '</strong></div>'
                     + '</div>';

            html += '<div class="table-responsive"><table style="width: 100%; border-collapse: collapse; font-size: 12px;">'
                  + '<thead><tr style="background: #f1f5f9; color: #475569; text-transform: uppercase; font-size: 10.5px;">'
                  + '<th style="padding: 8px 12px; text-align: start;">No / Indikator</th>'
                  + '<th style="padding: 8px 12px; text-align: center; width: 80px;">Rating</th>'
                  + '<th style="padding: 8px 12px; text-align: end; width: 80px;">Skor</th>'
                  + '<th style="padding: 8px 12px; text-align: start; width: 40%;">Alasan Penilai</th>'
                  + '</tr></thead><tbody>';

            var itemCounter = 1;
            for (var catId in data.groupedDetails) {
                var group = data.groupedDetails[catId];
                var catName = group.category ? group.category.name : 'Kategori';
                html += '<tr style="background: #f8fafc; border-top: 2px solid #cbd5e1;"><td colspan="4" style="padding: 8px 12px; font-weight: 700; color: #00223B;">' + escapeHtml(catName) + '</td></tr>';

                group.items.forEach(function(item) {
                    var subName = item.subcategory ? item.subcategory.name : 'Indikator';
                    html += '<tr style="border-bottom: 1px solid #f1f5f9;">'
                          + '<td style="padding: 8px 12px; color: #334155;"><strong>#' + itemCounter + '.</strong> ' + escapeHtml(subName) + '</td>'
                          + '<td style="padding: 8px 12px; text-align: center;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 7px; border-radius: 4px; font-weight: 700;">R' + escapeHtml(item.rating) + '</span></td>'
                          + '<td style="padding: 8px 12px; text-align: end; font-weight: 700;">' + parseFloat(item.score).toFixed(2) + '</td>'
                          + '<td style="padding: 8px 12px; color: #64748b; font-size: 11.5px;">' + (item.reason ? escapeHtml(item.reason) : '-') + '</td>'
                          + '</tr>';
                    itemCounter++;
                });
            }

            html += '</tbody></table></div>';
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = '<div style="color: #dc2626; padding: 20px; text-align: center;">Gagal memuat rincian evaluasi.</div>';
        });
    }

    window.addEventListener('click', function(e) {
        var modal = document.getElementById('evaluationDetailModal');
        if (e.target === modal) modal.classList.remove('active');
    });
</script>
@endpush
@endsection

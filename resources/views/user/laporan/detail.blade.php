@extends('layouts.app', ['title' => 'Detail Penilaian - ' . $evaluation->employee->name])

@section('content')
<div class="page-title-box" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">Detail Penilaian</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">{{ $evaluation->employee->name }} &mdash; {{ $evaluation->period_label }}</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="{{ route('laporan.index', ['month' => $evaluation->period_month, 'year' => $evaluation->period_year]) }}" 
           style="background: #f1f5f9; color: #475569; text-decoration: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; border: 1px solid #cbd5e1;">
            &larr; Kembali
        </a>
        <a href="{{ route('laporan.print', ['id' => $evaluation->id, 'action' => 'save_pdf']) }}" target="_blank"
           style="background: #dc2626; color: #ffffff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(220, 38, 38, 0.2);"
           title="Simpan lembar hasil penilaian sebagai PDF">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>Simpan PDF</span>
        </a>
        <a href="{{ route('laporan.print', ['id' => $evaluation->id, 'action' => 'print']) }}" target="_blank"
           style="background: #005b9f; color: #ffffff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0, 91, 159, 0.2);"
           title="Buka dialog cetak dokumen resmi langsung">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span>Cetak (Print)</span>
        </a>
    </div>
</div>

<!-- Header Ringkasan Karyawan Sesuai Gambar 5.2 SRS -->
<div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
            <div class="kpi-label" title="Karyawan" tabindex="0" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px;">Karyawan</div>
            <div style="font-size: 16px; font-weight: 700; color: #0f172a;">{{ $evaluation->employee->name }}</div>
            <div style="font-size: 12px; color: #94a3b8;">NIK: {{ $evaluation->employee->nik ?? '-' }}</div>
        </div>
        <div>
            <div class="kpi-label" title="Jabatan" tabindex="0" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px;">Jabatan</div>
            <div style="font-size: 15px; font-weight: 600; color: #334155;">{{ $evaluation->employee->position ?? '-' }}</div>
            <div style="font-size: 12px; color: #94a3b8;">Divisi: {{ $evaluation->division->name ?? '-' }}</div>
        </div>
        <div>
            <div class="kpi-label" title="Total Skor" tabindex="0" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px;">Total Skor</div>
            <div style="display: inline-block;">
                <span class="badge {{ $evaluation->badge_class }}" style="padding: 6px 14px; border-radius: 8px; font-size: 14px; font-weight: 700;">
                    {{ number_format($evaluation->total_score, 2) }} &mdash; {{ $evaluation->predicate }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown Rincian Aspek Sesuai Gambar 5.2 SRS -->
@php $catNumber = 1; @endphp
@foreach($groupedDetails as $catId => $group)
    <div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <!-- Header Aspek Sesuai Gambar 5.2 Kotak 2 -->
        <div style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 20px; display: flex; align-items: center; gap: 10px;">
            <div style="width: 22px; height: 22px; border-radius: 50%; background: #002B49; color: #ffffff; font-size: 11px; font-weight: 800; display: flex; align-items: center; justify-content: center;">
                {{ $catNumber++ }}
            </div>
            <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 0;">{{ $group['category']->name }}</h3>
        </div>

        <div style="padding: 16px 20px;">
            @foreach($group['items'] as $item)
                @php
                    $opt = App\Services\EvaluationScoringService::getRatingOptions()[$item->rating] ?? null;
                @endphp
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 12px; border-inline-start: 4px solid {{ $opt['color'] ?? '#0072CE' }};">
                    <!-- Pernyataan Butir (Kotak 3) -->
                    <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 8px;">
                        {{ $item->subcategory->name }}
                    </div>

                    <!-- Skor & Rating -->
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 12px;">
                        <span style="font-weight: 700; color: {{ $opt['color'] ?? '#0072CE' }}; background: #f8fafc; padding: 3px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                            Rating {{ $item->rating }} &mdash; {{ $opt['label'] ?? '-' }} ({{ number_format($item->score, 2) }})
                        </span>
                    </div>

                    <!-- Catatan / Alasan (Kotak 4) -->
                    <div style="font-size: 13px; color: #475569; background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                        <span style="font-weight: 600; color: #334155;">Alasan:</span> 
                        {{ $item->reason ?: 'Tidak ada catatan tambahan.' }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
@endsection

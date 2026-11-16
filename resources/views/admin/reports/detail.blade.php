@extends('layouts.admin')

@section('title', 'Rincian Evaluasi Kinerja TAD')

@section('content')
<div class="container-fluid" style="padding: 0;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div>
            <a href="{{ route('admin.reports.index', ['month' => $evaluation->period_month, 'year' => $evaluation->period_year]) }}"
               style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #005b9f; text-decoration: none; margin-bottom: 8px;">
                &larr; Kembali ke Laporan Eksekutif
            </a>
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #00223B;">
                Rincian Evaluasi: {{ $evaluation->employee?->name ?? 'Eks-Karyawan' }}
            </h1>
            <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                NIK {{ $evaluation->employee?->nik ?? '-' }} &bull;
                {{ $evaluation->employee?->position ?? '-' }} &bull;
                Divisi {{ $evaluation->employee?->division?->name ?? '-' }} &bull;
                Periode {{ $evaluation->period_label }}
            </p>
        </div>

        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="{{ route('admin.reports.print-single', ['id' => $evaluation->id, 'action' => 'save_pdf']) }}"
               target="_blank" rel="noopener noreferrer"
               class="btn btn-primary"
               style="background: #00223B; color: #ffffff; border: none; padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;">
                Simpan PDF
            </a>
            <a href="{{ route('admin.reports.print-single', ['id' => $evaluation->id, 'action' => 'print']) }}"
               target="_blank" rel="noopener noreferrer"
               class="btn"
               style="background: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; padding: 9px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;">
                Cetak (Print)
            </a>
        </div>
    </div>

    <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700;">Total Skor AKHLAK</div>
            <div style="font-size: 28px; font-weight: 800; color: #00223B;">
                {{ number_format((float) $evaluation->total_score, 2) }}
                <span style="font-size: 14px; font-weight: 600; color: #005b9f; margin-left: 8px;">{{ $evaluation->predicate }}</span>
            </div>
        </div>
        <div style="text-align: end;">
            <div style="font-size: 12px; color: #64748b;">Pejabat Penilai</div>
            <div style="font-size: 14px; font-weight: 700; color: #0f172a;">{{ $evaluation->evaluator?->name ?? '-' }}</div>
            <div style="font-size: 12px; color: #64748b;">
                Disubmit {{ $evaluation->submitted_at ? $evaluation->submitted_at->format('d M Y H:i') : '-' }} WIB
            </div>
        </div>
    </div>

    <div class="card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; text-transform: uppercase; font-size: 11px;">
                        <th style="padding: 12px 16px; text-align: start; width: 60px;">No</th>
                        <th style="padding: 12px 16px; text-align: start;">Indikator Penilaian</th>
                        <th style="padding: 12px 16px; text-align: center; width: 90px;">Rating</th>
                        <th style="padding: 12px 16px; text-align: end; width: 90px;">Skor</th>
                        <th style="padding: 12px 16px; text-align: start;">Alasan / Catatan Penilai</th>
                    </tr>
                </thead>
                <tbody>
                    @php $itemNo = 1; @endphp
                    @forelse($groupedDetails as $group)
                        <tr style="background: #f1f5f9;">
                            <td colspan="5" style="padding: 10px 16px; font-weight: 800; color: #00223B;">
                                {{ $group['category']->name }}
                            </td>
                        </tr>
                        @foreach($group['items'] as $detail)
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 10px 16px; color: #64748b; font-weight: 700;">#{{ $itemNo++ }}</td>
                                <td style="padding: 10px 16px; color: #0f172a;">{{ $detail->subcategory?->name ?? '-' }}</td>
                                <td style="padding: 10px 16px; text-align: center;">
                                    <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 5px; font-weight: 700;">
                                        R{{ $detail->rating }}
                                    </span>
                                </td>
                                <td style="padding: 10px 16px; text-align: end; font-weight: 700;">
                                    {{ number_format((float) $detail->score, 2) }}
                                </td>
                                <td style="padding: 10px 16px; color: #475569;">
                                    {{ $detail->reason ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 24px; text-align: center; color: #64748b;">
                                Belum ada rincian indikator untuk evaluasi ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

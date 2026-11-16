<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Penilaian - {{ $evaluation->employee->name }} - {{ $evaluation->period_label }}</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            background: #ffffff;
            margin: 0;
            padding: 24px;
            font-size: 12px;
            line-height: 1.4;
        }
        .header-kop {
            display: flex;
            align-items: center;
            border-bottom: 3px double #002B49;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .kop-logo {
            width: 140px;
            margin-right: 20px;
        }
        .kop-text {
            flex: 1;
            text-align: center;
        }
        .kop-text h2 {
            margin: 0;
            font-size: 16px;
            color: #002B49;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kop-text h3 {
            margin: 2px 0;
            font-size: 13px;
            color: #0072CE;
            font-weight: 600;
        }
        .kop-text p {
            margin: 2px 0;
            font-size: 11px;
            color: #4b5563;
        }
        .doc-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .doc-title h1 {
            font-size: 15px;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0 0 4px 0;
        }
        .doc-title p {
            margin: 0;
            font-size: 12px;
            color: #4b5563;
        }
        .info-table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .eval-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .eval-table th, .eval-table td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            font-size: 11px;
        }
        .eval-table th {
            background: #f3f4f6;
            text-align: center;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 36px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 240px;
            text-align: center;
        }
        .signature-space {
            height: 70px;
        }
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0072CE;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print print-toolbar" style="position: fixed; top: 16px; right: 16px; display: flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); padding: 8px 12px; border-radius: 10px; border: 1px solid rgba(203, 213, 225, 0.8); box-shadow: 0 4px 16px rgba(0, 34, 59, 0.15); z-index: 9999;">
        @if(($action ?? '') === 'save_pdf')
            <span style="font-size: 11px; color: #475569; margin-right: 4px; display: inline-flex; align-items: center; gap: 4px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                Pilih tujuan "Save as PDF" pada dialog cetak
            </span>
        @endif
        <button type="button" onclick="window.print()" style="background: #005b9f; color: #ffffff; border: none; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Cetak (Print)
        </button>
        <button type="button" onclick="window.print()" style="background: #dc2626; color: #ffffff; border: none; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Simpan PDF
        </button>
        <button type="button" onclick="window.close()" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
            Tutup
        </button>
    </div>

    <!-- Kop Surat Pelindo -->
    <div class="header-kop">
        <img class="kop-logo" src="{{ asset('assets/img/logo ptp.png') }}" alt="Pelindo Multi Terminal">
        <div class="kop-text">
            <h2>{{ strtoupper($branchSettings['company_name'] ?? 'PT Pelabuhan Indonesia (Persero)') }}</h2>
            <h3>{{ strtoupper($branchSettings['subholding_name'] ?? 'PT Pelindo Multi Terminal') }} &mdash; {{ strtoupper($branchSettings['branch_name'] ?? 'Branch Cirebon') }}</h3>
            <p>{{ $branchSettings['branch_address'] ?? 'Jl. Pelabuhan II No. 1, Pasirjambak, Kec. Lemahwungkuk, Kota Cirebon, Jawa Barat 45111' }}</p>
            <p>Departemen SDM &amp; Umum &mdash; Sistem Penilaian Kinerja Tenaga Alih Daya (SPKTAD)</p>
        </div>
    </div>

    <!-- Judul Dokumen -->
    <div class="doc-title">
        <h1>Lembar Hasil Penilaian Kinerja Tenaga Alih Daya (TAD)</h1>
        <p>Periode: <strong>{{ $evaluation->period_label }}</strong></p>
    </div>

    <!-- Data Karyawan -->
    <table class="info-table">
        <tr>
            <td style="width: 15%; font-weight: bold;">Nama Karyawan</td>
            <td style="width: 35%;">: {{ $evaluation->employee->name }}</td>
            <td style="width: 15%; font-weight: bold;">Total Skor</td>
            <td style="width: 35%;">: <strong>{{ number_format($evaluation->total_score, 2) }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">NIK / ID</td>
            <td>: {{ $evaluation->employee->nik ?? '-' }}</td>
            <td style="font-weight: bold;">Predikat Kinerja</td>
            <td>: <strong>{{ $evaluation->predicate }}</strong></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Jabatan / Posisi</td>
            <td>: {{ $evaluation->employee->position ?? '-' }}</td>
            <td style="font-weight: bold;">Nama Penilai</td>
            <td>: {{ $evaluation->evaluator->name ?? '-' }}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Divisi</td>
            <td>: {{ $evaluation->division->name ?? '-' }}</td>
            <td style="font-weight: bold;">Tanggal Dinilai</td>
            <td>: {{ $evaluation->submitted_at ? $evaluation->submitted_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') . ' WIB' : '-' }}</td>
        </tr>
    </table>

    <!-- Rincian 17 Butir Evaluasi -->
    <table class="eval-table">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 110px;">Aspek Utama</th>
                <th>Pernyataan Kinerja</th>
                <th style="width: 50px;">Rating</th>
                <th style="width: 50px;">Skor</th>
                <th style="width: 200px;">Alasan / Catatan Evaluasi</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($groupedDetails as $group)
                @foreach($group['items'] as $item)
                    <tr>
                        <td style="text-align: center;">{{ $no++ }}</td>
                        <td style="font-weight: bold; background: #fafafa;">{{ $group['category']->name }}</td>
                        <td>{{ $item->subcategory->name }}</td>
                        <td style="text-align: center; font-weight: bold;">{{ $item->rating }}</td>
                        <td style="text-align: center;">{{ number_format($item->score, 2) }}</td>
                        <td style="font-size: 10px; color: #374151;">{{ $item->reason ?: '-' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f9fafb; font-weight: bold;">
                <td colspan="4" style="text-align: right; padding-right: 12px;">TOTAL SKOR AKHIR:</td>
                <td style="text-align: center; font-size: 13px;">{{ number_format($evaluation->total_score, 2) }}</td>
                <td>PREDIKAT: {{ $evaluation->predicate }}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Tanda Tangan -->
    <div class="signature-section">
        <div class="signature-box">
            <div>Mengetahui,</div>
            <div style="font-weight: bold;">{{ $branchSettings['sdm_manager_title'] ?? 'Manager SDM & Umum' }}</div>
            <div class="signature-space"></div>
            <div style="font-weight: bold; border-bottom: 1px solid #111827; padding-bottom: 2px;">{{ $branchSettings['sdm_manager_name'] ?? '( .................................................... )' }}</div>
            <div style="font-size: 10px; color: #6b7280; margin-top: 2px;">
                @if(!empty($branchSettings['sdm_manager_nipp']))
                    NIPP. {{ $branchSettings['sdm_manager_nipp'] }}
                @else
                    {{ $branchSettings['branch_name'] ?? 'PTP Nonpetikemas Branch Cirebon' }}
                @endif
            </div>
        </div>

        <div class="signature-box">
            <div>{{ $branchSettings['city'] ?? 'Cirebon' }}, {{ date('d F Y') }}</div>
            <div style="font-weight: bold;">Penilai (Kepala Divisi)</div>
            <div class="signature-space"></div>
            <div style="font-weight: bold; border-bottom: 1px solid #111827; padding-bottom: 2px;">{{ $evaluation->evaluator->name ?? 'Rudy Kusuma' }}</div>
            <div style="font-size: 10px; color: #6b7280; margin-top: 2px;">{{ $evaluation->evaluator->position ?? 'Kepala Divisi Operasi' }}</div>
        </div>
    </div>

    @if(!empty($branchSettings['document_footer_note']))
        <div style="margin-top: 20px; font-size: 8.5px; color: #6b7280; font-style: italic; border-top: 1px dashed #e5e7eb; padding-top: 6px; text-align: center;">
            {{ $branchSettings['document_footer_note'] }}
        </div>
    @endif

    @if(($action ?? '') === 'print')
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                window.print();
            });
        </script>
    @endif
</body>
</html>

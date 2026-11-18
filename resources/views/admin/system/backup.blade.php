@extends('layouts.admin', ['title' => 'Cadangan & Pemulihan Basis Data'])

@section('content')
<div class="page-title-box" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">Cadangan &amp; Pemulihan Basis Data</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Pusat kendali snapshot data, integritas arsip, dan prosedur pemulihan bencana (Disaster Recovery)</p>
    </div>

    <!-- Tombol Buat Cadangan Instan -->
    <form action="{{ route('admin.backup.store') }}" method="POST" class="js-confirm-backup-form" data-backup-action="create">
        @csrf
        <button type="submit" style="background: #15803d; color: #ffffff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(21, 128, 61, 0.25);">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <span>Buat Cadangan Baru</span>
        </button>
    </form>
</div>

<!-- Kartu Metrik Backup & Penyimpanan -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div class="kpi-label" title="Total Arsip Cadangan" tabindex="0" style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Total Arsip Cadangan</div>
        <div class="tabular-nums" style="font-size: 28px; font-weight: 800; color: #00223B; margin-top: 4px;">{{ $totalCount }} Berkas</div>
        <div style="font-size: 11.5px; color: #005FA8; margin-top: 2px;">Maksimal 10 snapshot terbaru</div>
    </div>

    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div class="kpi-label" title="Ukuran Total Cadangan" tabindex="0" style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Ukuran Total Cadangan</div>
        <div class="tabular-nums" style="font-size: 28px; font-weight: 800; color: #0369a1; margin-top: 4px;">{{ $totalSizeFormatted }}</div>
        <div style="font-size: 11.5px; color: #0369a1; margin-top: 2px;">Penyimpanan terkompresi</div>
    </div>

    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div class="kpi-label" title="Ruang Disk Bebas" tabindex="0" style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Ruang Disk Bebas</div>
        <div class="tabular-nums" style="font-size: 28px; font-weight: 800; color: #15803d; margin-top: 4px;">{{ $freeDiskFormatted }}</div>
        <div style="font-size: 11.5px; color: #15803d; margin-top: 2px;">Kapasitas aman server lokal</div>
    </div>

    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div class="kpi-label" title="Cadangan Terakhir" tabindex="0" style="font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase;">Cadangan Terakhir</div>
        <div class="tabular-nums" style="font-size: 17px; font-weight: 800; color: #0f172a; margin-top: 8px;">
            {{ $latestBackup ? $latestBackup['created_at'] : 'Belum Ada' }}
        </div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">WIB (Waktu Indonesia Barat)</div>
    </div>
</div>

<!-- Pusat Kontrol One-Tap Official Release (Go-Live Readiness Engine) -->
@php
    $healthScore = (float) ($audit['health_score'] ?? 100);
    $summaryStatus = (string) ($audit['summary_status'] ?? 'EXCELLENT');
    $isReady = (bool) ($audit['is_production_ready'] ?? true);
    $passedRules = (int) ($audit['passed_rules'] ?? 0);
    $totalRules = (int) ($audit['total_rules'] ?? 0);
    $statusBadgeBg = $isReady ? '#dcfce7' : '#fef2f2';
    $statusBadgeColor = $isReady ? '#166534' : '#991b1b';
    $statusBadgeBorder = $isReady ? '#bbf7d0' : '#fecaca';
@endphp
<section id="oneTapReleaseSection" aria-labelledby="oneTapReleaseTitle" class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1; border-inline-start: 5px solid #00223B; box-shadow: 0 2px 8px rgba(0, 34, 59, 0.06); margin-bottom: 28px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #00223B 0%, #003865 100%); color: #ffffff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap;">
                <span style="background: rgba(21, 128, 61, 0.25); border: 1px solid rgba(134, 239, 172, 0.45); color: #bbf7d0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; padding: 2px 10px; border-radius: 999px;">
                    Release Automation Engine
                </span>
                <span style="background: {{ $statusBadgeBg }}; color: {{ $statusBadgeColor }}; border: 1px solid {{ $statusBadgeBorder }}; font-size: 11px; font-weight: 800; padding: 2px 10px; border-radius: 999px;">
                    {{ $summaryStatus }} ({{ $healthScore }}%)
                </span>
            </div>
            <h2 id="oneTapReleaseTitle" style="font-size: 18px; font-weight: 700; color: #ffffff; margin: 0 0 4px 0;">
                One-Tap Official Release &amp; Transisi Data Go-Live
            </h2>
            <p style="font-size: 12.5px; color: rgba(255, 255, 255, 0.82); margin: 0; line-height: 1.45;">
                Otomatisasi snapshot backup, normalisasi Master Data real/revisi, pembersihan data uji coba atau rekonsiliasi skor, serta sertifikasi kualitas data 5 dimensi dalam satu klik.
            </p>
        </div>

        <button type="button"
                id="openOfficialReleaseBtn"
                onclick="openOfficialReleaseModal()"
                style="background: #15803d; color: #ffffff; border: 1px solid rgba(255,255,255,0.22); padding: 11px 20px; border-radius: 8px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(21, 128, 61, 0.35); white-space: nowrap;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
            <span>One-Tap Official Release (Siapkan Go-Live)</span>
        </button>
    </div>

    <div style="padding: 20px 24px; background: #f8fafc;">
        <div style="font-size: 12px; font-weight: 700; color: #00223B; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 12px;">
            Status Kesiapan Go-Live (Data Quality Gate &mdash; {{ $passedRules }}/{{ $totalRules }} Aturan Lolos)
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <span class="kpi-label" title="Health Score Data" tabindex="0" style="display: block; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Health Score Data</span>
                <div class="tabular-nums" style="font-size: 20px; font-weight: 800; color: {{ $isReady ? '#15803d' : '#b91c1c' }}; margin-top: 4px;">
                    {{ $healthScore }}% <span style="font-size: 12px; font-weight: 700;">({{ $summaryStatus }})</span>
                </div>
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <span class="kpi-label" title="Master TAD Aktif" tabindex="0" style="display: block; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Master TAD Aktif</span>
                <div class="tabular-nums" style="font-size: 20px; font-weight: 800; color: #00223B; margin-top: 4px;">
                    {{ $activeEmployeeCount ?? 35 }} Personel
                </div>
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <span class="kpi-label" title="Transaksi Evaluasi Saat Ini" tabindex="0" style="display: block; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Transaksi Evaluasi Saat Ini</span>
                <div class="tabular-nums" style="font-size: 20px; font-weight: 800; color: #0369a1; margin-top: 4px;">
                    {{ $evaluationCount ?? 0 }} Data
                </div>
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                <span class="kpi-label" title="Pengajuan Edit" tabindex="0" style="display: block; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase;">Pengajuan Edit</span>
                <div class="tabular-nums" style="font-size: 20px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                    {{ $editRequestCount ?? 0 }} Permohonan
                </div>
            </div>
        </div>

        @if(!empty($audit['issues']))
            <div style="margin-top: 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px;">
                <div style="font-size: 12px; font-weight: 700; color: #92400e; margin-bottom: 4px;">Catatan Kualitas Data Sebelum Rilis:</div>
                <ul style="margin: 0; padding-inline-start: 18px; font-size: 12px; color: #78350f; line-height: 1.5;">
                    @foreach($audit['issues'] as $issue)
                        <li><strong>[{{ $issue['severity'] }}]</strong> {{ $issue['rule'] }}: {{ $issue['description'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>

<!-- Tabel Daftar Cadangan Basis Data -->
<div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 28px;">
    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; background: #fafafa;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">
            Daftar Berkas Cadangan Tersedia
        </h3>
        <span style="font-size: 12px; color: #475569;">Lokasi Direktori: <code>storage/app/backups/</code></span>
    </div>

    <span id="shaCopyStatus" class="sr-only" role="status" aria-live="polite"></span>

    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <th style="padding: 10px 12px; text-align: center; width: 40px;">No</th>
                    <th style="padding: 10px 12px; text-align: start;">Nama Berkas Snapshot</th>
                    <th style="padding: 10px 12px; text-align: center; width: 135px;">Waktu Pembuatan</th>
                    <th style="padding: 10px 12px; text-align: end; width: 85px;">Ukuran</th>
                    <th style="padding: 10px 12px; text-align: start; width: 220px;">Integritas (SHA-256)</th>
                    <th style="padding: 10px 12px; text-align: center; width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($backups as $index => $bk)
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td class="tabular-nums" style="padding: 10px 12px; text-align: center; font-weight: 600; color: #64748b;">
                            {{ $index + 1 }}
                        </td>
                        <td style="padding: 10px 12px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <svg aria-hidden="true" style="flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#005FA8" stroke-width="2"><path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v4"/><polyline points="14 2 14 8 20 8"/><path d="M2 15h10"/><path d="m9 18 3-3-3-3"/></svg>
                                <span style="font-family: monospace; font-size: 12.5px; font-weight: 700; color: #00223B; overflow-wrap: anywhere;">
                                    {{ $bk['filename'] }}
                                </span>
                            </div>
                        </td>
                        <td class="tabular-nums" style="padding: 10px 12px; text-align: center; font-size: 12px; color: #334155;">
                            {{ $bk['created_at'] }} WIB
                        </td>
                        <td class="tabular-nums" style="padding: 10px 12px; text-align: end; font-weight: 600; color: #0369a1; font-size: 12.5px; white-space: nowrap;">
                            {{ $bk['size_formatted'] }}
                        </td>
                        <td style="padding: 10px 12px;">
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <code class="tabular-nums" style="font-family: monospace; font-size: 11px; background: #f8fafc; border: 1px solid #cbd5e1; padding: 3px 6px; border-radius: 4px; color: #334155;">
                                    {{ substr($bk['sha256'], 0, 10) }}&hellip;{{ substr($bk['sha256'], -6) }}
                                </code>
                                <button type="button"
                                        class="table-action-btn js-copy-sha"
                                        data-sha="{{ $bk['sha256'] }}"
                                        aria-label="Salin kode SHA-256 lengkap untuk {{ $bk['filename'] }}"
                                        style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                                    Salin
                                </button>
                            </div>
                            <details style="margin-top: 4px;">
                                <summary style="font-size: 11px; color: #005FA8; font-weight: 600; cursor: pointer;">Lihat 64 digit SHA-256</summary>
                                <code style="display: block; margin-top: 4px; font-family: monospace; font-size: 10.5px; color: #1e293b; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 8px; word-break: break-all; line-height: 1.4;">{{ $bk['sha256'] }}</code>
                            </details>
                        </td>
                        <td style="padding: 10px 12px; text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <a href="{{ route('admin.backup.download', $bk['filename']) }}"
                                   class="table-action-btn"
                                   title="Unduh file cadangan ke komputer"
                                   style="background: #eff6ff; color: #0369a1; border: 1px solid #bae6fd; padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    <span>Unduh</span>
                                </a>

                                <form action="{{ route('admin.backup.destroy', $bk['filename']) }}" method="POST" class="js-confirm-backup-form" data-backup-action="delete" data-filename="{{ $bk['filename'] }}" style="margin: 0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="table-action-btn"
                                            aria-label="Hapus berkas cadangan {{ $bk['filename'] }}"
                                            title="Hapus berkas cadangan {{ $bk['filename'] }}"
                                            style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 5px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 36px; text-align: center; color: #64748b;">
                            Belum ada berkas cadangan basis data yang dibuat. Klik tombol <strong>"Buat Cadangan Baru"</strong> di atas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Panduan Pemulihan Bencana (Disaster Recovery Guidelines) -->
<div class="card" style="background: #f8fafc; border-radius: 12px; border: 1px solid #cbd5e1; padding: 20px 24px;">
    <h4 style="font-size: 14px; font-weight: 700; color: #00223B; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px;">
        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#005FA8" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Prosedur Pemulihan Bencana (Disaster Recovery Protocol) - Tim IT Pelindo</span>
    </h4>
    <ol style="font-size: 12.5px; color: #334155; line-height: 1.6; margin: 0; padding-inline-start: 20px;">
        <li><strong>Unduh Arsip:</strong> Unduh snapshot terbaru dari daftar di atas ke media penyimpanan aman terpisah (flashdisk dinas / server backup pelabuhan).</li>
        <li><strong>Hentikan Sesi Aktif:</strong> Hentikan server aplikasi sementara untuk mencegah penulisan data baru saat proses pemulihan.</li>
        <li><strong>Timpa Berkas Database:</strong> Ganti file <code>database/database.sqlite</code> dengan berkas snapshot cadangan yang telah diunduh.</li>
        <li><strong>Bersihkan Cache Framework:</strong> Jalankan perintah <code>php artisan optimize:clear</code> di terminal server.</li>
        <li><strong>Verifikasi Integritas:</strong> Jalankan <code>php artisan ptp:reconcile-scores</code> untuk memastikan tidak ada formula nilai yang terganggu.</li>
    </ol>
</div>

<!-- Modal Konfirmasi Kontekstual Backup & Hapus Snapshot -->
<dialog id="confirmBackupActionModal" class="modal-dialog" aria-labelledby="confirmBackupTitle" style="text-align: start; max-width: 440px;">
    <h3 id="confirmBackupTitle" style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">Konfirmasi Tindakan Cadangan</h3>
    <p id="confirmBackupDesc" style="font-size: 13.5px; color: #475569; line-height: 1.55; margin: 0 0 20px 0;"></p>
    <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
        <button type="button" onclick="closeBackupModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Batal</button>
        <button type="button" id="confirmBackupSubmitBtn" style="background: #15803d; color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Konfirmasi</button>
    </div>
</dialog>

<!-- Modal Eksekusi One-Tap Official Release (Go-Live Engine) -->
<dialog id="officialReleaseModal" class="modal-dialog" aria-labelledby="officialReleaseModalTitle" style="text-align: start; max-width: 580px;">
    <form action="{{ route('admin.backup.official-release') }}" method="POST" id="officialReleaseForm">
        @csrf
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px;">
            <div>
                <h3 id="officialReleaseModalTitle" style="font-size: 17px; font-weight: 700; color: #00223B; margin: 0 0 4px 0;">
                    Eksekusi One-Tap Official Release (Go-Live)
                </h3>
                <p style="font-size: 12.5px; color: #475569; margin: 0; line-height: 1.45;">
                    Pilih mode transisi data sebelum aplikasi diterbitkan ke lingkungan operasional resmi Cabang Cirebon.
                </p>
            </div>
            <button type="button" onclick="closeOfficialReleaseModal()" aria-label="Tutup modal rilis resmi" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-weight: 700; flex-shrink: 0;">&times;</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #00223B; background: #f8fafc; border-radius: 8px; cursor: pointer;">
                <input type="radio" name="release_mode" value="purge_and_ready" checked style="margin-top: 3px; accent-color: #00223B;">
                <div>
                    <div style="font-size: 13px; font-weight: 700; color: #00223B;">
                        Mode Transisi Go-Live (Bersihkan Data Testing &amp; Rapikan Master Real)
                    </div>
                    <div style="font-size: 12px; color: #475569; line-height: 1.45; margin-top: 2px;">
                        Membuat backup otomatis, menormalisasi Master Karyawan/Divisi/Penilai (mempertahankan 100% revisi data real), menghapus seluruh evaluasi &amp; pengajuan uji coba, membuka periode berjalan, dan mengaudit kualitas data.
                    </div>
                </div>
            </label>

            <label style="display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 8px; cursor: pointer;">
                <input type="radio" name="release_mode" value="sanitize_and_reconcile" style="margin-top: 3px; accent-color: #15803d;">
                <div>
                    <div style="font-size: 13px; font-weight: 700; color: #0f172a;">
                        Mode Finalisasi &amp; Rekonsiliasi Data Real (Tanpa Hapus Penilaian)
                    </div>
                    <div style="font-size: 12px; color: #475569; line-height: 1.45; margin-top: 2px;">
                        Merapikan Master Data, memvalidasi ulang akurasi matematis skor &amp; predikat 17 indikator AKHLAK pada seluruh penilaian yang sudah masuk, membersihkan pengajuan ditolak, serta mengunci kesiapan rilis.
                    </div>
                </div>
            </label>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 18px;">
            <label for="releaseConfirmationInput" style="display: block; font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
                Konfirmasi Keamanan Eksekusi (Ketik <code>RILIS-RESMI</code>)
            </label>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="text"
                       name="confirmation_code"
                       id="releaseConfirmationInput"
                       required
                       autocomplete="off"
                       placeholder="Ketik: RILIS-RESMI"
                       class="form-control-custom"
                       style="flex: 1; min-width: 180px; padding: 8px 12px; font-size: 13px; border: 1px solid #64748b; border-radius: 6px; font-family: monospace; text-transform: uppercase;">
                <button type="button"
                        onclick="document.getElementById('releaseConfirmationInput').value='RILIS-RESMI'"
                        style="background: #e2e8f0; color: #0f172a; border: 1px solid #94a3b8; padding: 8px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;">
                    Isi Otomatis Kode
                </button>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
            <button type="button" onclick="closeOfficialReleaseModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 9px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Batal
            </button>
            <button type="submit" id="submitOfficialReleaseBtn" style="background: #15803d; color: #ffffff; border: none; padding: 9px 20px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;">
                Eksekusi Rilis Resmi Sekarang
            </button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
    var pendingBackupForm = null;
    var backupModal = document.getElementById('confirmBackupActionModal');
    var officialReleaseModal = document.getElementById('officialReleaseModal');

    function closeBackupModal() {
        if (backupModal && typeof backupModal.close === 'function' && backupModal.open) backupModal.close();
        else if (backupModal) backupModal.removeAttribute('open');
    }

    function openOfficialReleaseModal() {
        if (officialReleaseModal && typeof officialReleaseModal.showModal === 'function') officialReleaseModal.showModal();
        else if (officialReleaseModal) officialReleaseModal.setAttribute('open', '');
    }

    function closeOfficialReleaseModal() {
        if (officialReleaseModal && typeof officialReleaseModal.close === 'function' && officialReleaseModal.open) officialReleaseModal.close();
        else if (officialReleaseModal) officialReleaseModal.removeAttribute('open');
    }

    officialReleaseModal?.addEventListener('click', function(e) {
        if (e.target === officialReleaseModal) {
            var r = officialReleaseModal.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                closeOfficialReleaseModal();
            }
        }
    });

    document.querySelectorAll('.js-confirm-backup-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            pendingBackupForm = form;
            var action = form.dataset.backupAction;
            var titleEl = document.getElementById('confirmBackupTitle');
            var descEl = document.getElementById('confirmBackupDesc');
            var submitBtn = document.getElementById('confirmBackupSubmitBtn');

            if (action === 'delete') {
                var filename = form.dataset.filename || 'berkas ini';
                titleEl.textContent = 'Hapus Berkas Cadangan';
                descEl.textContent = 'Arsip snapshot "' + filename + '" akan dihapus permanen dari penyimpanan server lokal. Pastikan Anda telah menyimpan salinan eksternal bila diperlukan.';
                submitBtn.textContent = 'Hapus Berkas Cadangan';
                submitBtn.style.background = '#b91c1c';
            } else {
                titleEl.textContent = 'Buat Snapshot Cadangan Baru';
                descEl.textContent = 'Sistem akan membekukan salinan utuh basis data beserta sidik integritas SHA-256 ke direktori penyimpanan cadangan.';
                submitBtn.textContent = 'Buat Cadangan Sekarang';
                submitBtn.style.background = '#15803d';
            }

            if (backupModal && typeof backupModal.showModal === 'function') backupModal.showModal();
            else if (backupModal) backupModal.setAttribute('open', '');
        });
    });

    document.getElementById('confirmBackupSubmitBtn')?.addEventListener('click', function() {
        if (pendingBackupForm) {
            pendingBackupForm.dataset.confirmed = '1';
            closeBackupModal();
            pendingBackupForm.submit();
        }
    });

    backupModal?.addEventListener('click', function(e) {
        if (e.target === backupModal) {
            var r = backupModal.getBoundingClientRect();
            if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                closeBackupModal();
            }
        }
    });

    document.querySelectorAll('.js-copy-sha').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var sha = btn.dataset.sha || '';
            if (!sha) return;
            var announce = function() {
                var orig = btn.textContent;
                btn.textContent = 'Tersalin';
                var live = document.getElementById('shaCopyStatus');
                if (live) live.textContent = 'Kode SHA-256 lengkap berhasil disalin ke papan klip.';
                setTimeout(function() { btn.textContent = orig; }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(sha).then(announce);
            } else {
                var temp = document.createElement('textarea');
                temp.value = sha;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                announce();
            }
        });
    });
</script>
@endpush
@endsection

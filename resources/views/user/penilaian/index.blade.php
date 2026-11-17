@extends('layouts.app', ['title' => 'Penilaian Karyawan'])

@section('content')
<div class="page-title-box" style="margin-bottom: 20px;">
    <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">Penilaian Karyawan</h1>
    <p style="font-size: 14px; color: #64748b; margin: 0;">Lakukan penilaian untuk karyawan di divisi Anda</p>
</div>

<!-- Filter Bar Sesuai Gambar 4.1 SRS -->
<div class="card" style="background: #ffffff; padding: 16px 24px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
    <form method="GET" action="{{ route('penilaian.index') }}" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <label for="penilaianMonth" style="font-size: 14px; font-weight: 600; color: #334155;">Periode:</label>
        <select id="penilaianMonth" name="month" aria-label="Pilih bulan periode" class="form-control-custom" style="width: auto; min-width: 140px; padding: 8px 12px; border: 1px solid #64748b;">
            @foreach($monthNames as $m => $name)
                <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>

        <select id="penilaianYear" name="year" aria-label="Pilih tahun periode" class="form-control-custom" style="width: auto; min-width: 100px; padding: 8px 12px; border: 1px solid #64748b;">
            @for($y = 2025; $y <= 2028; $y++)
                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>

        <button type="submit" style="background: #005FA8; color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;">
            Tampilkan
        </button>
    </form>
</div>

<!-- Banner Oranye Sesuai Gambar 4.2 SRS Bila Periode Tertutup -->
@if(!$isPeriodOpen && !$hasApprovedDivisionAccess)
<div style="background: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #c2410c; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
    <div style="display: flex; align-items: flex-start; gap: 12px;">
        <span aria-hidden="true" style="color: #c2410c; font-size: 20px;">⚠</span>
        <div>
            <div style="font-size: 14px; font-weight: 700; color: #9a3412; margin-bottom: 2px;">Akses Penilaian Periode Ini</div>
            <div style="font-size: 13px; color: #9a3412;">
                Periode ini berstatus <strong>Tertutup</strong>. Anda memerlukan persetujuan administrator untuk membuka kembali akses penilaian.
            </div>
        </div>
    </div>
    <div>
        <button type="button" onclick="openDivisionRequestModal()" style="background: #c2410c; color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            <span>Ajukan Akses</span>
        </button>
    </div>
</div>
@endif

<!-- Tab Switcher + Instant Quick Search (Shortcut /) -->
<div style="display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year, 'tab' => 'pending']) }}" 
           @if($activeTab === 'pending') aria-current="page" @endif
           style="padding: 8px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; {{ $activeTab === 'pending' ? 'background: #e0f2fe; color: #005FA8;' : 'color: #475569; background: transparent;' }}">
            <span>Belum Dinilai</span>
            <span class="tabular-nums" style="background: {{ $activeTab === 'pending' ? '#005FA8' : '#e2e8f0' }}; color: {{ $activeTab === 'pending' ? '#ffffff' : '#334155' }}; font-size: 11px; padding: 2px 8px; border-radius: 999px;">
                {{ $totalPending }}
            </span>
        </a>

        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year, 'tab' => 'evaluated']) }}" 
           @if($activeTab === 'evaluated') aria-current="page" @endif
           style="padding: 8px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; {{ $activeTab === 'evaluated' ? 'background: #e0f2fe; color: #005FA8;' : 'color: #475569; background: transparent;' }}">
            <span>Sudah Dinilai</span>
            <span class="tabular-nums" style="background: {{ $activeTab === 'evaluated' ? '#005FA8' : '#e2e8f0' }}; color: {{ $activeTab === 'evaluated' ? '#ffffff' : '#334155' }}; font-size: 11px; padding: 2px 8px; border-radius: 999px;">
                {{ $totalEvaluated }}
            </span>
        </a>
    </div>

    <div class="quick-search-input-wrap">
        <svg aria-hidden="true" class="search-ico" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" id="penilaianQuickSearch" data-quick-search class="quick-search-input" placeholder="Cari nama / jabatan TAD..." aria-label="Cari karyawan TAD dalam daftar">
        <span class="kbd-hint" aria-hidden="true">/</span>
    </div>
</div>

<!-- Konten Tabel Karyawan -->
<div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
        <span style="font-size: 15px; font-weight: 700; color: #0f172a;">
            Karyawan &mdash; {{ $monthNames[$month] ?? '' }} {{ $year }}
        </span>
        <span id="penilaianRowCount" style="font-size: 12px; color: #64748b; font-weight: 500;"></span>
    </div>

    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                    <th style="padding: 14px 20px; width: 60px;">No</th>
                    <th style="padding: 14px 20px;">Nama</th>
                    <th style="padding: 14px 20px;">Jabatan</th>
                    <th style="padding: 14px 20px;">Status</th>
                    <th style="padding: 14px 20px; text-align: end;">Total Skor</th>
                    <th style="padding: 14px 20px; text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody id="penilaianTableBody">
                @if($activeTab === 'pending')
                    @forelse($pendingEmployees as $index => $item)
                        <tr class="penilaian-row" data-search="{{ strtolower($item['employee']->name.' '.($item['employee']->position ?? '')) }}" style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;">
                            <td style="padding: 14px 20px; color: #64748b;">{{ $index + 1 }}</td>
                            <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $item['employee']->name }}</td>
                            <td style="padding: 14px 20px; color: #475569;">{{ $item['employee']->position ?? '-' }}</td>
                            <td style="padding: 14px 20px;">
                                <span style="background: #ffedd5; color: #9a3412; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 11px;">
                                    Belum Dinilai
                                </span>
                            </td>
                            <td style="padding: 14px 20px; color: #64748b; text-align: end;">&mdash;</td>
                            <td style="padding: 14px 20px; text-align: center;">
                                @if($item['can_evaluate'])
                                    <a href="{{ route('penilaian.create', ['employee_id' => $item['employee']->id, 'month' => $month, 'year' => $year]) }}" 
                                       class="table-action-btn"
                                       style="background: #005FA8; color: #ffffff; text-decoration: none; padding: 6px 16px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">
                                        Nilai
                                    </a>
                                @else
                                    <span style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block;">
                                        Akses Terkunci
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">
                                Seluruh karyawan pada divisi ini telah selesai dinilai untuk periode {{ $monthNames[$month] ?? '' }} {{ $year }}.
                            </td>
                        </tr>
                    @endforelse
                @else
                    @forelse($evaluatedEmployees as $index => $item)
                        <tr class="penilaian-row" data-search="{{ strtolower($item['employee']->name.' '.($item['employee']->position ?? '')) }}" style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 20px; color: #64748b;">{{ $index + 1 }}</td>
                            <td style="padding: 14px 20px; font-weight: 600; color: #0f172a;">{{ $item['employee']->name }}</td>
                            <td style="padding: 14px 20px; color: #475569;">{{ $item['employee']->position ?? '-' }}</td>
                            <td style="padding: 14px 20px;">
                                <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 11px;">
                                    Sudah Dinilai
                                </span>
                            </td>
                            <td style="padding: 14px 20px; font-weight: 700; color: #0f172a; text-align: end;">
                                <span class="badge tabular-nums {{ $item['evaluation']->badge_class }}" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    {{ number_format($item['evaluation']->total_score, 2) }}
                                </span>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 8px;">
                                    @if($item['can_edit'])
                                        <a href="{{ route('penilaian.edit', ['id' => $item['evaluation']->id]) }}" 
                                           class="table-action-btn"
                                           style="background: #005FA8; color: #ffffff; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">
                                            Edit
                                        </a>
                                    @else
                                        <button type="button" class="table-action-btn" data-id="{{ $item['employee']->id }}" data-name="{{ $item['employee']->name }}" onclick="openIndividualRequestModalFromBtn(this)" 
                                                style="background: #c2410c; color: #ffffff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                                            Ajukan Edit
                                        </button>
                                    @endif
                                    <a href="{{ route('laporan.detail', ['id' => $item['evaluation']->id]) }}" 
                                       class="table-action-btn"
                                       style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center;">
                                        Lihat
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: #64748b;">
                                Belum ada karyawan yang dinilai pada periode {{ $monthNames[$month] ?? '' }} {{ $year }}.
                            </td>
                        </tr>
                    @endforelse
                @endif
                <tr id="penilaianSearchEmptyRow" style="display: none;">
                    <td colspan="6" style="padding: 36px 20px; text-align: center; color: #64748b; font-size: 13px;">
                        Tidak ditemukan karyawan yang cocok dengan kata kunci "<strong id="penilaianSearchQueryText"></strong>".
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajukan Akses Divisi (Native Dialog) -->
<dialog id="divisionRequestModal" class="modal-dialog" aria-labelledby="divReqModalTitle" style="text-align: left; max-width: 480px;">
    <h3 id="divReqModalTitle" style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">Permohonan Pembukaan Akses Periode</h3>
    <p style="font-size: 13px; color: #475569; margin: 0 0 16px 0;">
        Ajukan permohonan ke Admin SPKTAD untuk membuka periode <strong>{{ $monthNames[$month] ?? '' }} {{ $year }}</strong>.
    </p>

    <form action="{{ route('request.division') }}" method="POST">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="year" value="{{ $year }}">

        <div style="margin-bottom: 16px;">
            <label for="divReqReason" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Alasan Permohonan</label>
            <textarea id="divReqReason" name="reason" rows="3" class="form-control-custom" placeholder="Contoh: Keterlambatan input data operasional..." required style="width: 100%; padding: 10px 12px; border: 1px solid #64748b;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
            <button type="button" onclick="closeDivisionRequestModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Batal</button>
            <button type="submit" style="background: #c2410c; color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Kirim Permohonan</button>
        </div>
    </form>
</dialog>

<!-- Modal Ajukan Edit Individual (Native Dialog) -->
<dialog id="individualRequestModal" class="modal-dialog" aria-labelledby="indReqModalTitle" style="text-align: left; max-width: 480px;">
    <h3 id="indReqModalTitle" style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">Permohonan Edit Nilai Karyawan</h3>
    <p style="font-size: 13px; color: #475569; margin: 0 0 16px 0;">
        Karyawan: <strong id="reqEmployeeName" style="color: #0f172a;"></strong> &mdash; Periode: <strong>{{ $monthNames[$month] ?? '' }} {{ $year }}</strong>
    </p>

    <form action="{{ route('request.individual') }}" method="POST">
        @csrf
        <input type="hidden" name="employee_id" id="reqEmployeeId" value="">
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="year" value="{{ $year }}">

        <div style="margin-bottom: 16px;">
            <label for="indReqReason" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Alasan Koreksi / Pembaruan</label>
            <textarea id="indReqReason" name="reason" rows="3" class="form-control-custom" placeholder="Contoh: Koreksi butir aspek kompetensi..." required style="width: 100%; padding: 10px 12px; border: 1px solid #64748b;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
            <button type="button" onclick="closeIndividualRequestModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Batal</button>
            <button type="submit" style="background: #c2410c; color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Kirim Permohonan</button>
        </div>
    </form>
</dialog>

<script>
    function openModalEl(id) {
        const el = document.getElementById(id);
        if (el && typeof el.showModal === 'function') el.showModal();
        else if (el) el.setAttribute('open', '');
    }
    function closeModalEl(id) {
        const el = document.getElementById(id);
        if (el && typeof el.close === 'function' && el.open) el.close();
        else if (el) el.removeAttribute('open');
    }

    function openDivisionRequestModal() {
        openModalEl('divisionRequestModal');
    }
    function closeDivisionRequestModal() {
        closeModalEl('divisionRequestModal');
    }

    function openIndividualRequestModalFromBtn(btn) {
        var id = btn.getAttribute('data-id');
        var name = btn.getAttribute('data-name');
        openIndividualRequestModal(id, name);
    }

    function openIndividualRequestModal(id, name) {
        document.getElementById('reqEmployeeId').value = id;
        document.getElementById('reqEmployeeName').innerText = name;
        openModalEl('individualRequestModal');
    }
    function closeIndividualRequestModal() {
        closeModalEl('individualRequestModal');
    }

    ['divisionRequestModal', 'individualRequestModal'].forEach(function(id) {
        const dlg = document.getElementById(id);
        if (dlg) {
            dlg.addEventListener('click', function(e) {
                if (e.target === dlg) {
                    const r = dlg.getBoundingClientRect();
                    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                        closeModalEl(id);
                    }
                }
            });
        }
    });

    // Pencarian instan tabel karyawan TAD tanpa reload
    (function () {
        const searchInput = document.getElementById('penilaianQuickSearch');
        const rows = Array.from(document.querySelectorAll('.penilaian-row'));
        const emptyRow = document.getElementById('penilaianSearchEmptyRow');
        const queryTextEl = document.getElementById('penilaianSearchQueryText');
        const countEl = document.getElementById('penilaianRowCount');

        if (!searchInput) return;

        function applyQuickFilter() {
            const q = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(function (row) {
                const hay = (row.getAttribute('data-search') || '').toLowerCase();
                const matched = !q || hay.includes(q);
                row.style.display = matched ? '' : 'none';
                if (matched) visibleCount++;
            });

            if (emptyRow && queryTextEl) {
                if (rows.length > 0 && visibleCount === 0 && q !== '') {
                    queryTextEl.textContent = searchInput.value.trim();
                    emptyRow.style.display = '';
                } else {
                    emptyRow.style.display = 'none';
                }
            }

            if (countEl && rows.length > 0) {
                countEl.textContent = q
                    ? 'Menampilkan ' + visibleCount + ' dari ' + rows.length + ' karyawan'
                    : 'Total ' + rows.length + ' karyawan';
            }
        }

        searchInput.addEventListener('input', applyQuickFilter);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && searchInput.value !== '') {
                searchInput.value = '';
                applyQuickFilter();
            }
        });
        applyQuickFilter();
    })();
</script>
@endsection

@extends(Auth::user()->isAdmin() ? 'layouts.admin' : 'layouts.app', ['title' => 'Master Karyawan TAD'])

@section('content')
<div class="page-title-box" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">Master Data Karyawan TAD</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Manajemen direktori Tenaga Alih Daya, penempatan divisi, jabatan operasional, dan status keaktifan</p>
    </div>

    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        @if(Auth::user()->isAdmin())
            <button type="button" onclick="openDivisionModal()" style="background: #ffffff; color: #0f172a; border: 1px solid #64748b; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <span>Kelola Divisi ({{ $divisions->count() }})</span>
            </button>
        @endif

        <button type="button" onclick="openCreateEmployeeModal()" style="background: var(--pelindo-blue-primary, #005b9f); color: #ffffff; border: none; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(0, 91, 159, 0.25);">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Tambah Karyawan TAD</span>
        </button>
    </div>
</div>

<!-- Metric Summary Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span class="kpi-label" title="Total TAD Terdaftar" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748b;">Total TAD Terdaftar</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
        <div class="tabular-nums" style="font-size: 26px; font-weight: 800; color: #0f172a;">{{ $counts['total'] }}</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Seluruh arsip personel cabang</div>
    </div>

    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span class="kpi-label" title="Personel TAD Aktif" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #15803d;">Personel TAD Aktif</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
        </div>
        <div class="tabular-nums" style="font-size: 26px; font-weight: 800; color: #15803d;">{{ $counts['active'] }}</div>
        <div style="font-size: 11.5px; color: #15803d; margin-top: 2px;">Masuk antrian evaluasi bulanan</div>
    </div>

    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span class="kpi-label" title="Personel Nonaktif" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748b;">Personel Nonaktif</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
        </div>
        <div class="tabular-nums" style="font-size: 26px; font-weight: 800; color: #475569;">{{ $counts['inactive'] }}</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">Riwayat evaluasi tetap tersimpan</div>
    </div>

    <div class="card" style="background: #ffffff; padding: 18px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <span class="kpi-label" title="Divisi Operasional Aktif" tabindex="0" style="font-size: 12.5px; font-weight: 600; color: #64748b;">Divisi Operasional Aktif</span>
            <div style="width: 34px; height: 34px; border-radius: 8px; background: #f3e8ff; color: #7e22ce; display: flex; align-items: center; justify-content: center;">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            </div>
        </div>
        <div class="tabular-nums" style="font-size: 26px; font-weight: 800; color: #0f172a;">{{ $counts['divisions'] }}</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">PTP Nonpetikemas Cirebon</div>
    </div>
</div>

<!-- Info Integritas Riwayat -->
<div style="background: #eff6ff; border-inline-start: 4px solid #0369a1; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
    <svg aria-hidden="true" style="color: #0369a1; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div style="font-size: 12.5px; color: #1e3a8a; line-height: 1.5;">
        <strong>Perlindungan Arsip Penilaian:</strong> Karyawan TAD yang sudah purna tugas atau mutasi sebaiknya diubah statusnya menjadi <strong>Nonaktif</strong> agar seluruh rekapitulasi nilai historis bulanan tetap utuh dan teraudit. Penghapusan permanen hanya diizinkan bagi entri baru yang belum memiliki riwayat evaluasi.
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 16px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <form method="GET" action="{{ route('admin.employees.index') }}" style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="flex: 1; min-width: min(100%, 240px); position: relative;">
            <input type="text" id="empSearchInput" name="search" value="{{ $search }}" aria-label="Cari nama karyawan, NIK TAD, jabatan, atau vendor" placeholder="Cari nama karyawan, NIK TAD, jabatan, atau vendor..." class="form-control-custom" style="width: 100%; padding: 8px 14px; font-size: 13px; border: 1px solid #64748b; border-radius: 8px;">
        </div>

        @if(Auth::user()->isAdmin())
            <div style="display: flex; align-items: center; gap: 8px;">
                <label for="empFilterDivision" style="font-size: 13px; font-weight: 600; color: #475569;">Divisi:</label>
                <select id="empFilterDivision" name="division_id" onchange="this.form.submit()" class="form-control-custom" style="padding: 8px 12px; font-size: 13px; border: 1px solid #64748b; border-radius: 8px; width: auto; max-width: 100%; min-width: 0;">
                    <option value="all" {{ $currentDivision === 'all' ? 'selected' : '' }}>Semua Divisi</option>
                    @foreach($divisions as $div)
                        <option value="{{ $div->id }}" {{ (string) $currentDivision === (string) $div->id ? 'selected' : '' }}>{{ $div->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div style="display: flex; align-items: center; gap: 8px;">
            <label for="empFilterStatus" style="font-size: 13px; font-weight: 600; color: #475569;">Status:</label>
            <select id="empFilterStatus" name="status" onchange="this.form.submit()" class="form-control-custom" style="padding: 8px 12px; font-size: 13px; border: 1px solid #64748b; border-radius: 8px; width: auto; max-width: 100%; min-width: 0;">
                <option value="all" {{ $currentStatus === 'all' ? 'selected' : '' }}>Semua Status</option>
                <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>

        <button type="submit" style="background: #00223B; color: #ffffff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
            Filter
        </button>

        @if($search !== '' || $currentDivision !== 'all' || $currentStatus !== 'all')
            <a href="{{ route('admin.employees.index') }}" style="font-size: 12.5px; color: #b91c1c; font-weight: 600; text-decoration: none; padding: 8px 12px; display: inline-flex; align-items: center;">
                Reset
            </a>
        @endif
    </form>
</div>

<!-- Data Table -->
<div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
    <div style="padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
        <h3 style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0;">Direktori Tenaga Alih Daya</h3>
        <span style="font-size: 12px; color: #64748b;">Menampilkan {{ $employees->total() }} personel</span>
    </div>

    @if($employees->isEmpty())
        <div style="padding: 48px 20px; text-align: center; color: #64748b; font-size: 14px;">
            <svg style="margin: 0 auto 12px auto; display: block; color: #94a3b8;" xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="8" x2="22" y2="13"/><line x1="22" y1="8" x2="17" y2="13"/></svg>
            Tidak ditemukan data karyawan TAD sesuai kriteria pencarian.
        </div>
    @else
        <div class="table-responsive">
            <table class="table-pelindo data-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 13px 18px; text-align: start; width: 48px;">No</th>
                        <th style="padding: 13px 18px; text-align: start;">NIK / ID</th>
                        <th style="padding: 13px 18px; text-align: start;">Nama Karyawan TAD</th>
                        <th style="padding: 13px 18px; text-align: start;">Jabatan / Posisi</th>
                        <th style="padding: 13px 18px; text-align: start;">Divisi Penempatan</th>
                        <th style="padding: 13px 18px; text-align: start;">Mitra Vendor</th>
                        <th style="padding: 13px 18px; text-align: center;">Arsip Nilai</th>
                        <th style="padding: 13px 18px; text-align: center;">Status</th>
                        <th style="padding: 13px 18px; text-align: center; width: 190px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employees as $idx => $emp)
                        <tr style="border-bottom: 1px solid #f1f5f9; {{ !$emp->is_active ? 'background: #f8fafc;' : '' }}">
                            <td class="tabular-nums" style="padding: 13px 18px; color: #64748b; font-weight: 500;">
                                {{ $employees->firstItem() + $idx }}
                            </td>
                            <td class="tabular-nums" style="padding: 13px 18px; font-family: monospace; font-weight: 700; color: #0369a1; font-size: 12.5px;">
                                {{ $emp->nik ?: '-' }}
                            </td>
                            <td style="padding: 13px 18px; font-weight: 700; color: #0f172a;">
                                {{ $emp->name }}
                            </td>
                            <td style="padding: 13px 18px; color: #334155;">
                                {{ $emp->position ?: '-' }}
                            </td>
                            <td style="padding: 13px 18px;">
                                <span style="background: #f1f5f9; color: #0f172a; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                    {{ $emp->division->name ?? '-' }}
                                </span>
                            </td>
                            <td style="padding: 13px 18px; color: #475569; font-size: 12px;">
                                {{ $emp->vendor_name }}
                            </td>
                            <td style="padding: 13px 18px; text-align: center;">
                                <span class="tabular-nums" style="background: #e0f2fe; color: #0369a1; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700;">
                                    {{ $emp->evaluations_count }} periode
                                </span>
                            </td>
                            <td style="padding: 13px 18px; text-align: center;">
                                @if($emp->is_active)
                                    <span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <span aria-hidden="true" style="width: 6px; height: 6px; border-radius: 50%; background: #15803d;"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <span aria-hidden="true" style="width: 6px; height: 6px; border-radius: 50%; background: #64748b;"></span>
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 13px 18px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <button type="button"
                                            class="js-edit-employee table-action-btn"
                                            data-id="{{ $emp->id }}"
                                            data-nik="{{ $emp->nik }}"
                                            data-name="{{ $emp->name }}"
                                            data-division-id="{{ $emp->division_id }}"
                                            data-position="{{ $emp->position }}"
                                            data-vendor="{{ $emp->vendor_name }}"
                                            data-active="{{ $emp->is_active ? '1' : '0' }}"
                                            aria-label="Edit profil {{ $emp->name }}"
                                            title="Edit data karyawan"
                                            style="background: #eff6ff; color: #0369a1; border: 1px solid #bae6fd; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        <span>Edit</span>
                                    </button>

                                    <form method="POST"
                                          action="{{ route('admin.employees.toggle-status', $emp->id) }}"
                                          class="js-confirm-employee-form"
                                          data-emp-name="{{ $emp->name }}"
                                          data-action-type="{{ $emp->is_active ? 'deactivate' : 'activate' }}"
                                          style="margin: 0;">
                                        @csrf
                                        <button type="submit"
                                                class="table-action-btn"
                                                title="{{ $emp->is_active ? 'Nonaktifkan karyawan' : 'Aktifkan kembali karyawan' }}"
                                                style="background: {{ $emp->is_active ? '#fff7ed' : '#f0fdf4' }}; color: {{ $emp->is_active ? '#9a3412' : '#15803d' }}; border: 1px solid {{ $emp->is_active ? '#fed7aa' : '#bbf7d0' }}; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                                            {{ $emp->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>

                                    <form method="POST"
                                          action="{{ route('admin.employees.destroy', $emp->id) }}"
                                          class="js-confirm-employee-form"
                                          data-emp-name="{{ $emp->name }}"
                                          data-action-type="delete"
                                          data-eval-count="{{ $emp->evaluations_count }}"
                                          data-has-history="{{ ($emp->evaluations_count > 0 || $emp->edit_requests_count > 0) ? '1' : '0' }}"
                                          style="margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="table-action-btn"
                                                aria-label="Hapus permanen {{ $emp->name }}"
                                                title="Hapus permanen {{ $emp->name }}"
                                                style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 5px 8px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="padding: 16px 24px; border-top: 1px solid #e2e8f0;">
            {{ $employees->links('vendor.pagination.corporate') }}
        </div>
    @endif
</div>

<!-- Modal Tambah Karyawan TAD (Native Dialog) -->
<dialog id="createEmployeeModal" class="modal-dialog" aria-labelledby="createEmpModalTitle" style="text-align: start; max-width: 520px;">
    <h3 id="createEmpModalTitle" style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">Tambah Karyawan TAD Baru</h3>
    <p style="font-size: 13px; color: #475569; margin: 0 0 20px 0;">Masukkan profil lengkap personel Tenaga Alih Daya sesuai penempatan divisi.</p>

    <form method="POST" action="{{ route('admin.employees.store') }}">
        @csrf
        <div class="modal-grid-2" style="margin-bottom: 14px;">
            <div>
                <label for="create_emp_nik" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">NIK / Kode TAD <span style="color: #64748b; font-weight: 400;">(Opsional)</span></label>
                <input type="text" id="create_emp_nik" name="nik" placeholder="Otomatis jika kosong (TAD-XXXX)" class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label for="create_emp_division" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Divisi Penempatan <span style="color: #b91c1c;">*</span></label>
                @if(Auth::user()->isKepalaDivisi())
                    <input type="hidden" name="division_id" value="{{ Auth::user()->division_id }}">
                    <input type="text" class="form-control-custom" readonly value="{{ Auth::user()->division->name ?? 'Divisi Anda' }}" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 6px; font-size: 13px; color: #475569;">
                @else
                    <select id="create_emp_division" name="division_id" required class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
                        <option value="">-- Pilih Divisi --</option>
                        @foreach($activeDivisions as $div)
                            <option value="{{ $div->id }}">{{ $div->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        <div style="margin-bottom: 14px;">
            <label for="create_emp_name" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Nama Lengkap Karyawan <span style="color: #b91c1c;">*</span></label>
            <input type="text" id="create_emp_name" name="name" required placeholder="Contoh: Ahmad Fauzi" class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
        </div>

        <div style="margin-bottom: 14px;">
            <label for="create_emp_position" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Jabatan / Posisi Operasional <span style="color: #b91c1c;">*</span></label>
            <input type="text" id="create_emp_position" name="position" required placeholder="Contoh: Operator Alat (Forklift) / Adm Kapal" class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
        </div>

        <div style="margin-bottom: 18px;">
            <label for="create_emp_vendor" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Perusahaan Penyedia / Mitra Vendor <span style="color: #b91c1c;">*</span></label>
            <input type="text" id="create_emp_vendor" name="vendor_name" required value="PT Pelindo Daya Sejahtera" class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <button type="button" onclick="closeCreateEmployeeModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Batal</button>
            <button type="submit" style="background: var(--pelindo-blue-primary, #005b9f); color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Simpan Karyawan</button>
        </div>
    </form>
</dialog>

<!-- Modal Edit Karyawan TAD (Native Dialog) -->
<dialog id="editEmployeeModal" class="modal-dialog" aria-labelledby="editEmpModalTitle" style="text-align: start; max-width: 520px;">
    <h3 id="editEmpModalTitle" style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0;">Edit Profil Karyawan TAD</h3>
    <p style="font-size: 13px; color: #475569; margin: 0 0 20px 0;">Perbarui informasi jabatan, penempatan divisi, atau kode identitas personel.</p>

    <form id="editEmployeeForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="modal-grid-2" style="margin-bottom: 14px;">
            <div>
                <label for="edit_emp_nik" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">NIK / Kode TAD</label>
                <input type="text" name="nik" id="edit_emp_nik" class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label for="edit_emp_division" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Divisi Penempatan <span style="color: #b91c1c;">*</span></label>
                @if(Auth::user()->isKepalaDivisi())
                    <input type="hidden" name="division_id" id="edit_emp_division" value="{{ Auth::user()->division_id }}">
                    <input type="text" class="form-control-custom" readonly value="{{ Auth::user()->division->name ?? 'Divisi Anda' }}" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 6px; font-size: 13px; color: #475569;">
                @else
                    <select name="division_id" id="edit_emp_division" required class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
                        @foreach($divisions as $div)
                            <option value="{{ $div->id }}">{{ $div->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        <div style="margin-bottom: 14px;">
            <label for="edit_emp_name" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Nama Lengkap Karyawan <span style="color: #b91c1c;">*</span></label>
            <input type="text" name="name" id="edit_emp_name" required class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
        </div>

        <div style="margin-bottom: 14px;">
            <label for="edit_emp_position" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Jabatan / Posisi Operasional <span style="color: #b91c1c;">*</span></label>
            <input type="text" name="position" id="edit_emp_position" required class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
        </div>

        <div class="modal-grid-2-1" style="margin-bottom: 18px;">
            <div>
                <label for="edit_emp_vendor" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Mitra Vendor <span style="color: #b91c1c;">*</span></label>
                <input type="text" name="vendor_name" id="edit_emp_vendor" required class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label for="edit_emp_active" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">Status Keaktifan</label>
                <select name="is_active" id="edit_emp_active" class="form-control-custom" style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; border-top: 1px solid #e2e8f0; padding-top: 16px;">
            <button type="button" onclick="closeEditEmployeeModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Batal</button>
            <button type="submit" style="background: var(--pelindo-blue-primary, #005b9f); color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Simpan Perubahan</button>
        </div>
    </form>
</dialog>

@if(Auth::user()->isAdmin())
<!-- Modal Divisi (Native Dialog, Overflow-Safe) -->
<dialog id="divisionModal" class="modal-dialog" aria-labelledby="divisionModalTitle" style="text-align: start; max-width: 600px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;">
        <div>
            <h3 id="divisionModalTitle" style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0;">Master Divisi Operasional</h3>
            <p style="font-size: 12.5px; color: #475569; margin: 2px 0 0 0;">Daftar unit kerja penempatan TAD di PTP Nonpetikemas Cirebon</p>
        </div>
        <button type="button" class="table-action-btn" onclick="closeDivisionModal()" aria-label="Tutup dialog master divisi" style="background: #f1f5f9; border: 1px solid #64748b; border-radius: 6px; font-size: 18px; color: #334155; cursor: pointer; padding: 4px 10px; display: inline-flex; align-items: center; justify-content: center;"><span aria-hidden="true">&times;</span></button>
    </div>

    <!-- Tambah Divisi Baru -->
    <form method="POST" action="{{ route('admin.divisions.store') }}" style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 18px;">
        @csrf
        <div style="font-size: 12px; font-weight: 700; color: #334155; text-transform: uppercase; margin-bottom: 8px;">Tambah Divisi Baru</div>
        <div class="modal-division-add-grid">
            <input type="text" name="name" required aria-label="Nama divisi baru" placeholder="Nama Divisi" class="form-control-custom" style="width: 100%; padding: 7px 10px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
            <input type="text" name="description" aria-label="Deskripsi divisi baru" placeholder="Deskripsi singkat unit kerja" class="form-control-custom" style="width: 100%; padding: 7px 10px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px;">
            <button type="submit" style="background: var(--pelindo-blue-primary, #005b9f); color: #ffffff; border: none; padding: 8px 14px; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer;">Tambah</button>
        </div>
    </form>

    <!-- Daftar Divisi -->
    <div style="display: flex; flex-direction: column; gap: 10px;">
        @foreach($divisions as $div)
            <form method="POST" action="{{ route('admin.divisions.update', $div->id) }}" class="modal-division-row-grid" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                @csrf
                @method('PUT')
                <div>
                    <input type="text" name="name" value="{{ $div->name }}" required aria-label="Nama divisi {{ $div->name }}" class="form-control-custom" style="width: 100%; padding: 6px 10px; border: 1px solid #64748b; border-radius: 6px; font-size: 13px; font-weight: 600;">
                    <div class="tabular-nums" style="font-size: 11px; color: #475569; margin-top: 3px;">{{ $div->employees_count }} personel TAD</div>
                </div>
                <input type="text" name="description" value="{{ $div->description }}" aria-label="Deskripsi divisi {{ $div->name }}" placeholder="Deskripsi" class="form-control-custom" style="width: 100%; padding: 6px 10px; border: 1px solid #64748b; border-radius: 6px; font-size: 12.5px;">
                <select name="is_active" aria-label="Status keaktifan divisi {{ $div->name }}" class="form-control-custom" style="width: 100%; padding: 6px 8px; border: 1px solid #64748b; border-radius: 6px; font-size: 12px;">
                    <option value="1" {{ $div->is_active ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ !$div->is_active ? 'selected' : '' }}>Nonaktif</option>
                </select>
                <button type="submit" style="background: #0f172a; color: #ffffff; border: none; padding: 7px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">Simpan</button>
            </form>
        @endforeach
    </div>
</dialog>
@endif

<!-- Modal Konfirmasi Kontekstual Tindakan Personel (Nonaktifkan / Hapus Permanen) -->
<dialog id="confirmEmployeeActionModal" class="modal-dialog" aria-labelledby="confirmEmpActionTitle" style="text-align: start; max-width: 440px;">
    <h3 id="confirmEmpActionTitle" style="font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">Konfirmasi Tindakan Personel</h3>
    <p id="confirmEmpActionDesc" style="font-size: 13.5px; color: #475569; line-height: 1.55; margin: 0 0 20px 0;"></p>
    <div style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
        <button type="button" id="confirmEmpActionCancelBtn" onclick="closeDialogById('confirmEmployeeActionModal')" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Batal</button>
        <button type="button" id="confirmEmpActionSubmitBtn" style="background: #b91c1c; color: #ffffff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">Konfirmasi</button>
    </div>
</dialog>

@push('scripts')
<script>
    function openDialogById(id) {
        var el = document.getElementById(id);
        if (el && typeof el.showModal === 'function') el.showModal();
        else if (el) el.setAttribute('open', '');
    }
    function closeDialogById(id) {
        var el = document.getElementById(id);
        if (el && typeof el.close === 'function' && el.open) el.close();
        else if (el) el.removeAttribute('open');
    }

    function openCreateEmployeeModal() { openDialogById('createEmployeeModal'); }
    function closeCreateEmployeeModal() { closeDialogById('createEmployeeModal'); }
    function closeEditEmployeeModal() { closeDialogById('editEmployeeModal'); }
    function openDivisionModal() { openDialogById('divisionModal'); }
    function closeDivisionModal() { closeDialogById('divisionModal'); }

    document.querySelectorAll('.js-edit-employee').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            document.getElementById('editEmployeeForm').action = "{{ url('/admin/employees') }}/" + encodeURIComponent(id);
            document.getElementById('edit_emp_nik').value = this.dataset.nik || '';
            document.getElementById('edit_emp_name').value = this.dataset.name || '';
            document.getElementById('edit_emp_division').value = this.dataset.divisionId || '';
            document.getElementById('edit_emp_position').value = this.dataset.position || '';
            document.getElementById('edit_emp_vendor').value = this.dataset.vendor || 'PT Pelindo Daya Sejahtera';
            document.getElementById('edit_emp_active').value = this.dataset.active || '1';
            openDialogById('editEmployeeModal');
        });
    });

    function setSafeModalDescription(container, parts) {
        container.textContent = '';
        parts.forEach(function(part) {
            if (part.br) {
                container.appendChild(document.createElement('br'));
            } else if (part.strong) {
                var strong = document.createElement('strong');
                strong.textContent = part.text;
                container.appendChild(strong);
            } else {
                container.appendChild(document.createTextNode(part.text));
            }
        });
    }

    var pendingEmployeeForm = null;
    document.querySelectorAll('.js-confirm-employee-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var type = form.dataset.actionType;
            if (type === 'activate' || form.dataset.confirmed === '1') {
                return;
            }
            e.preventDefault();
            pendingEmployeeForm = form;
            var name = form.dataset.empName || 'personel ini';
            var titleEl = document.getElementById('confirmEmpActionTitle');
            var descEl = document.getElementById('confirmEmpActionDesc');
            var cancelBtn = document.getElementById('confirmEmpActionCancelBtn');
            var submitBtn = document.getElementById('confirmEmpActionSubmitBtn');

            if (type === 'delete') {
                var hasHistory = form.dataset.hasHistory === '1';
                var evalCount = form.dataset.evalCount || '0';
                if (hasHistory) {
                    titleEl.textContent = 'Perlindungan Arsip Riwayat Evaluasi';
                    setSafeModalDescription(descEl, [
                        { text: 'Personel ' },
                        { strong: true, text: name },
                        { text: ' memiliki ' },
                        { strong: true, text: evalCount + ' periode riwayat evaluasi' },
                        { text: '. Demi integritas audit perusahaan, data yang memiliki riwayat penilaian tidak dapat dihapus permanen.' },
                        { br: true },
                        { br: true },
                        { text: 'Gunakan tombol ' },
                        { strong: true, text: 'Nonaktifkan' },
                        { text: ' untuk mengeluarkan personel dari antrian aktif tanpa merusak arsip historis.' }
                    ]);
                    submitBtn.style.display = 'none';
                    cancelBtn.textContent = 'Mengerti';
                    pendingEmployeeForm = null;
                } else {
                    titleEl.textContent = 'Hapus Permanen Personel';
                    setSafeModalDescription(descEl, [
                        { text: 'Data karyawan ' },
                        { strong: true, text: name },
                        { text: ' akan dihapus permanen dari direktori cabang. Tindakan ini tidak dapat dibatalkan.' }
                    ]);
                    submitBtn.style.display = 'inline-block';
                    submitBtn.textContent = 'Hapus Permanen';
                    submitBtn.style.background = '#b91c1c';
                    cancelBtn.textContent = 'Batal';
                }
            } else {
                titleEl.textContent = 'Nonaktifkan Personel TAD';
                setSafeModalDescription(descEl, [
                    { text: 'Karyawan ' },
                    { strong: true, text: name },
                    { text: ' akan dikeluarkan dari antrian evaluasi aktif, namun seluruh riwayat nilai historis tetap tersimpan aman.' }
                ]);
                submitBtn.style.display = 'inline-block';
                submitBtn.textContent = 'Nonaktifkan Personel';
                submitBtn.style.background = '#c2410c';
                cancelBtn.textContent = 'Batal';
            }
            openDialogById('confirmEmployeeActionModal');
        });
    });

    document.getElementById('confirmEmpActionSubmitBtn')?.addEventListener('click', function() {
        if (pendingEmployeeForm) {
            pendingEmployeeForm.dataset.confirmed = '1';
            closeDialogById('confirmEmployeeActionModal');
            pendingEmployeeForm.submit();
        }
    });

    ['createEmployeeModal', 'editEmployeeModal', 'divisionModal', 'confirmEmployeeActionModal'].forEach(function(modalId) {
        var el = document.getElementById(modalId);
        if (el) {
            el.addEventListener('click', function(e) {
                if (e.target === el) {
                    var r = el.getBoundingClientRect();
                    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) {
                        closeDialogById(modalId);
                    }
                }
            });
        }
    });
</script>
@endpush
@endsection

@extends('layouts.app', ['title' => ($isEdit ? 'Edit' : 'Form') . ' Penilaian'])

@section('content')
<div class="page-title-box" style="margin-bottom: 20px;">
    <h1 style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">{{ $isEdit ? 'Edit' : 'Form' }} Penilaian</h1>
    <p style="font-size: 14px; color: #475569; margin: 0;">
        <strong>{{ $employee->name }}</strong> 
        @if($employee->position) &mdash; <span style="color: #64748b;">{{ $employee->position }}</span> @endif
        &mdash; Periode: <span style="color: #005FA8; font-weight: 600;">{{ $periodLabel }}</span>
    </p>
</div>

<!-- Legenda Skala Penilaian Sesuai Bab 4.5 Gambar 4.4 SRS -->
<div class="card" style="background: #ffffff; padding: 14px 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
    <div style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
        Skala Penilaian (1 s.d. 6):
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
        @foreach($ratingOptions as $opt)
            <div style="display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 12px; border-radius: 999px; font-size: 12px;">
                <span style="width: 14px; height: 14px; border-radius: 50%; background: {{ $opt['color'] }}; display: inline-block;"></span>
                <span style="font-weight: 600; color: #1e293b;">{{ $opt['label'] }}</span>
                <span style="color: #64748b; font-size: 11px;">({{ number_format($opt['score'], 2) }})</span>
            </div>
        @endforeach
    </div>
</div>

<!-- Banner Pemulihan Draf Penilaian (Auto-Save LocalStorage) -->
<div id="draftRestoreAlert" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #005FA8; border-radius: 8px; padding: 12px 18px; margin-bottom: 20px; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: #1e3a8a;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        <span><strong>Draf Tersimpan Ditemukan:</strong> Ada draf isian penilaian lokal dari <span id="draftTime" style="font-weight: 600;"></span>. Pulihkan nilai ke formulir?</span>
    </div>
    <div style="display: flex; gap: 8px;">
        <button type="button" id="btnRestoreDraft" style="background: #005FA8; color: #ffffff; border: none; padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s ease;">Pulihkan Draf</button>
        <button type="button" id="btnDiscardDraft" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 7px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;">Buang</button>
    </div>
</div>

<form id="evaluationForm" action="{{ route('penilaian.store') }}" method="POST">
    @csrf
    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
    <input type="hidden" name="month" value="{{ $month }}">
    <input type="hidden" name="year" value="{{ $year }}">

    @php $itemGlobalNumber = 1; @endphp

    @foreach($categories as $categoryIndex => $cat)
        <div class="card" style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 24px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
            <!-- Header Kategori Navy dengan Nomor Lingkaran Sesuai SRS -->
            <div style="background: #00223B; color: #ffffff; padding: 14px 20px; display: flex; align-items: center; gap: 10px;">
                <div style="width: 26px; height: 26px; border-radius: 50%; background: #38BDF8; color: #00223B; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    {{ $cat->order_index ?? ($categoryIndex + 1) }}
                </div>
                <h3 style="font-size: 15px; font-weight: 700; margin: 0; color: #ffffff;">{{ $cat->name }}</h3>
            </div>

            <!-- Tabel Butir Subkategori -->
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse; text-align: start; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 11px; text-transform: uppercase;">
                            <th style="padding: 10px 16px; width: 45px; text-align: center;">No</th>
                            <th style="padding: 10px 16px; text-align: start;">Aspek Penilaian</th>
                            <th style="padding: 10px 16px; width: 280px; text-align: center;">Skor (1 &ndash; 6)</th>
                            <th style="padding: 10px 16px; width: 340px; text-align: start;">Alasan / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cat->subcategories as $sub)
                            @php
                                $currentRating = old('ratings.' . $sub->id, $existingData[$sub->id]['rating'] ?? 5);
                                $currentReason = old('reasons.' . $sub->id, $existingData[$sub->id]['reason'] ?? $sub->default_reason ?? '');
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 16px; text-align: center; color: #64748b; vertical-align: top;">
                                    {{ $itemGlobalNumber++ }}
                                </td>
                                <td style="padding: 16px; color: #1e293b; font-weight: 500; line-height: 1.5; vertical-align: top;">
                                    {{ $sub->name }}
                                </td>
                                <td style="padding: 16px; text-align: center; vertical-align: top;">
                                    <div role="radiogroup" aria-label="Skor untuk {{ $sub->name }}" style="display: flex; justify-content: center; gap: 6px; flex-wrap: nowrap;">
                                        @foreach($ratingOptions as $opt)
                                            <label class="rating-touch-label" style="position: relative; cursor: pointer; display: inline-flex; flex-direction: column; align-items: center; justify-content: center; padding: 4px;" title="{{ $opt['label'] }} ({{ $opt['score'] }})">
                                                <input type="radio" 
                                                       name="ratings[{{ $sub->id }}]" 
                                                       value="{{ $opt['rating'] }}" 
                                                       class="rating-radio sr-only-radio"
                                                       data-subid="{{ $sub->id }}"
                                                       {{ $currentRating == $opt['rating'] ? 'checked' : '' }}>
                                                <span class="sr-only">Skor {{ $opt['rating'] }} ({{ $opt['label'] }}) untuk {{ $sub->name }}</span>
                                                <span aria-hidden="true" class="rating-circle rating-circle-{{ $opt['rating'] }}" 
                                                      style="width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid {{ $opt['color'] }}; color: {{ $opt['color'] }}; transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;">
                                                    {{ $opt['rating'] }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="padding: 16px; vertical-align: top;">
                                    <textarea name="reasons[{{ $sub->id }}]" 
                                              rows="2" 
                                              aria-label="Alasan penilaian untuk {{ $sub->name }}"
                                              class="form-control-custom auto-save-input" 
                                              data-key="reason_{{ $sub->id }}"
                                              placeholder="Tulis alasan / catatan..." 
                                              style="width: 100%; padding: 8px 12px; border: 1px solid #64748b; border-radius: 6px; font-size: 12.5px; resize: vertical; line-height: 1.4;">{{ $currentReason }}</textarea>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <!-- Tombol Simpan & Batal Sesuai Gambar 4.5 SRS -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <a href="{{ route('penilaian.index', ['month' => $month, 'year' => $year]) }}" 
           style="background: #f1f5f9; color: #334155; text-decoration: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; border: 1px solid #64748b; display: inline-flex; align-items: center; gap: 8px;">
            <span>&larr; Batal</span>
        </a>

        <button type="submit" 
                style="background: #15803d; color: #ffffff; border: none; padding: 10px 32px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; box-shadow: 0 4px 6px -1px rgba(21, 128, 61, 0.28);">
            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <span>Simpan Penilaian</span>
        </button>
    </div>
</form>

<style>
    /* Styling interaktif lingkaran skor radio (WCAG AA >= 4.5:1) */
    input[type="radio"]:checked ~ .rating-circle-1 { background: #b91c1c !important; color: #ffffff !important; }
    input[type="radio"]:checked ~ .rating-circle-2 { background: #c2410c !important; color: #ffffff !important; }
    input[type="radio"]:checked ~ .rating-circle-3 { background: #a16207 !important; color: #ffffff !important; }
    input[type="radio"]:checked ~ .rating-circle-4 { background: #0369a1 !important; color: #ffffff !important; }
    input[type="radio"]:checked ~ .rating-circle-5 { background: #15803d !important; color: #ffffff !important; }
    input[type="radio"]:checked ~ .rating-circle-6 { background: #166534 !important; color: #ffffff !important; }
    input.rating-radio:focus-visible ~ .rating-circle { outline: 2px solid #005FA8 !important; outline-offset: 2px !important; }
    @media (pointer: coarse) {
        .rating-touch-label { min-width: 44px; min-height: 44px; }
    }
</style>

<script>
    // Fitur Anti Data Loss (Auto Save Draft di LocalStorage)
    const empId = "{{ $employee->id }}";
    const draftKey = `draft_eval_${empId}_{{ $month }}_{{ $year }}`;

    // Auto save textarea
    document.querySelectorAll('.auto-save-input').forEach(textarea => {
        textarea.addEventListener('input', () => {
            saveDraft();
        });
    });

    // Auto save radio
    document.querySelectorAll('.rating-radio').forEach(radio => {
        radio.addEventListener('change', () => {
            saveDraft();
        });
    });

    function saveDraft() {
        const ratings = {};
        document.querySelectorAll('.rating-radio:checked').forEach(r => {
            ratings[r.dataset.subid] = r.value;
        });

        const reasons = {};
        document.querySelectorAll('.auto-save-input').forEach(t => {
            reasons[t.dataset.key] = t.value;
        });

        localStorage.setItem(draftKey, JSON.stringify({ ratings, reasons, time: new Date().getTime() }));
    }

    // Inisialisasi Deteksi & Pemulihan Draf Tersimpan
    document.addEventListener('DOMContentLoaded', () => {
        const savedDraft = localStorage.getItem(draftKey);
        if (!savedDraft) return;

        try {
            const draft = JSON.parse(savedDraft);
            const hasRatings = draft.ratings && Object.keys(draft.ratings).length > 0;
            const hasReasons = draft.reasons && Object.values(draft.reasons).some(r => typeof r === 'string' && r.trim() !== '');

            if (hasRatings || hasReasons) {
                const alertEl = document.getElementById('draftRestoreAlert');
                const timeEl = document.getElementById('draftTime');
                if (alertEl && timeEl) {
                    if (draft.time) {
                        const d = new Date(draft.time);
                        timeEl.textContent = d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }) + ', pukul ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    } else {
                        timeEl.textContent = 'sesi sebelumnya';
                    }
                    alertEl.style.display = 'flex';

                    document.getElementById('btnRestoreDraft').addEventListener('click', () => {
                        if (draft.ratings) {
                            Object.entries(draft.ratings).forEach(([subId, ratingVal]) => {
                                const radio = document.querySelector(`.rating-radio[data-subid="${subId}"][value="${ratingVal}"]`);
                                if (radio) {
                                    radio.checked = true;
                                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            });
                        }
                        if (draft.reasons) {
                            Object.entries(draft.reasons).forEach(([key, val]) => {
                                const textarea = document.querySelector(`.auto-save-input[data-key="${key}"]`);
                                if (textarea) {
                                    textarea.value = val;
                                }
                            });
                        }
                        alertEl.style.display = 'none';
                    });

                    document.getElementById('btnDiscardDraft').addEventListener('click', () => {
                        localStorage.removeItem(draftKey);
                        alertEl.style.display = 'none';
                    });
                }
            }
        } catch (err) {
            console.error('Gagal membaca draf lokal:', err);
        }
    });

    // Bersihkan draft saat submit berhasil
    document.getElementById('evaluationForm').addEventListener('submit', () => {
        localStorage.removeItem(draftKey);
    });
</script>
@endsection

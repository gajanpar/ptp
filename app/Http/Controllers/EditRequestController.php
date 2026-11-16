<?php

namespace App\Http\Controllers;

use App\Models\EditRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EditRequestController extends Controller
{
    public function requestDivisionAccess(Request $request)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        if (! $divisionId) {
            return back()->with('error', 'Akun Administrator tidak terikat pada satu divisi; gunakan menu Manajemen Periode untuk membuka akses divisi secara langsung.');
        }

        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2100',
            'reason' => 'nullable|string|max:1000',
        ]);

        $month = (int) $request->month;
        $year = (int) $request->year;

        // Cek apakah sudah ada request pending
        $existing = EditRequest::where('division_id', $divisionId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->where('request_type', 'division_access')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->with('info', 'Permohonan pembukaan akses untuk periode ini sudah pernah diajukan dan sedang menunggu persetujuan admin.');
        }

        EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => $divisionId,
            'requested_by' => $user->id,
            'period_month' => $month,
            'period_year' => $year,
            'reason' => $request->reason ?? 'Permohonan pembukaan akses penilaian periode tertutup',
            'status' => 'pending',
        ]);

        return back()->with('success', 'Permohonan pembukaan akses berhasil diajukan ke Admin SPKTAD.');
    }

    public function requestIndividualEdit(Request $request)
    {
        $user = Auth::user();
        $divisionId = $user->division_id;

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2020,2035',
            'reason' => 'nullable|string|max:1000',
        ]);

        $emp = Employee::findOrFail($request->employee_id);

        // BOLA/IDOR prevention: a Kepala Divisi may only request edits for their own division's personnel.
        if ($divisionId !== null && (int) $emp->division_id !== (int) $divisionId) {
            abort(403, 'Akses ditolak. Anda hanya dapat mengajukan permohonan edit untuk personel divisi Anda sendiri.');
        }

        $existing = EditRequest::where('employee_id', $emp->id)
            ->where('period_month', $request->month)
            ->where('period_year', $request->year)
            ->where('request_type', 'individual_edit')
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return back()->with('info', 'Permohonan edit nilai untuk '.$emp->name.' sedang menunggu persetujuan admin.');
        }

        EditRequest::create([
            'request_type' => 'individual_edit',
            'employee_id' => $emp->id,
            'division_id' => $emp->division_id,
            'requested_by' => $user->id,
            'period_month' => (int) $request->month,
            'period_year' => (int) $request->year,
            'reason' => $request->reason ?? 'Permohonan perubahan/pembaruan penilaian karyawan',
            'status' => 'pending',
        ]);

        return back()->with('success', 'Permohonan izin edit nilai untuk '.$emp->name.' berhasil diajukan ke Admin.');
    }
}

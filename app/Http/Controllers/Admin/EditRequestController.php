<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\EditRequest;
use App\Models\PeriodManagement;
use App\Services\EvaluationScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EditRequestController extends Controller
{
    /**
     * Tampilkan daftar pengajuan permohonan edit nilai & buka akses periode.
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');
        $type = $request->get('type', 'all');
        $divisionId = $request->get('division_id', 'all');

        $query = EditRequest::with(['division', 'employee', 'requester', 'reviewer'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type !== 'all') {
            $query->where('request_type', $type);
        }

        if ($divisionId !== 'all') {
            $query->where('division_id', (int) $divisionId);
        }

        $requests = $query->paginate(15)->withQueryString();

        // Hitung total status untuk badge ringkasan
        $counts = [
            'all' => EditRequest::count(),
            'pending' => EditRequest::where('status', 'pending')->count(),
            'approved' => EditRequest::where('status', 'approved')->count(),
            'rejected' => EditRequest::where('status', 'rejected')->count(),
            'completed' => EditRequest::where('status', 'completed')->count(),
        ];

        $divisions = Division::where('is_active', true)->orderBy('name')->get();

        $monthNames = EvaluationScoringService::MONTH_NAMES;

        return view('admin.edit_requests.index', [
            'requests' => $requests,
            'counts' => $counts,
            'currentStatus' => $status,
            'currentType' => $type,
            'currentDivision' => $divisionId,
            'divisions' => $divisions,
            'monthNames' => $monthNames,
        ]);
    }

    /**
     * Setujui pengajuan akses periode atau edit nilai karyawan.
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $editRequest = EditRequest::findOrFail($id);

        if ($editRequest->status === 'completed') {
            return back()->with('info', 'Pengajuan ini sudah selesai diproses sebelumnya.');
        }

        DB::transaction(function () use ($editRequest, $request) {
            $editRequest->update([
                'status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'admin_note' => $request->admin_note ?? 'Disetujui oleh Administrator SDM/IT.',
            ]);

            // Jika permohonan buka akses divisi, sinkronkan period_management menjadi 'open'
            if ($editRequest->request_type === 'division_access') {
                PeriodManagement::updateOrCreate(
                    [
                        'division_id' => $editRequest->division_id,
                        'period_month' => $editRequest->period_month,
                        'period_year' => $editRequest->period_year,
                    ],
                    [
                        'status' => 'open',
                        'updated_by' => Auth::id(),
                    ]
                );
            }
        });

        $typeLabel = ($editRequest->request_type === 'division_access')
            ? 'Akses Periode Divisi'
            : 'Izin Edit Karyawan';

        ActivityLog::record(
            'edit_request.approved',
            "Menyetujui permohonan {$typeLabel} untuk ".($editRequest->employee ? $editRequest->employee->name : $editRequest->division->name),
            'EditRequest',
            $editRequest->id,
            [
                'request_id' => $editRequest->id,
                'request_type' => $editRequest->request_type,
                'division_id' => $editRequest->division_id,
                'employee_id' => $editRequest->employee_id,
                'admin_note' => $request->admin_note,
            ]
        );

        return back()->with('success', "Permohonan {$typeLabel} berhasil DISETUJUI.");
    }

    /**
     * Tolak pengajuan akses periode atau edit nilai karyawan.
     */
    public function reject(Request $request, $id)
    {
        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $editRequest = EditRequest::findOrFail($id);

        if ($editRequest->status === 'completed') {
            return back()->with('info', 'Pengajuan ini sudah berstatus selesai dan tidak dapat ditolak.');
        }

        $editRequest->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'admin_note' => $request->admin_note ?? 'Permohonan ditolak oleh Administrator SDM/IT.',
        ]);

        ActivityLog::record(
            'edit_request.rejected',
            'Menolak permohonan '.($editRequest->request_type === 'division_access' ? 'Akses Periode' : 'Izin Edit').' dengan alasan: '.($request->admin_note ?? '-'),
            'EditRequest',
            $editRequest->id,
            [
                'request_id' => $editRequest->id,
                'request_type' => $editRequest->request_type,
                'division_id' => $editRequest->division_id,
                'employee_id' => $editRequest->employee_id,
                'admin_note' => $request->admin_note,
            ]
        );

        return back()->with('warning', 'Permohonan berhasil DITOLAK.');
    }
}

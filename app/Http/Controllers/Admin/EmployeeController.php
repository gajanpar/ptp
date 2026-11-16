<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /**
     * Verify that the current user has authority to manage the given employee.
     */
    protected function authorizeEmployeeDivision(Employee $employee, string $action = 'mengelola'): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isKepalaDivisi()) {
            if ((int) $employee->division_id !== (int) $user->division_id) {
                abort(403, "Akses ditolak. Anda tidak memiliki hak akses untuk {$action} personel dari unit divisi lain.");
            }

            return;
        }

        abort(403, 'Akses ditolak.');
    }

    /**
     * Display the TAD employee master directory with filtering and statistics.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $search = trim((string) $request->get('search', ''));
        $currentDivision = (string) $request->get('division_id', 'all');
        $currentStatus = (string) $request->get('status', 'all');

        $query = Employee::with('division')
            ->withCount(['evaluations', 'editRequests'])
            ->orderByDesc('is_active')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('vendor_name', 'like', "%{$search}%");
            });
        }

        if ($user->isKepalaDivisi()) {
            $currentDivision = (string) $user->division_id;
            $query->where('division_id', $user->division_id);
        } elseif ($currentDivision !== 'all') {
            $query->where('division_id', (int) $currentDivision);
        }

        if ($currentStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($currentStatus === 'inactive') {
            $query->where('is_active', false);
        }

        $employees = $query->paginate(15)->withQueryString();

        if ($user->isKepalaDivisi()) {
            $divisions = Division::where('id', $user->division_id)->withCount('employees')->get();
            $activeDivisions = $divisions;
            $stats = DB::table('employees')
                ->where('division_id', $user->division_id)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active, SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) as inactive')
                ->first();
            $counts = [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
                'divisions' => 1,
            ];
        } else {
            $divisions = Division::withCount('employees')->orderBy('id')->get();
            $activeDivisions = $divisions->where('is_active', true)->values();
            $stats = DB::table('employees')
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active, SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) as inactive')
                ->first();
            $counts = [
                'total' => (int) $stats->total,
                'active' => (int) $stats->active,
                'inactive' => (int) $stats->inactive,
                'divisions' => $activeDivisions->count(),
            ];
        }

        return view('admin.employees.index', [
            'employees' => $employees,
            'divisions' => $divisions,
            'activeDivisions' => $activeDivisions,
            'counts' => $counts,
            'search' => $search,
            'currentDivision' => $currentDivision,
            'currentStatus' => $currentStatus,
        ]);
    }

    /**
     * Store a newly created TAD employee record.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user->isKepalaDivisi()) {
            $request->merge(['division_id' => $user->division_id]);
        }

        $validated = $request->validate([
            'nik' => ['nullable', 'string', 'max:50', 'unique:employees,nik'],
            'name' => ['required', 'string', 'max:100'],
            'division_id' => ['required', 'exists:divisions,id'],
            'position' => ['required', 'string', 'max:100'],
            'vendor_name' => ['required', 'string', 'max:100'],
        ], [
            'nik.unique' => 'NIK / Kode TAD tersebut sudah terdaftar pada karyawan lain.',
            'name.required' => 'Nama lengkap karyawan TAD wajib diisi.',
            'division_id.required' => 'Divisi penempatan wajib dipilih.',
            'position.required' => 'Jabatan operasional wajib diisi.',
        ]);

        $employee = Employee::create([
            'nik' => $validated['nik'] ?: null,
            'name' => $validated['name'],
            'division_id' => (int) $validated['division_id'],
            'position' => $validated['position'],
            'vendor_name' => $validated['vendor_name'],
            'is_active' => true,
        ]);

        if (empty($employee->nik)) {
            $generatedNik = 'TAD-'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT);
            if (! Employee::where('nik', $generatedNik)->where('id', '!=', $employee->id)->exists()) {
                $employee->update(['nik' => $generatedNik]);
            }
        }

        ActivityLog::record(
            'employee.created',
            "Menambahkan personel TAD: {$employee->name} ({$employee->nik})",
            'Employee',
            $employee->id,
            ['nik' => $employee->nik, 'division_id' => $employee->division_id, 'position' => $employee->position]
        );

        return back()->with('success', "Karyawan TAD {$employee->name} ({$employee->nik}) berhasil ditambahkan.");
    }

    /**
     * Update an existing TAD employee profile.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeDivision($employee, 'memperbarui profil');

        $user = auth()->user();
        if ($user->isKepalaDivisi()) {
            $request->merge(['division_id' => $user->division_id]);
        }

        $validated = $request->validate([
            'nik' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('employees', 'nik')->ignore($employee->id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'division_id' => ['required', 'exists:divisions,id'],
            'position' => ['required', 'string', 'max:100'],
            'vendor_name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ], [
            'nik.unique' => 'NIK / Kode TAD tersebut sudah digunakan oleh karyawan lain.',
        ]);

        $employee->update([
            'nik' => $validated['nik'] ?: $employee->nik,
            'name' => $validated['name'],
            'division_id' => (int) $validated['division_id'],
            'position' => $validated['position'],
            'vendor_name' => $validated['vendor_name'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        ActivityLog::record(
            'employee.updated',
            "Memperbarui profil personel TAD: {$employee->name} ({$employee->nik})",
            'Employee',
            $employee->id,
            ['nik' => $employee->nik, 'is_active' => (bool) $validated['is_active']]
        );

        return back()->with('success', "Profil karyawan TAD {$employee->name} berhasil diperbarui.");
    }

    /**
     * Toggle active/inactive status while preserving historical evaluations.
     */
    public function toggleStatus(Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeDivision($employee, 'mengubah status');

        $newStatus = ! $employee->is_active;
        $employee->update(['is_active' => $newStatus]);

        $statusLabel = $newStatus ? 'DIAKTIFKAN' : 'DINONAKTIFKAN';

        ActivityLog::record(
            'employee.status_toggled',
            "Mengubah status personel TAD {$employee->name} ({$employee->nik}) menjadi {$statusLabel}",
            'Employee',
            $employee->id,
            ['new_status' => $newStatus]
        );

        return back()->with(
            'success',
            "Status personel {$employee->name} berhasil {$statusLabel}. Seluruh arsip riwayat evaluasi tetap terjaga."
        );
    }

    /**
     * Delete a newly created employee only if they have zero evaluation or edit-request history.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorizeEmployeeDivision($employee, 'menghapus');

        if ($employee->evaluations()->exists() || $employee->editRequests()->exists()) {
            return back()->with(
                'error',
                "Data {$employee->name} tidak dapat dihapus permanen karena memiliki riwayat penilaian historis. Gunakan fitur Nonaktifkan untuk menjaga integritas audit."
            );
        }

        try {
            $name = $employee->name;
            $nik = $employee->nik;
            $employee->delete();

            ActivityLog::record(
                'employee.deleted',
                "Menghapus permanen data personel TAD: {$name} ({$nik})",
                'Employee',
                null,
                ['deleted_name' => $name, 'deleted_nik' => $nik]
            );

            return back()->with('success', "Entri karyawan {$name} berhasil dihapus.");
        } catch (QueryException $e) {
            return back()->with(
                'error',
                "Data {$employee->name} tidak dapat dihapus karena masih terkait dengan catatan operasional lain pada sistem."
            );
        }
    }

    /**
     * Create a new operational division.
     */
    public function storeDivision(Request $request): RedirectResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Akses ditolak. Pengelolaan divisi hanya diizinkan untuk Administrator SDM / IT.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:divisions,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Nama divisi tersebut sudah terdaftar.',
        ]);

        $division = Division::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        ActivityLog::record(
            'division.created',
            "Menambahkan divisi operasional baru: {$division->name}",
            'Division',
            $division->id
        );

        return back()->with('success', "Divisi operasional {$division->name} berhasil ditambahkan.");
    }

    /**
     * Update an existing operational division.
     */
    public function updateDivision(Request $request, Division $division): RedirectResponse
    {
        if (! auth()->user()->isAdmin()) {
            abort(403, 'Akses ditolak. Pengelolaan divisi hanya diizinkan untuk Administrator SDM / IT.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('divisions', 'name')->ignore($division->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $division->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) $validated['is_active'],
        ]);

        ActivityLog::record(
            'division.updated',
            "Memperbarui data divisi operasional: {$division->name}",
            'Division',
            $division->id,
            ['is_active' => (bool) $validated['is_active']]
        );

        return back()->with('success', "Data Divisi {$division->name} berhasil diperbarui.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Division;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display the evaluator & administrator account management directory.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $currentRole = (string) $request->get('role', 'all');
        $currentDivision = (string) $request->get('division_id', 'all');
        $currentStatus = (string) $request->get('status', 'all');

        $query = User::with('division')
            ->withCount(['evaluations', 'editRequests'])
            ->orderByRaw("CASE WHEN role = 'admin' THEN 1 ELSE 2 END")
            ->orderByDesc('is_active')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($currentRole !== 'all') {
            $query->where('role', $currentRole);
        }

        if ($currentDivision !== 'all') {
            $query->where('division_id', (int) $currentDivision);
        }

        if ($currentStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($currentStatus === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->paginate(15)->withQueryString();
        $divisions = Division::orderBy('id')->get();
        $activeDivisions = $divisions->where('is_active', true)->values();

        // Single aggregate query replaces four separate count queries (SQLite + PostgreSQL safe).
        $countsRow = DB::table('users')->selectRaw(
            'COUNT(*) as total, '
            ."SUM(CASE WHEN role = 'admin' AND is_active THEN 1 ELSE 0 END) as admins, "
            ."SUM(CASE WHEN role = 'kepala_divisi' AND is_active THEN 1 ELSE 0 END) as evaluators, "
            .'SUM(CASE WHEN NOT is_active THEN 1 ELSE 0 END) as inactive'
        )->first();

        $counts = [
            'total' => (int) $countsRow->total,
            'admins' => (int) $countsRow->admins,
            'evaluators' => (int) $countsRow->evaluators,
            'inactive' => (int) $countsRow->inactive,
        ];

        return view('admin.users.index', [
            'users' => $users,
            'divisions' => $divisions,
            'activeDivisions' => $activeDivisions,
            'counts' => $counts,
            'search' => $search,
            'currentRole' => $currentRole,
            'currentDivision' => $currentDivision,
            'currentStatus' => $currentStatus,
        ]);
    }

    /**
     * Create a new evaluator (Kepala Divisi) or Administrator account.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,kepala_divisi'],
            'division_id' => ['nullable', 'required_if:role,kepala_divisi', 'exists:divisions,id'],
            'position' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.unique' => 'Username tersebut sudah digunakan oleh akun lain.',
            'username.alpha_dash' => 'Username hanya boleh berisi huruf, angka, tanda hubung (-), atau garis bawah (_).',
            'email.unique' => 'Alamat email tersebut sudah terdaftar.',
            'division_id.required_if' => 'Akun Kepala Divisi wajib ditempatkan pada salah satu divisi.',
            'password.min' => 'Password minimal harus terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $isAdmin = $validated['role'] === 'admin';

        $user = User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => $validated['email'] ?: null,
            'role' => $validated['role'],
            'division_id' => $isAdmin ? null : (int) $validated['division_id'],
            'position' => $validated['position'] ?: ($isAdmin ? 'Administrator SDM / IT' : 'Kepala Divisi'),
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        ActivityLog::record(
            'user.created',
            "Membuat akun pengguna baru: {$user->name} (@{$user->username}) role {$user->role}",
            'User',
            $user->id,
            ['username' => $user->username, 'role' => $user->role, 'division_id' => $user->division_id]
        );

        return back()->with('success', "Akun pengguna {$user->name} (@{$user->username}) berhasil dibuat.");
    }

    /**
     * Update an existing user profile without exposing or mutating password hashes.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', 'in:admin,kepala_divisi'],
            'division_id' => ['nullable', 'required_if:role,kepala_divisi', 'exists:divisions,id'],
            'position' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ], [
            'username.unique' => 'Username tersebut sudah digunakan oleh akun lain.',
            'division_id.required_if' => 'Akun Kepala Divisi wajib memiliki divisi penempatan.',
        ]);

        $isSelf = $user->id === Auth::id();
        $targetActive = (bool) $validated['is_active'];

        if ($isSelf && ($validated['role'] !== 'admin' || ! $targetActive)) {
            return back()->with(
                'error',
                'Demi keamanan sistem, Anda tidak dapat menurunkan hak akses atau menonaktifkan akun Administrator yang sedang Anda gunakan.'
            );
        }

        $isAdmin = $validated['role'] === 'admin';

        $user->update([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => $validated['email'] ?: null,
            'role' => $validated['role'],
            'division_id' => $isAdmin ? null : (int) $validated['division_id'],
            'position' => $validated['position'] ?: $user->position,
            'is_active' => $targetActive,
        ]);

        ActivityLog::record(
            'user.updated',
            "Memperbarui profil akun pengguna: {$user->name} (@{$user->username})",
            'User',
            $user->id,
            ['username' => $user->username, 'role' => $user->role, 'is_active' => $targetActive]
        );

        if (! $targetActive) {
            $this->revokeUserSessions($user->id);
        }

        return back()->with('success', "Profil akun {$user->name} (@{$user->username}) berhasil diperbarui.");
    }

    /**
     * Reset a user's password securely by Administrator SDM/IT.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal harus terdiri dari 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        ActivityLog::record(
            'user.password_reset',
            "Mereset kata sandi akun pengguna: {$user->name} (@{$user->username})",
            'User',
            $user->id
        );

        if ($user->id !== Auth::id()) {
            $this->revokeUserSessions($user->id);
        }

        return back()->with('success', "Password untuk akun {$user->name} (@{$user->username}) berhasil direset secara aman.");
    }

    /**
     * Toggle active/inactive account status and immediately revoke active sessions if deactivated.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Administrator yang sedang digunakan.');
        }

        $newStatus = ! $user->is_active;
        $user->update(['is_active' => $newStatus]);

        if (! $newStatus) {
            $this->revokeUserSessions($user->id);
        }

        $statusLabel = $newStatus ? 'DIAKTIFKAN' : 'DINONAKTIFKAN';

        ActivityLog::record(
            'user.status_toggled',
            "Mengubah status akun pengguna {$user->name} (@{$user->username}) menjadi {$statusLabel}",
            'User',
            $user->id,
            ['new_status' => $newStatus]
        );

        return back()->with('success', "Status akun {$user->name} (@{$user->username}) berhasil {$statusLabel}.");
    }

    /**
     * Delete a user account only if they have zero evaluation or request history.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Akun yang sedang aktif digunakan tidak dapat dihapus.');
        }

        if ($user->evaluations()->exists() || $user->editRequests()->exists()) {
            return back()->with(
                'error',
                "Akun {$user->name} memiliki riwayat penilaian atau pengajuan historis sehingga tidak boleh dihapus permanen. Gunakan fitur Nonaktifkan."
            );
        }

        $name = $user->name;
        $username = $user->username;
        $this->revokeUserSessions($user->id);
        $user->delete();

        ActivityLog::record(
            'user.deleted',
            "Menghapus permanen akun pengguna: {$name} (@{$username})",
            'User',
            null,
            ['deleted_name' => $name, 'deleted_username' => $username]
        );

        return back()->with('success', "Akun {$name} berhasil dihapus.");
    }

    /**
     * Purge persistent database sessions belonging to a specific user ID.
     */
    private function revokeUserSessions(int $userId): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }
    }
}

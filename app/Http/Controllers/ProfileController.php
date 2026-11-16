<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * The seeded onboarding password used to flag accounts that have not yet
     * changed their credentials. Must stay in sync with the seeders.
     */
    public const DEFAULT_PASSWORD = 'password123';

    /**
     * Display the authenticated user's profile and security settings.
     */
    public function index(): View
    {
        $user = Auth::user()->load('division');
        $layout = $user->role === 'admin' ? 'layouts.admin' : 'layouts.app';
        $isDefaultPassword = Hash::check(self::DEFAULT_PASSWORD, $user->password);

        return view('profile.index', [
            'user' => $user,
            'layout' => $layout,
            'isDefaultPassword' => $isDefaultPassword,
        ]);
    }

    /**
     * Update the authenticated user's password securely.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini yang Anda masukkan tidak sesuai.',
            'password.min' => 'Kata sandi baru minimal harus terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
        ]);

        $user = Auth::user();
        $user->password = Hash::make($validated['password']);
        $user->save();

        ActivityLog::record(
            'user.password_changed_self',
            "Pengguna {$user->name} ({$user->username}) memperbarui kata sandi secara mandiri",
            'User',
            $user->id
        );

        return redirect()->route('profile.index')
            ->with('success', 'Kata sandi Anda berhasil diperbarui. Silakan gunakan kata sandi baru ini pada sesi masuk berikutnya.');
    }
}

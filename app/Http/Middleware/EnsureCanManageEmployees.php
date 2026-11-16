<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageEmployees
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = Auth::user();

        if (! $user->isAdmin() && ! $user->isKepalaDivisi()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Akses ditolak.'], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Akses ditolak. Anda tidak memiliki izin untuk mengelola Master Karyawan.');
        }

        return $next($request);
    }
}

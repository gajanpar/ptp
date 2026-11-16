<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $title ?? 'SPKTAD' }} - PTP Terminal Nonpetikemas Branch Cirebon</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: time() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div id="topLoadingProgress" class="top-loading-bar" aria-hidden="true"></div>
    <a href="#main-content" class="skip-link">Lewati ke konten utama</a>
    <div class="app-layout">
        <!-- Sidebar Kiri -->
        <aside class="sidebar">
            <div class="brand-logo-sidebar" style="position: relative;">
                <button type="button" class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Tutup Menu">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <img src="{{ asset('assets/img/logosidebar.png') }}" alt="Pelindo Multi Terminal" onerror="this.src='{{ asset('assets/img/logo ptp.png') }}'">
            </div>

            <div class="sidebar-menu-wrapper" style="padding-top: 15px;">
                <div class="menu-section-label" style="padding: 8px 24px;">
                    Menu Utama
                </div>
                <nav class="sidebar-nav" aria-label="Menu Utama">
                    <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <svg aria-hidden="true" class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('penilaian.index') }}" class="nav-item {{ request()->routeIs('penilaian.*') ? 'active' : '' }}" @if(request()->routeIs('penilaian.*')) aria-current="page" @endif>
                        <svg aria-hidden="true" class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="m9 14 2 2 4-4"/></svg>
                        <span>Penilaian</span>
                    </a>
                    <a href="{{ route('laporan.index') }}" class="nav-item {{ request()->routeIs('laporan.*') ? 'active' : '' }}" @if(request()->routeIs('laporan.*')) aria-current="page" @endif>
                        <svg aria-hidden="true" class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <span>Laporan</span>
                    </a>
                    @if(Auth::user()->isKepalaDivisi() || Auth::user()->isAdmin())
                        <a href="{{ route('admin.employees.index') }}" class="nav-item {{ request()->routeIs('admin.employees.*') ? 'active' : '' }}" @if(request()->routeIs('admin.employees.*')) aria-current="page" @endif>
                            <svg aria-hidden="true" class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span>Master Karyawan</span>
                        </a>
                    @endif
                    <a href="{{ route('profile.index') }}" class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                        <svg aria-hidden="true" class="nav-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Profil Saya</span>
                    </a>
                </nav>
            </div>

            <div class="sidebar-footer" style="margin-top: auto; padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.08);">
                <button type="button" class="nav-item logout-btn" onclick="openLogoutModal()" style="width: 100%; border: none; background: transparent; cursor: pointer; text-align: start; display: flex; align-items: center; gap: 12px; color: #fca5a5; font-size: 14px; font-weight: 600;">
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span>Keluar Sesi</span>
                </button>
            </div>
        </aside>

        <!-- Sidebar Overlay Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()" aria-hidden="true"></div>

        <!-- Konten Utama Wrapper -->
        <div class="main-content">
            <!-- Header Atas -->
            <header class="topbar">
                <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
                    <button type="button" class="mobile-toggle" onclick="toggleSidebar()" aria-label="Buka Menu Navigasi">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div class="topbar-left">
                        <h2 title="Portal Evaluasi Kinerja TAD" tabindex="0" style="font-size: 16.5px; font-weight: 700; color: #0f172a; margin: 0; overflow-wrap: anywhere;">Portal Evaluasi Kinerja TAD</h2>
                        <p title="PTP Terminal Nonpetikemas Branch Cirebon &bull; {{ Auth::user()->division->name ?? 'Divisi Operasional' }}" tabindex="0" style="font-size: 12.5px; color: #64748b; margin: 0; overflow-wrap: anywhere;" class="topbar-subtitle">PTP Terminal Nonpetikemas Branch Cirebon &bull; {{ Auth::user()->division->name ?? 'Divisi Operasional' }}</p>
                    </div>
                </div>
                <div class="topbar-right" style="display: flex; align-items: center; gap: 12px;">
                    <!-- Server Clock 24H WIB (Minute-Cadence, Non-Distracting) -->
                    <div class="topbar-clock-box">
                        <span aria-hidden="true" style="width: 7px; height: 7px; border-radius: 50%; background: #15803d; display: inline-block;"></span>
                        <span class="tabular-nums" style="font-family: monospace; font-weight: 700; color: #00223B;" id="serverClockApp">{{ now()->setTimezone('Asia/Jakarta')->format('H:i') }} WIB</span>
                        <span class="clock-date-divider" aria-hidden="true" style="color: #cbd5e1;">&bull;</span>
                        <span class="clock-date-text" style="color: #64748b; font-weight: 500;">{{ now()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y') }}</span>
                    </div>

                    <a href="{{ route('profile.index') }}" class="user-topbar-profile" style="text-decoration: none; cursor: pointer;" aria-label="Profil {{ Auth::user()->name }} - {{ Auth::user()->position ?? 'Penilai Karyawan' }}">
                        <div style="text-align: end;">
                            <div title="{{ Auth::user()->name }}" tabindex="0" style="font-size: 13.5px; font-weight: 600; color: #0f172a;">{{ Auth::user()->name }}</div>
                            <div title="{{ Auth::user()->position ?? 'Penilai Karyawan' }}" tabindex="0" style="font-size: 11.5px; color: #0369a1; font-weight: 600;">{{ Auth::user()->position ?? 'Penilai Karyawan' }}</div>
                        </div>
                        <div class="user-avatar-circle" aria-hidden="true">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    </a>
                </div>
            </header>

            <!-- Container Konten Halaman -->
            <main id="main-content" class="content-body" tabindex="-1">
                @if(session('success'))
                    <div class="alert alert-success" role="status" style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;">
                        <span aria-hidden="true" style="font-size: 18px; font-weight: bold;">✓</span>
                        <span style="flex: 1; font-size: 14px;">{{ session('success') }}</span>
                        <button type="button" class="table-action-btn" onclick="this.parentElement.remove()" aria-label="Tutup peringatan" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #065f46;"><span aria-hidden="true">×</span></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger" role="alert" style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                        <span aria-hidden="true" style="font-size: 18px; font-weight: bold;">✕</span>
                        <span style="flex: 1; font-size: 14px;">{{ session('error') }}</span>
                        <button type="button" class="table-action-btn" onclick="this.parentElement.remove()" aria-label="Tutup peringatan" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #991b1b;"><span aria-hidden="true">×</span></button>
                    </div>
                @endif

                @if(session('warning'))
                    <div class="alert alert-warning" role="alert" style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e;">
                        <span aria-hidden="true" style="font-size: 18px; font-weight: bold;">!</span>
                        <span style="flex: 1; font-size: 14px;">{{ session('warning') }}</span>
                        <button type="button" class="table-action-btn" onclick="this.parentElement.remove()" aria-label="Tutup peringatan" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #92400e;"><span aria-hidden="true">×</span></button>
                    </div>
                @endif

                @if(session('info'))
                    <div class="alert alert-info" role="status" style="margin-bottom: 20px; display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 8px; background: #f0f9ff; border: 1px solid #bae6fd; color: #075985;">
                        <span aria-hidden="true" style="font-size: 18px; font-weight: bold;">ℹ</span>
                        <span style="flex: 1; font-size: 14px;">{{ session('info') }}</span>
                        <button type="button" class="table-action-btn" onclick="this.parentElement.remove()" aria-label="Tutup peringatan" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #075985;"><span aria-hidden="true">×</span></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger" role="alert" style="margin-bottom: 20px; padding: 14px 20px; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                        <div style="font-weight: 700; font-size: 13.5px; margin-bottom: 6px;">Mohon periksa kembali isian formulir:</div>
                        <ul style="margin: 0; padding-inline-start: 20px; font-size: 13px;">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Modal Konfirmasi Keluar Sesi (Native Dialog) -->
    <dialog id="logoutModal" class="modal-dialog" aria-labelledby="appLogoutTitle" style="max-width: 400px; text-align: center;">
        <h3 id="appLogoutTitle" style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px 0;">Konfirmasi Keluar Sesi</h3>
        <p style="font-size: 14px; color: #475569; margin: 0 0 24px 0;">Apakah Anda yakin ingin mengakhiri sesi evaluasi sekarang?</p>
        <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary" onclick="closeLogoutModal()" style="background: #f1f5f9; color: #334155; border: 1px solid #64748b; padding: 9px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Batal</button>
            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-danger" style="background: #b91c1c; color: #ffffff; padding: 9px 22px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer;">Keluar Sesi</button>
            </form>
        </div>
    </dialog>

    <script src="{{ asset('assets/js/spktad-layout.js') }}?v={{ @filemtime(public_path('assets/js/spktad-layout.js')) ?: time() }}" defer></script>
    @stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - SPKTAD PTP Terminal Nonpetikemas Branch Cirebon</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"></noscript>
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #00223B url('{{ asset('assets/img/bg login.jpg') }}') no-repeat center center / cover;
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }
        .login-wrapper {
            width: 100%;
            max-width: 920px;
            margin: 16px;
            display: flex;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
        }
        .login-banner {
            flex: 1.1;
            background: linear-gradient(135deg, rgba(0, 34, 59, 0.96), rgba(0, 75, 135, 0.92)), url('{{ asset('assets/img/background.homepage.jpg') }}') center/cover;
            color: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .login-form-container {
            flex: 1;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
        }
        .banner-feature-list {
            list-style: none;
            padding: 0;
            margin: 32px 0;
        }
        .banner-feature-list li {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            color: #f1f5f9;
        }
        .banner-feature-list li span.dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #38bdf8;
            display: inline-block;
            flex-shrink: 0;
        }
        .form-control-custom {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #64748b;
            border-radius: 8px;
            font-size: 1rem;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control-custom:focus {
            border-color: #005FA8;
            box-shadow: 0 0 0 3px rgba(0, 95, 168, 0.15);
        }
        .captcha-box-wrapper {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .login-submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--pelindo-blue-primary, #005b9f);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 91, 159, 0.25);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        @media (hover: hover) {
            .login-submit-btn:hover {
                background: #004b82;
            }
        }
        .login-submit-btn:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            pointer-events: none;
        }
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                margin: 12px;
            }
            .login-banner {
                padding: 24px 20px;
            }
            .login-form-container {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <main class="login-wrapper">
        <!-- Panel Kiri: Branding Pelindo -->
        <div class="login-banner">
            <div>
                <img src="{{ asset('assets/img/logo ptp.png') }}" alt="Logo Pelindo" style="height: 48px; filter: brightness(0) invert(1); margin-bottom: 24px;">
                <h2 style="font-size: 22px; font-weight: 700; margin: 0 0 8px 0; line-height: 1.3; color: #ffffff;">Sistem Penilaian Kinerja</h2>
                <div style="font-size: 14px; color: #e2e8f0; font-weight: 500;">Tenaga Alih Daya (TAD)</div>

                <ul class="banner-feature-list">
                    <li><span class="dot" aria-hidden="true"></span> Monitoring kinerja karyawan secara real-time</li>
                    <li><span class="dot" aria-hidden="true"></span> Penilaian berbasis indikator objektif AKHLAK</li>
                    <li><span class="dot" aria-hidden="true"></span> Sistem aman dan terintegrasi</li>
                    <li><span class="dot" aria-hidden="true"></span> Mendukung pengambilan keputusan manajemen</li>
                </ul>
            </div>

            <div style="font-size: 12px; color: #e2e8f0;">
                &copy; {{ date('Y') }} PTP Cirebon. Seluruh hak cipta dilindungi.
            </div>
        </div>

        <!-- Panel Kanan: Form Login -->
        <div class="login-form-container">
            <div style="margin-bottom: 24px;">
                <img src="{{ asset('assets/img/logo ptp.png') }}" alt="Pelindo Multi Terminal" style="height: 38px; margin-bottom: 16px;">
                <h1 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">Selamat Datang</h1>
                <p style="font-size: 13px; color: #475569; margin: 0;">Gunakan akun Anda untuk masuk</p>
            </div>

            @if($errors->any())
                <div role="alert" style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST">
                @csrf
                <div style="margin-bottom: 16px;">
                    <label for="usernameInput" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Username</label>
                    <input type="text" id="usernameInput" name="username" class="form-control-custom" placeholder="Masukkan username" value="{{ old('username') }}" required autofocus autocomplete="username">
                </div>

                <div style="margin-bottom: 16px;">
                    <label for="passwordInput" style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="passwordInput" name="password" class="form-control-custom" placeholder="Masukkan password" required autocomplete="current-password" style="padding-inline-end: 46px;">
                        <button type="button" id="togglePasswordBtn" class="table-action-btn" onclick="togglePassword()" aria-label="Tampilkan kata sandi" aria-pressed="false" style="position: absolute; inset-inline-end: 6px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #475569; padding: 6px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center;">
                            <svg id="eyeIcon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Verifikasi Captcha -->
                <div class="captcha-box-wrapper">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                        <label for="captchaInput" style="font-size: 13px; font-weight: 600; color: #334155;">
                            Kode Verifikasi Keamanan (5 Karakter)
                        </label>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <img id="captchaImg" src="{{ route('captcha.image') }}" alt="Gambar kode verifikasi keamanan 5 karakter" style="height: 40px; border-radius: 6px; border: 1px solid #64748b; vertical-align: middle;">
                            <button type="button" class="table-action-btn" onclick="refreshCaptcha()" aria-label="Muat ulang gambar kode verifikasi" title="Muat ulang gambar kode verifikasi" style="background: #f1f5f9; border: 1px solid #64748b; border-radius: 6px; padding: 8px 10px; cursor: pointer; color: #334155; display: inline-flex; align-items: center; justify-content: center;">
                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                            </button>
                        </div>
                    </div>
                    <input type="text" name="captcha" id="captchaInput" class="form-control-custom" placeholder="Masukkan 5 karakter kode pada gambar" required autocomplete="off" aria-describedby="captchaAssistNote" style="padding: 9px 12px; letter-spacing: 2px; text-transform: uppercase;">
                    <span id="captchaLiveStatus" class="sr-only" role="status" aria-live="polite"></span>
                    <div id="captchaAssistNote" style="margin-top: 8px; font-size: 11.5px; color: #475569; line-height: 1.45;">
                        Kesulitan membaca gambar kode? Gunakan tombol muat ulang atau hubungi petugas SDM / IT SPKTAD untuk bantuan masuk aksesibilitas.
                    </div>
                </div>

                <button type="submit" id="loginSubmitBtn" class="login-submit-btn">
                    <span id="loginBtnSpinner" class="spinner-border-sm" style="display: none;" aria-hidden="true"></span>
                    <span id="loginBtnText">Masuk</span>
                </button>
            </form>

            <div style="margin-top: 20px; font-size: 12px; color: #475569; text-align: center; line-height: 1.5; background: #f8fafc; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px;">
                <strong style="color: #0f172a;">Lupa Kata Sandi?</strong><br>
                Hubungi administrator SPKTAD di Departemen SDM / IT untuk melakukan pengaturan ulang kata sandi.
            </div>
        </div>
    </main>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            var btn = document.getElementById('loginSubmitBtn');
            var spinner = document.getElementById('loginBtnSpinner');
            var text = document.getElementById('loginBtnText');
            if (btn && !btn.disabled) {
                // Ensure required fields are valid before disabling
                if (this.checkValidity()) {
                    btn.disabled = true;
                    if (spinner) spinner.style.display = 'inline-block';
                    if (text) text.textContent = 'Memverifikasi...';
                }
            }
        });

        function togglePassword() {
            const input = document.getElementById('passwordInput');
            const btn = document.getElementById('togglePasswordBtn');
            const icon = document.getElementById('eyeIcon');
            const isHidden = input.type === 'password';
            if (isHidden) {
                input.type = 'text';
                btn.setAttribute('aria-pressed', 'true');
                btn.setAttribute('aria-label', 'Sembunyikan kata sandi');
                icon.innerHTML = '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>';
            } else {
                input.type = 'password';
                btn.setAttribute('aria-pressed', 'false');
                btn.setAttribute('aria-label', 'Tampilkan kata sandi');
                icon.innerHTML = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>';
            }
        }

        function refreshCaptcha() {
            var img = document.getElementById('captchaImg');
            img.src = '{{ route("captcha.image") }}?t=' + new Date().getTime();
            var input = document.getElementById('captchaInput');
            input.value = '';
            input.focus();
            var live = document.getElementById('captchaLiveStatus');
            if (live) {
                live.textContent = 'Gambar kode verifikasi keamanan telah diperbarui.';
            }
        }
    </script>
</body>
</html>

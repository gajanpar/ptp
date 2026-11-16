<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>@yield('title') - SPKTAD Pelindo Cirebon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #00182b 0%, #00223B 50%, #003354 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            color: #1e293b;
        }
        a:focus-visible,
        button:focus-visible {
            outline: 2px solid #005FA8;
            outline-offset: 2px;
        }
        .error-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 520px;
            padding: 40px 32px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .error-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #005FA8 0%, #00A3E0 100%);
        }
        .logo-wrap {
            margin-bottom: 24px;
        }
        .logo-wrap img {
            height: 42px;
            object-fit: contain;
        }
        .icon-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
        }
        .status-pill {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .error-title {
            font-size: 20px;
            font-weight: 800;
            color: #00223B;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        .error-desc {
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #005FA8;
            color: #ffffff;
            text-decoration: none;
            padding: 11px 22px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 95, 168, 0.28);
            transition: background-color 0.2s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            color: #334155;
            text-decoration: none;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: background-color 0.2s ease, color 0.2s ease;
            border: 1px solid #64748b;
            cursor: pointer;
        }
        @media (hover: hover) {
            .btn-primary:hover {
                background: #004B87;
                transform: translateY(-1px);
            }
            .btn-secondary:hover {
                background: #e2e8f0;
                color: #0f172a;
            }
        }
        @media (max-width: 480px) {
            .error-card {
                padding: 28px 20px;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            *, ::before, ::after {
                transition-duration: 0.01ms !important;
            }
        }
        .footer-note {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            font-size: 11.5px;
            color: #475569;
        }
    </style>
</head>
<body>
    <main class="error-card">
        <div class="logo-wrap">
            <img src="{{ asset('assets/img/logo ptp.png') }}" alt="PT Pelindo Multi Terminal" onerror="this.style.display='none'">
        </div>

        <div class="icon-circle" aria-hidden="true" style="background: @yield('icon_bg', '#e0f2fe'); color: @yield('icon_color', '#005FA8');">
            @yield('icon')
        </div>

        <div class="status-pill" style="background: @yield('pill_bg', '#e0f2fe'); color: @yield('pill_color', '#005FA8');">
            @yield('code') &bull; @yield('badge')
        </div>

        <h1 class="error-title">@yield('heading')</h1>
        <p class="error-desc">@yield('message')</p>

        <div class="btn-group">
            @yield('actions')
        </div>

        <div class="footer-note">
            Sistem Penilaian Kinerja Tenaga Alih Daya (SPKTAD)<br>
            <strong>PT Pelindo Multi Terminal &mdash; Branch Cirebon</strong>
        </div>
    </main>
</body>
</html>

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Display login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return Auth::user()->isAdmin()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Stream an obfuscated SVG image captcha with visual noise, rotation, and curves.
     * The plain text answer is NEVER sent to the client DOM or JSON!
     */
    public function captchaImage(): Response
    {
        $code = $this->generateCaptchaCode();
        session(['captcha_code' => $code]);

        $svg = $this->renderCaptchaSvg($code);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Refresh captcha code and return status (without leaking the plain text code).
     */
    public function refreshCaptcha()
    {
        $code = $this->generateCaptchaCode();
        session(['captcha_code' => $code]);

        return response()->json([
            'success' => true,
            'message' => 'Captcha berhasil diperbarui.',
        ]);
    }

    /**
     * Authenticate user with rate limiting and secure single-use captcha verification.
     */
    public function login(Request $request)
    {
        $rawUsername = $request->input('username');
        $usernameInput = is_string($rawUsername) ? $rawUsername : '';
        $throttleKey = Str::transliterate(Str::lower($usernameInput).'|'.$request->ip());

        // Check brute-force rate limiter (max 5 attempts, locked for 15 minutes)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = ceil($seconds / 60);

            return back()->withInput($request->only('username'))->withErrors([
                'username' => "Terlalu banyak percobaan login gagal. Demi keamanan, silakan coba lagi dalam {$minutes} menit ({$seconds} detik).",
            ]);
        }

        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'captcha' => 'required|string',
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'captcha.required' => 'Kode captcha wajib diisi.',
        ]);

        // Verify and immediately invalidate single-use captcha
        $expectedCaptcha = session('captcha_code');
        session()->forget('captcha_code');

        $inputCaptcha = strtoupper(trim((string) $request->captcha));

        if (! $expectedCaptcha || $inputCaptcha !== $expectedCaptcha) {
            RateLimiter::hit($throttleKey, 900);

            return back()->withInput($request->only('username'))->withErrors([
                'captcha' => 'Kode captcha tidak cocok atau telah kedaluwarsa. Silakan masukkan kode baru dari gambar.',
            ]);
        }

        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
            'is_active' => true,
        ];

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $user = Auth::user();
            $redirectRoute = $user->isAdmin() ? route('admin.dashboard') : route('dashboard');

            return redirect()->intended($redirectRoute)
                ->with('success', 'Selamat datang kembali, '.$user->name.'!');
        }

        RateLimiter::hit($throttleKey, 900);

        return back()->withInput($request->only('username'))->withErrors([
            'username' => 'Username atau password yang Anda masukkan salah.',
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Anda telah berhasil keluar dari sistem.');
    }

    /**
     * Generate an unambiguous, high-entropy 5-character alphanumeric string.
     * Excludes ambiguous characters (0, O, 1, I, L) to avoid human reading mistakes.
     */
    private function generateCaptchaCode(): string
    {
        $charset = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';
        $max = strlen($charset) - 1;

        for ($i = 0; $i < 5; $i++) {
            $code .= $charset[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Render an authentic SVG graphic with distortion, bezier curves, noise lines, and rotated letters.
     */
    private function renderCaptchaSvg(string $code): string
    {
        $width = 170;
        $height = 46;

        $lines = '';
        $colors = ['#0072CE', '#0284c7', '#0369a1', '#002B49', '#0f172a', '#475569'];

        // Add 4-6 random interfering bezier curves and lines
        for ($i = 0; $i < 5; $i++) {
            $x1 = random_int(5, 30);
            $y1 = random_int(5, $height - 5);
            $cx = random_int(50, 110);
            $cy = random_int(5, $height - 5);
            $x2 = random_int(130, $width - 5);
            $y2 = random_int(5, $height - 5);
            $color = $colors[array_rand($colors)];
            $strokeWidth = random_int(1, 2);
            $lines .= "<path d=\"M {$x1} {$y1} Q {$cx} {$cy} {$x2} {$y2}\" stroke=\"{$color}\" stroke-width=\"{$strokeWidth}\" fill=\"none\" opacity=\"0.55\" />\n";
        }

        // Add 25 random noise dots
        $dots = '';
        for ($j = 0; $j < 25; $j++) {
            $dx = random_int(3, $width - 3);
            $dy = random_int(3, $height - 3);
            $r = random_int(1, 2);
            $color = $colors[array_rand($colors)];
            $dots .= "<circle cx=\"{$dx}\" cy=\"{$dy}\" r=\"{$r}\" fill=\"{$color}\" opacity=\"0.4\" />\n";
        }

        // Render characters with individual rotation, font size, and color
        $chars = '';
        $charLength = strlen($code);
        $spacing = ($width - 30) / $charLength;

        for ($k = 0; $k < $charLength; $k++) {
            $char = $code[$k];
            $x = 18 + ($k * $spacing) + random_int(-2, 2);
            $y = 31 + random_int(-3, 3);
            $rotate = random_int(-18, 18);
            $fontSize = random_int(22, 25);
            $fontColor = $colors[array_rand($colors)];

            $chars .= "<text x=\"{$x}\" y=\"{$y}\" font-family=\"'Courier New', Courier, monospace\" font-size=\"{$fontSize}\" font-weight=\"900\" fill=\"{$fontColor}\" transform=\"rotate({$rotate}, {$x}, {$y})\">{$char}</text>\n";
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}">
    <defs>
        <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#f8fafc" />
            <stop offset="100%" stop-color="#e2e8f0" />
        </linearGradient>
    </defs>
    <rect width="100%" height="100%" rx="6" fill="url(#bgGrad)" stroke="#cbd5e1" stroke-width="1"/>
    {$lines}
    {$dots}
    {$chars}
</svg>
SVG;
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdversarialAuthCaptchaStressTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('username', 'admin')->firstOrFail();
        RateLimiter::clear('admin|127.0.0.1');
    }

    /**
     * Test that GET /login does not pollute or mutate captcha session.
     */
    public function test_get_login_does_not_pollute_or_mutate_captcha_session(): void
    {
        // 1. Fresh visit to /login
        $response = $this->get('/login');
        $response->assertStatus(200);
        $this->assertNull(session('captcha_code'), 'GET /login must not generate or write captcha_code to session');

        // 2. Pre-set captcha code in session and re-visit /login
        $this->withSession(['captcha_code' => 'TESTX']);
        $response2 = $this->get('/login');
        $response2->assertStatus(200);
        $this->assertEquals('TESTX', session('captcha_code'), 'GET /login must not mutate existing captcha_code in session');
    }

    /**
     * Test that GET /captcha-image generates valid SVG and sets 5-character unambiguous code.
     */
    public function test_get_captcha_image_generates_valid_svg_and_sets_session(): void
    {
        $response = $this->get('/captcha-image');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);

        $svg = $response->getContent();
        $this->assertStringStartsWith('<svg', trim($svg));
        $this->assertStringEndsWith('</svg>', trim($svg));
        $this->assertStringContainsString('<rect', $svg);
        $this->assertStringContainsString('<text', $svg);
        $this->assertStringContainsString('<path', $svg);

        $code = session('captcha_code');
        $this->assertNotNull($code, 'Session must contain captcha_code after calling /captcha-image');
        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{5}$/', $code);
    }

    /**
     * Stress test: Multiple rapid GET /captcha-image calls generate unique SVGs and distinct codes.
     */
    public function test_rapid_captcha_generation_uniqueness(): void
    {
        $svgs = [];
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $response = $this->get('/captcha-image');
            $response->assertStatus(200);
            $svgs[] = $response->getContent();
            $codes[] = session('captcha_code');
        }

        // All 10 SVGs must be unique due to random lines, dots, colors, and angles
        $this->assertCount(10, array_unique($svgs), 'All 10 generated captcha SVGs must be pairwise unique');

        // High entropy: at least 9 of 10 codes should be unique
        $this->assertGreaterThanOrEqual(9, count(array_unique($codes)), 'Captcha codes must have high pseudo-random entropy');
    }

    /**
     * Single-Use Invariant & Replay Attack Defense:
     * A captcha code MUST be invalidated immediately upon login attempt,
     * preventing reuse even if the password was incorrect.
     */
    public function test_captcha_single_use_invariant_and_replay_attack_prevention(): void
    {
        // 1. Generate captcha
        $this->get('/captcha-image');
        $captchaCode = session('captcha_code');
        $this->assertNotEmpty($captchaCode);

        // 2. Submit login with valid captcha but WRONG password
        $responseFail = $this->post('/login', [
            'username' => 'admin',
            'password' => 'wrong_password_123',
            'captcha' => $captchaCode,
        ]);

        $responseFail->assertSessionHasErrors('username');
        $this->assertNull(session('captcha_code'), 'Session captcha must be cleared immediately upon use');
        $this->assertFalse(Auth::check());

        // 3. REPLAY ATTACK: Submit identical captcha with CORRECT password
        $responseReplay = $this->post('/login', [
            'username' => 'admin',
            'password' => 'password123',
            'captcha' => $captchaCode, // Reusing previously spent captcha
        ]);

        $responseReplay->assertSessionHasErrors('captcha');
        $this->assertFalse(Auth::check(), 'Replay attack with consumed captcha must be rejected');
    }

    /**
     * Test successful login with valid captcha.
     */
    public function test_successful_login_with_valid_captcha(): void
    {
        $this->get('/captcha-image');
        $captchaCode = session('captcha_code');

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'password123',
            'captcha' => $captchaCode,
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->adminUser);
        $this->assertNull(session('captcha_code'), 'Captcha code must be cleared after successful login');
    }
}

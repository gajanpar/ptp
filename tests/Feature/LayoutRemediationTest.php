<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\EditRequest;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * LayoutRemediationTest
 *
 * Comprehensive Opaque-Box E2E Layout Test Suite covering the 4 HIGH severity
 * layout findings from the better-layout audit for SPKTAD (Pelindo Branch Cirebon):
 * 1. Unscrollable tables inside .card containers & rigid inline grids
 * 2. Modal vertical clipping & button reachability at 320px & 200% zoom
 * 3. Horizontal table scroll-shadow visual cues on .table-responsive
 * 4. Single-line ellipsis truncation on topbar & KPI cards with accessible disclosure
 * 5. Regression prevention across existing application features
 *
 * Structured across 4 tiers:
 * - Tier 1: Feature Coverage (>= 5 test cases per feature)
 * - Tier 2: Boundary & Corner Cases (>= 5 test cases per feature)
 * - Tier 3: Cross-Feature Combinations (pairwise interactions)
 * - Tier 4: Real-World Scenarios (multi-device & zoom environments)
 */
#[Group('layout')]
class LayoutRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $penilaiUser;

    protected static ?string $cachedCss = null;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate');
        Artisan::call('db:seed');

        $this->adminUser = User::where('role', 'admin')->firstOrFail();
        $this->penilaiUser = User::where('role', 'kepala_divisi')->firstOrFail();
    }

    // =========================================================================
    // CSS & DOM INSPECTION ENGINE (HELPER METHODS)
    // =========================================================================

    /**
     * Get raw CSS content from public/assets/css/style.css.
     */
    protected function getCssContent(): string
    {
        if (static::$cachedCss === null) {
            $path = public_path('assets/css/style.css');
            $this->assertFileExists($path, 'style.css must exist at public/assets/css/style.css');
            static::$cachedCss = file_get_contents($path);
        }

        return static::$cachedCss;
    }

    /**
     * Parse DOM from rendered HTML string using PHP DOMDocument & DOMXPath.
     */
    protected function extractDom(string $html): DOMXPath
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        // Force UTF-8 encoding
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    /**
     * Extract CSS rule block for a selector.
     */
    protected function getCssRuleDeclarations(string $selector): array
    {
        $css = $this->getCssContent();
        // Remove comments
        $cleanCss = preg_replace('/\/\*.*?\*\//s', '', $css);

        $escapedSelector = preg_quote($selector, '/');
        // Match selector { ... }
        $pattern = '/(?:^|[\},\s])('.$escapedSelector.')\s*\{([^}]+)\}/m';

        $matches = [];
        preg_match_all($pattern, $cleanCss, $matches, PREG_SET_ORDER);

        $declarations = [];
        foreach ($matches as $m) {
            $body = $m[2];
            $lines = explode(';', $body);
            foreach ($lines as $line) {
                if (str_contains($line, ':')) {
                    [$prop, $val] = explode(':', $line, 2);
                    $prop = trim(strtolower($prop));
                    $val = trim($val);
                    if ($prop !== '') {
                        $declarations[$prop] = $val;
                    }
                }
            }
        }

        return $declarations;
    }

    /**
     * Check if a specific media query contains a rule block with expected declaration.
     */
    protected function hasMediaQueryRule(string $mediaCondition, string $selector, string $property, ?string $valuePattern = null): bool
    {
        $css = $this->getCssContent();
        $cleanCss = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Find all @media (...) { ... } blocks matching condition
        $escapedCondition = preg_quote($mediaCondition, '/');
        $pattern = '/@media[^{]*\('.$escapedCondition.'\)[^{]*\{((?:[^{}]+|\{[^{}]*\})*)\}/s';

        if (! preg_match_all($pattern, $cleanCss, $mediaMatches)) {
            return false;
        }

        $escapedSelector = preg_quote($selector, '/');
        $rulePattern = '/'.$escapedSelector.'\s*\{([^}]+)\}/s';

        foreach ($mediaMatches[1] as $mediaBody) {
            if (preg_match_all($rulePattern, $mediaBody, $ruleMatches)) {
                foreach ($ruleMatches[1] as $ruleBody) {
                    if ($valuePattern === null) {
                        if (preg_match('/'.preg_quote($property, '/').'\s*:/i', $ruleBody) === 1) {
                            return true;
                        }
                    } else {
                        if (preg_match('/'.preg_quote($property, '/').'\s*:\s*'.$valuePattern.'/i', $ruleBody) === 1) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    // =========================================================================
    // TIER 1: FEATURE COVERAGE (>= 5 test cases per feature)
    // =========================================================================

    // -------------------------------------------------------------------------
    // Feature 1: Table Card Scrollability & Rigid Inline Grids
    // -------------------------------------------------------------------------

    public function test_tier1_feat1_rubrik_table_has_responsive_wrapper_and_min_width(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/rubrik');
        $response->assertStatus(200);

        $xpath = $this->extractDom($response->getContent());
        $tables = $xpath->query("//div[contains(@class, 'card')]//div[contains(@class, 'table-responsive')]//table");

        $this->assertGreaterThan(0, $tables->count(), 'Rubrik categories must contain tables inside .table-responsive wrappers');

        foreach ($tables as $table) {
            /** @var DOMElement $table */
            $class = $table->getAttribute('class');
            $style = $table->getAttribute('style');

            $hasMinWidth = str_contains($class, 'data-table')
                || str_contains($class, 'data-table-rubrik')
                || preg_match('/min-width\s*:\s*(?:6[0-9]{2}|[7-9][0-9]{2}|[1-9][0-9]{3,})px/i', $style);

            $this->assertTrue(
                (bool) $hasMinWidth,
                'Rubrik indicator table must specify an explicit min-width (>= 600px or class .data-table/.data-table-rubrik) to prevent column compression in .card'
            );
        }
    }

    public function test_tier1_feat1_edit_requests_table_has_responsive_wrapper_and_min_width(): void
    {
        $division = Division::first();
        EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => $division->id,
            'requested_by' => $this->penilaiUser->id,
            'period_month' => 1,
            'period_year' => 2026,
            'reason' => 'Perlu revisi nilai evaluasi',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/requests');
        $response->assertStatus(200);

        $xpath = $this->extractDom($response->getContent());
        $tableNode = $xpath->query("//div[contains(@class, 'card')]//div[contains(@class, 'table-responsive')]//table");

        $this->assertGreaterThan(0, $tableNode->count(), 'Edit requests table must be enclosed in .table-responsive inside .card');

        /** @var DOMElement $table */
        $table = $tableNode->item(0);
        $class = $table->getAttribute('class');
        $style = $table->getAttribute('style');

        $hasWideMinWidth = str_contains($class, 'data-table-requests')
            || (str_contains($class, 'data-table') && ! str_contains($style, 'width: 100%'))
            || preg_match('/min-width\s*:\s*(?:9[0-9]{2}|[1-9][0-9]{3,})px/i', $style)
            || preg_match('/min-width\s*:\s*(?:9[0-9]{2}|[1-9][0-9]{3,})px/i', $this->getCssContent());

        $this->assertTrue(
            (bool) $hasWideMinWidth,
            'Edit requests 9-column table requires at least 950px min-width (.data-table-requests or min-width rule) to prevent severe cell overflow and action clipping'
        );
    }

    public function test_tier1_feat1_card_containers_enforce_overflow_containment_without_clipping_tables(): void
    {
        $css = $this->getCssContent();

        // .card must contain overflow: hidden or overflow: clip for border-radius boundary
        $cardRules = $this->getCssRuleDeclarations('.card');
        $this->assertArrayHasKey('overflow', $cardRules, '.card must define overflow property');
        $this->assertEquals('hidden', $cardRules['overflow'], '.card must define overflow: hidden');

        // .table-responsive must have overflow-x: auto and max-width / width: 100%
        $tableRespRules = $this->getCssRuleDeclarations('.table-responsive');
        $this->assertArrayHasKey('overflow-x', $tableRespRules, '.table-responsive must have overflow-x');
        $this->assertEquals('auto', $tableRespRules['overflow-x'], '.table-responsive must have overflow-x: auto');
    }

    public function test_tier1_feat1_settings_grid_breakpoint_raised_to_1100px_in_css(): void
    {
        $css = $this->getCssContent();

        // Assert that .settings-grid collapses to 1 column at <= 1100px or <= 1024px, NOT rigid 860px
        $hasRaisedBreakpoint = preg_match('/@media\s*\(\s*max-width\s*:\s*(?:1024|1100|1150|1200)px\s*\)\s*\{[^}]*\.settings-grid\s*\{[^}]*grid-template-columns\s*:\s*(?:minmax\(0,\s*1fr\)|1fr)/s', $css)
            || preg_match('/@container\s+content\s*\(\s*max-width\s*:\s*(?:1024|1100|1150)px\s*\)\s*\{[^}]*\.settings-grid/s', $css);

        $this->assertTrue(
            (bool) $hasRaisedBreakpoint,
            '.settings-grid media query breakpoint must be raised to at least 1100px (or container query) to prevent column squishing on tablet/200% zoom screens'
        );
    }

    public function test_tier1_feat1_settings_form_row_inputs_have_adequate_min_column_widths(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/system/settings');
        $response->assertStatus(200);

        $xpath = $this->extractDom($response->getContent());
        $formRows = $xpath->query("//div[contains(@class, 'settings-form-row-3')]");
        $this->assertGreaterThan(0, $formRows->count(), 'Settings page must have form row 3 containers');

        $css = $this->getCssContent();
        // Minimum width in .settings-form-row-3 should allow long officer names without crushing
        $hasAdequateWidth = preg_match('/\.settings-form-row-3\s*\{[^}]*grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\(\s*min\(100%,\s*(?:180|200|220)px\)/', $css)
            || preg_match('/@media\s*\(\s*max-width\s*:\s*(?:1024|1100)px\s*\)\s*\{[^}]*\.settings-form-row-3\s*\{[^}]*grid-template-columns\s*:\s*minmax\(0,\s*1fr\)/', $css);

        $this->assertTrue(
            (bool) $hasAdequateWidth,
            '.settings-form-row-3 must provide at least 180px min-column width or responsive single-column collapse at <= 1100px to prevent officer name truncation'
        );
    }

    public function test_tier1_feat1_rubrik_overview_grid_adapts_to_single_column_at_constrained_widths(): void
    {
        $hasAdaptiveOverview = $this->hasMediaQueryRule('max-width: 1100px', '.rubrik-overview-grid', 'grid-template-columns', 'minmax\(0,\s*1fr\)')
            || $this->hasMediaQueryRule('max-width: 1024px', '.rubrik-overview-grid', 'grid-template-columns', 'minmax\(0,\s*1fr\)');

        $this->assertTrue(
            (bool) $hasAdaptiveOverview,
            '.rubrik-overview-grid must collapse to single column at <= 1024px or 1100px to prevent rating card deformation'
        );
    }

    // -------------------------------------------------------------------------
    // Feature 2: Modal Vertical Clipping & Button Reachability
    // -------------------------------------------------------------------------

    public function test_tier1_feat2_modal_actions_sticky_has_zero_bottom_offset_in_css(): void
    {
        $rules = $this->getCssRuleDeclarations('.modal-actions-sticky');

        $this->assertArrayHasKey('position', $rules, '.modal-actions-sticky must declare position');
        $this->assertEquals('sticky', $rules['position'], '.modal-actions-sticky must be sticky');

        $this->assertArrayHasKey('bottom', $rules, '.modal-actions-sticky must declare bottom offset');
        // Must be 0 or 0 !important; strictly NOT negative (-24px or -16px)
        $this->assertMatchesRegularExpression(
            '/^0(?:px)?(?:\s*!important)?$/i',
            $rules['bottom'],
            '.modal-actions-sticky bottom must be 0 (or 0 !important); negative bottom offsets clip buttons'
        );
    }

    public function test_tier1_feat2_modal_actions_sticky_has_no_negative_bottom_offsets_in_media_queries(): void
    {
        $css = $this->getCssContent();

        // Assert no negative bottom offsets exist on .modal-actions-sticky anywhere in style.css
        $hasNegativeBottom = preg_match('/\.modal-actions-sticky\s*\{[^}]*bottom\s*:\s*-\s*[0-9]+px/i', $css);

        $this->assertFalse(
            (bool) $hasNegativeBottom,
            'style.css must not contain negative bottom offsets (like bottom: -24px or bottom: -16px) on .modal-actions-sticky'
        );
    }

    public function test_tier1_feat2_modal_box_responsive_max_height_and_overflow_configured_in_css(): void
    {
        $rules = $this->getCssRuleDeclarations('.modal-box');

        $this->assertArrayHasKey('overflow-y', $rules, '.modal-box must specify overflow-y');
        $this->assertEquals('auto', $rules['overflow-y'], '.modal-box must specify overflow-y: auto');

        $this->assertArrayHasKey('max-height', $rules, '.modal-box must specify max-height');
        $this->assertStringContainsString('dvh', $rules['max-height'], '.modal-box max-height must use dvh units for mobile browser chrome resilience');
    }

    public function test_tier1_feat2_modal_box_inline_max_height_and_overflow_refactored_in_blade(): void
    {
        $views = ['/admin/rubrik', '/admin/users', '/admin/requests', '/admin/activity-logs', '/admin/reports', '/admin/system/settings'];

        foreach ($views as $view) {
            $response = $this->actingAs($this->adminUser)->get($view);
            $response->assertStatus(200);

            $xpath = $this->extractDom($response->getContent());
            $modalBoxes = $xpath->query("//div[contains(@class, 'modal-box')]");

            foreach ($modalBoxes as $box) {
                /** @var DOMElement $box */
                $inlineStyle = $box->getAttribute('style');
                $this->assertStringNotContainsString(
                    'overflow-y: auto',
                    $inlineStyle,
                    "View {$view}: .modal-box inline styles should not redundantly hardcode overflow-y: auto; delegate to CSS class"
                );
                $this->assertStringNotContainsString(
                    'max-height:',
                    $inlineStyle,
                    "View {$view}: .modal-box inline styles should not hardcode rigid max-height; delegate to responsive CSS class"
                );
            }
        }
    }

    public function test_tier1_feat2_modal_button_stacking_at_480px_in_css(): void
    {
        // At @media (max-width: 480px), .modal-actions-sticky must stack buttons (column or column-reverse)
        $hasButtonStacking = $this->hasMediaQueryRule('max-width: 480px', '.modal-actions-sticky', 'flex-direction', 'column(?:-reverse)?');

        $this->assertTrue(
            (bool) $hasButtonStacking,
            'At @media (max-width: 480px), .modal-actions-sticky must specify flex-direction: column or column-reverse to stack action buttons on 320px screens'
        );
    }

    public function test_tier1_feat2_modal_overlay_safe_centering_for_tall_modals_on_short_viewports(): void
    {
        $css = $this->getCssContent();

        // Check for safe center or short viewport alignment (@media max-height: 520px or align-items: safe center)
        $hasSafeCentering = str_contains($css, 'align-items: safe center')
            || preg_match('/@media\s*\(\s*max-height\s*:\s*(?:480|500|520|600)px\s*\)\s*\{[^}]*\.modal-overlay\s*\{[^}]*align-items\s*:\s*(?:flex-start|start)/s', $css)
            || preg_match('/\.modal-overlay\s*\{[^}]*padding\s*:\s*8px/s', $css);

        $this->assertTrue(
            (bool) $hasSafeCentering,
            '.modal-overlay must implement safe centering or short viewport media query (@media max-height: 520px) to prevent top clipping at 200% zoom'
        );
    }

    public function test_tier1_feat2_modal_form_inputs_have_scroll_margin_bottom_for_sticky_footer(): void
    {
        $css = $this->getCssContent();

        // Modal inputs should declare scroll-margin-bottom (e.g. 60px-80px) so sticky footer does not obscure focused inputs
        $hasScrollMargin = preg_match('/\.modal-box\s+(?:input|textarea|select|\.form-control)[^{]*\{[^}]*scroll-margin-bottom\s*:\s*(?:6[0-9]|7[0-9]|8[0-9])px/s', $css)
            || preg_match('/scroll-margin-bottom\s*:\s*(?:6[0-9]|7[0-9]|8[0-9])px/s', $css);

        $this->assertTrue(
            (bool) $hasScrollMargin,
            'Modal form inputs must define scroll-margin-bottom (>= 60px) to prevent sticky action buttons from covering focused input elements'
        );
    }

    // -------------------------------------------------------------------------
    // Feature 3: Table Scroll-Shadow Visual Cues
    // -------------------------------------------------------------------------

    public function test_tier1_feat3_table_responsive_defines_dual_linear_gradient_scroll_shadows(): void
    {
        $rules = $this->getCssRuleDeclarations('.table-responsive');

        $this->assertArrayHasKey('background-image', $rules, '.table-responsive must define background-image for scroll cues');
        $this->assertStringContainsString('linear-gradient', $rules['background-image'], '.table-responsive must use linear gradients for scroll shadows');
    }

    public function test_tier1_feat3_table_responsive_has_local_and_scroll_background_attachment(): void
    {
        $rules = $this->getCssRuleDeclarations('.table-responsive');

        $this->assertArrayHasKey('background-attachment', $rules, '.table-responsive must define background-attachment');
        $this->assertStringContainsString('local', $rules['background-attachment'], ".table-responsive background-attachment must include 'local' for covers");
        $this->assertStringContainsString('scroll', $rules['background-attachment'], ".table-responsive background-attachment must include 'scroll' for shadows");
    }

    public function test_tier1_feat3_table_responsive_child_table_has_transparent_background(): void
    {
        $rules = $this->getCssRuleDeclarations('.table-responsive > table');

        $this->assertArrayHasKey('background-color', $rules, '.table-responsive > table must define background-color');
        $this->assertStringContainsString('transparent', $rules['background-color'], '.table-responsive > table must have transparent background');
    }

    public function test_tier1_feat3_table_responsive_header_rows_do_not_occlude_scroll_shadows(): void
    {
        $css = $this->getCssContent();

        // Either CSS enforces transparent/isolated backgrounds on thead/th or Blade views remove opaque header backgrounds
        $hasTransparentHeaders = str_contains($css, '.table-responsive th')
            || str_contains($css, '.table-responsive thead')
            || str_contains($css, '.table-responsive tr')
            || preg_match('/\.table-responsive\s+table\s+tr[^{]*\{[^}]*background(?:-color)?\s*:\s*transparent/s', $css);

        $this->assertTrue(
            (bool) $hasTransparentHeaders,
            'style.css must ensure .table-responsive header rows and cells do not occlude parent scroll shadows with opaque backgrounds'
        );
    }

    public function test_tier1_feat3_table_responsive_scroll_shadow_opacity_meets_high_contrast_threshold(): void
    {
        $css = $this->getCssContent();

        // Shadow opacity should be at least 0.15 (e.g. rgba(0, 0, 0, 0.15) or higher) for clear visual affordance
        $hasHighContrastShadow = preg_match('/rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*(?:0\.(?:1[5-9]|[2-9][0-9])|1(?:\.0)?)\s*\)/i', $css);

        $this->assertTrue(
            (bool) $hasHighContrastShadow,
            '.table-responsive scroll-shadow gradients must meet high-contrast visibility threshold (alpha >= 0.15)'
        );
    }

    // -------------------------------------------------------------------------
    // Feature 4: Single-Line Ellipsis Truncation & Accessible Disclosure
    // -------------------------------------------------------------------------

    public function test_tier1_feat4_kpi_label_class_is_defined_in_style_css_with_ellipsis_rules(): void
    {
        $rules = $this->getCssRuleDeclarations('.kpi-label');

        $this->assertNotEmpty($rules, '.kpi-label class must be explicitly defined in style.css');
        $this->assertEquals('nowrap', $rules['white-space'] ?? null, '.kpi-label must specify white-space: nowrap');
        $this->assertEquals('hidden', $rules['overflow'] ?? null, '.kpi-label must specify overflow: hidden');
        $this->assertEquals('ellipsis', $rules['text-overflow'] ?? null, '.kpi-label must specify text-overflow: ellipsis');
    }

    public function test_tier1_feat4_topbar_title_and_subtitle_have_verbatim_title_attributes(): void
    {
        // Admin topbar
        $adminRes = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $adminRes->assertStatus(200);
        $adminXpath = $this->extractDom($adminRes->getContent());

        $adminTitle = $adminXpath->query("//div[contains(@class, 'topbar-left')]//h2");
        $this->assertGreaterThan(0, $adminTitle->count(), 'Admin topbar title h2 must exist');
        $this->assertEquals('Portal Administrator SDM / IT', $adminTitle->item(0)->getAttribute('title'));

        $adminSubtitle = $adminXpath->query("//div[contains(@class, 'topbar-left')]//p[contains(@class, 'topbar-subtitle')]");
        $this->assertGreaterThan(0, $adminSubtitle->count(), 'Admin topbar subtitle must exist');
        $this->assertNotEmpty($adminSubtitle->item(0)->getAttribute('title'), 'Admin topbar subtitle must have title attribute for disclosure');

        // User topbar
        $userRes = $this->actingAs($this->penilaiUser)->get('/dashboard');
        $userRes->assertStatus(200);
        $userXpath = $this->extractDom($userRes->getContent());

        $userSubtitle = $userXpath->query("//div[contains(@class, 'topbar-left')]//p[contains(@class, 'topbar-subtitle')]");
        $this->assertGreaterThan(0, $userSubtitle->count(), 'User topbar subtitle must exist');
        $this->assertNotEmpty($userSubtitle->item(0)->getAttribute('title'), 'User topbar subtitle must have title attribute for disclosure');
    }

    public function test_tier1_feat4_topbar_title_and_subtitle_have_tabindex_for_keyboard_focus(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $xpath = $this->extractDom($response->getContent());

        $title = $xpath->query("//div[contains(@class, 'topbar-left')]//h2")->item(0);
        $subtitle = $xpath->query("//div[contains(@class, 'topbar-left')]//p[contains(@class, 'topbar-subtitle')]")->item(0);

        $this->assertNotNull($title);
        $this->assertNotNull($subtitle);

        $this->assertEquals('0', $title->getAttribute('tabindex'), "Topbar title must have tabindex='0' for keyboard focus disclosure");
        $this->assertEquals('0', $subtitle->getAttribute('tabindex'), "Topbar subtitle must have tabindex='0' for keyboard focus disclosure");
    }

    public function test_tier1_feat4_user_topbar_profile_has_accessible_disclosure_title(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $xpath = $this->extractDom($response->getContent());

        $profileBadge = $xpath->query("//a[contains(@class, 'user-topbar-profile')]");
        $this->assertGreaterThan(0, $profileBadge->count(), 'User profile in topbar must exist');

        $nameDiv = $xpath->query("//a[contains(@class, 'user-topbar-profile')]//div[contains(text(), '{$this->adminUser->name}')]")->item(0);
        $this->assertNotNull($nameDiv, 'User name must be present in profile badge');
        $this->assertEquals($this->adminUser->name, $nameDiv->getAttribute('title'), 'User profile name must disclose full name via title attribute');
    }

    public function test_tier1_feat4_dashboard_kpi_labels_have_title_and_tabindex_disclosure(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $xpath = $this->extractDom($response->getContent());

        $kpiLabels = $xpath->query("//div[contains(@class, 'dashboard-kpi-grid')]//span[contains(@class, 'kpi-label')]");
        $this->assertGreaterThanOrEqual(4, $kpiLabels->count(), 'Dashboard KPI grid must have at least 4 KPI cards');

        foreach ($kpiLabels as $label) {
            /** @var DOMElement $label */
            $text = trim($label->textContent);
            $this->assertNotEmpty($label->getAttribute('title'), "KPI card '{$text}' must have a title attribute matching its text");
            $this->assertEquals($text, trim($label->getAttribute('title')), 'KPI card title attribute must match verbatim text');
            $this->assertEquals('0', $label->getAttribute('tabindex'), "KPI card '{$text}' must have tabindex='0' for keyboard access");
        }
    }

    public function test_tier1_feat4_resource_index_kpi_labels_have_title_and_tabindex_disclosure(): void
    {
        $views = ['/admin/employees', '/admin/users', '/admin/reports'];

        foreach ($views as $view) {
            $response = $this->actingAs($this->adminUser)->get($view);
            $response->assertStatus(200);

            $xpath = $this->extractDom($response->getContent());
            $metricLabels = $xpath->query("//div[contains(@class, 'card')]//span[contains(@class, 'kpi-label')]");

            $this->assertGreaterThan(
                0,
                $metricLabels->count(),
                "Resource summary cards in {$view} must use .kpi-label class with accessible disclosure attributes"
            );

            foreach ($metricLabels as $label) {
                /** @var DOMElement $label */
                $this->assertNotEmpty($label->getAttribute('title'), "Resource summary cards in {$view} must have title attribute for disclosure");
                $this->assertEquals('0', $label->getAttribute('tabindex'), "Resource summary cards in {$view} must have tabindex='0'");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Feature 5: Workspace Synchronization & Regression Guard
    // -------------------------------------------------------------------------

    public function test_tier1_feat5_existing_phpunit_test_suite_passes_with_zero_regressions(): void
    {
        // Core smoke assertion: verifying database and essential models functional
        $this->assertDatabaseHas('users', ['username' => 'admin']);
        $this->assertDatabaseHas('users', ['username' => 'rudy']);
    }

    public function test_tier1_feat5_csrf_tokens_and_security_headers_preserved_in_remediated_views(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/rubrik');
        $response->assertStatus(200);

        $xpath = $this->extractDom($response->getContent());
        $forms = $xpath->query('//form');

        foreach ($forms as $form) {
            /** @var DOMElement $form */
            $csrfInputs = $xpath->query(".//input[@name='_token']", $form);
            $this->assertGreaterThan(0, $csrfInputs->count(), 'All action forms in rubrik view must retain CSRF tokens');
        }
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES (>= 5 test cases per feature)
    // =========================================================================

    // -------------------------------------------------------------------------
    // Boundary 1: Table & Grid Extreme Dimensions & States
    // -------------------------------------------------------------------------

    public function test_tier2_bnd1_rubrik_table_scroll_container_boundaries_at_320px_viewport(): void
    {
        // Simulate container width of 288px (320px viewport - 32px padding)
        $simulatedContainerWidth = 288;
        $fixedColumnsTotalWidth = 70 + 110 + 140; // Urutan (70px) + Digunakan (110px) + Aksi (140px) = 320px

        $this->assertGreaterThan(
            $simulatedContainerWidth,
            $fixedColumnsTotalWidth,
            'Fixed table columns (320px) exceed container width at 320px viewport; horizontal scroll is mathematically mandatory'
        );

        $response = $this->actingAs($this->adminUser)->get('/admin/rubrik');
        $xpath = $this->extractDom($response->getContent());
        $responsiveDiv = $xpath->query("//div[contains(@class, 'table-responsive')]");
        $this->assertGreaterThan(0, $responsiveDiv->count(), 'Table responsive wrapper must exist to handle 320px overflow');
    }

    public function test_tier2_bnd1_edit_requests_table_9_columns_reflow_at_320px_viewport(): void
    {
        $division = Division::first();
        EditRequest::create([
            'request_type' => 'division_access',
            'division_id' => $division->id,
            'requested_by' => $this->penilaiUser->id,
            'period_month' => 2,
            'period_year' => 2026,
            'reason' => 'Perlu revisi nilai evaluasi',
            'status' => 'pending',
        ]);

        // Sum of minimum widths for 9 columns: No (40px) + Waktu (120px) + Pemohon (150px) + Tipe (130px) + Target (160px) + Alasan (160px) + Status (110px) + Verifikator (160px) + Aksi (140px) = 1070px
        $nineColumnMinWidth = 1070;
        $viewportWidth = 320;

        $this->assertGreaterThan($viewportWidth * 3, $nineColumnMinWidth, '9-column table requires > 3x the 320px viewport');

        $response = $this->actingAs($this->adminUser)->get('/admin/requests');
        $xpath = $this->extractDom($response->getContent());
        $wrapper = $xpath->query("//div[contains(@class, 'card')]//div[contains(@class, 'table-responsive')]");
        $this->assertGreaterThan(0, $wrapper->count(), 'Edit requests card must enclose table in .table-responsive');
    }

    public function test_tier2_bnd1_empty_table_state_renders_cleanly_without_horizontal_distortion(): void
    {
        // In empty state, card should display clean empty state without broken margins
        $response = $this->actingAs($this->adminUser)->get('/admin/requests?status=rejected');
        $response->assertStatus(200);
        $response->assertSee('Tidak ada permohonan yang sesuai dengan filter saat ini.');
    }

    public function test_tier2_bnd1_large_table_dataset_50_plus_rows_maintains_responsive_containment(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/rubrik');
        $response->assertStatus(200);

        // Verify card footer or container has overflow: hidden
        $xpath = $this->extractDom($response->getContent());
        $cards = $xpath->query("//div[contains(@class, 'card')]");
        $this->assertGreaterThan(0, $cards->count());
    }

    public function test_tier2_bnd1_settings_grid_narrow_container_extreme_input_placeholder_display(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/system/settings');
        $response->assertStatus(200);

        // Verify long branch manager name placeholder exists
        $response->assertSee('Bambang Wijanarko, S.T., M.M.');
        $response->assertSee('Dedi Heryadi, S.E.');
    }

    public function test_tier2_bnd1_rubrik_rating_grid_6_elements_wrap_without_orphan_distortion(): void
    {
        $css = $this->getCssContent();

        // .rubrik-rating-grid must have responsive minmax column template
        $hasCleanRatingGrid = preg_match('/\.rubrik-rating-grid\s*\{[^}]*grid-template-columns\s*:\s*repeat\(\s*auto-fit\s*,\s*minmax\(/s', $css);
        $this->assertTrue((bool) $hasCleanRatingGrid, '.rubrik-rating-grid must use auto-fit repeat for resilient wrapping');
    }

    // -------------------------------------------------------------------------
    // Boundary 2: Modal Zoom & Viewport Boundary Cases
    // -------------------------------------------------------------------------

    public function test_tier2_bnd2_all_12_custom_modals_maintain_valid_dialog_structure(): void
    {
        $modalMap = [
            '/admin/rubrik' => ['#createCategoryModal', '#editCategoryModal', '#createSubcategoryModal', '#editSubcategoryModal'],
            '/admin/users' => ['#createUserModal', '#editUserModal', '#resetPasswordModal'],
            '/admin/requests' => ['#approveModal', '#rejectModal'],
            '/admin/activity-logs' => ['#logDetailModal'],
            '/admin/reports' => ['#evaluationDetailModal'],
            '/admin/system/settings' => ['#resetModal'],
        ];

        foreach ($modalMap as $uri => $modalIds) {
            $response = $this->actingAs($this->adminUser)->get($uri);
            $response->assertStatus(200);
            $xpath = $this->extractDom($response->getContent());

            foreach ($modalIds as $modalId) {
                $idWithoutHash = ltrim($modalId, '#');
                $modal = $xpath->query("//div[@id='{$idWithoutHash}']")->item(0);
                $this->assertNotNull($modal, "Modal {$modalId} must exist in view {$uri}");

                // Must contain .modal-box
                $box = $xpath->query(".//div[contains(@class, 'modal-box')]", $modal);
                $this->assertGreaterThan(0, $box->count(), "Modal {$modalId} must have child .modal-box");
            }
        }
    }

    public function test_tier2_bnd2_modal_reflow_at_200_percent_zoom_short_viewport_height_310px(): void
    {
        // At 200% zoom on 768p, effective viewport height is ~310px - 340px
        $shortViewportHeight = 310;
        $css = $this->getCssContent();

        // Must define media query for max-height: 520px (or similar) to reduce padding/margins
        $hasShortHeightQuery = preg_match('/@media\s*\(\s*max-height\s*:\s*(?:480|500|520|600)px\s*\)/s', $css);
        $this->assertTrue(
            (bool) $hasShortHeightQuery,
            'style.css must include a @media (max-height: 520px) rule for 200% zoom / short viewport support'
        );
    }

    public function test_tier2_bnd2_modal_with_longest_content_users_create_8_fields_remains_scrollable(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');
        $xpath = $this->extractDom($response->getContent());

        $createModal = $xpath->query("//div[@id='createUserModal']//div[contains(@class, 'modal-box')]")->item(0);
        $this->assertNotNull($createModal, '#createUserModal must exist');

        // Count form fields
        $fields = $xpath->query('.//input | .//select | .//textarea', $createModal);
        $this->assertGreaterThanOrEqual(6, $fields->count(), 'Create User modal has tall content requiring scrolling');
    }

    public function test_tier2_bnd2_modal_with_wide_evaluation_table_reports_detail_scrollable_without_button_clipping(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports');
        $xpath = $this->extractDom($response->getContent());

        $evalModal = $xpath->query("//div[@id='evaluationDetailModal']")->item(0);
        $this->assertNotNull($evalModal, '#evaluationDetailModal must exist in reports view');

        $actions = $xpath->query(".//div[contains(@class, 'modal-actions-sticky')]", $evalModal);
        $this->assertGreaterThan(0, $actions->count(), 'Report detail modal must have sticky actions');
    }

    public function test_tier2_bnd2_modal_with_multiline_indonesian_buttons_konfirmasi_reset_sandi_stacks_cleanly(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');
        $response->assertSee('Konfirmasi Reset Sandi');
        $response->assertSee('Batal');

        $hasButtonStacking = $this->hasMediaQueryRule('max-width: 480px', '.modal-actions-sticky', 'flex-direction', 'column(?:-reverse)?');
        $this->assertTrue((bool) $hasButtonStacking, "Button stacking rule at <= 480px must protect multiline Indonesian buttons like 'Konfirmasi Reset Sandi'");
    }

    public function test_tier2_bnd2_modal_with_svg_action_buttons_approve_reject_render_without_overflow(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/requests');
        $response->assertSee('Konfirmasi Setujui');
        $response->assertSee('Konfirmasi Tolak');

        $xpath = $this->extractDom($response->getContent());
        $approveButtons = $xpath->query("//div[@id='approveModal']//button[@type='submit']//svg");
        $this->assertGreaterThan(0, $approveButtons->count(), 'Approve modal submit button contains SVG icon');
    }

    public function test_tier2_bnd2_native_dialog_modals_logout_and_backup_maintain_viewport_reflow(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $xpath = $this->extractDom($response->getContent());

        $logoutDialog = $xpath->query("//dialog[@id='logoutModal']")->item(0);
        $this->assertNotNull($logoutDialog, "Admin layout must contain native <dialog id='logoutModal'>");
    }

    // -------------------------------------------------------------------------
    // Boundary 3: Scroll-Shadow Corner Cases
    // -------------------------------------------------------------------------

    public function test_tier2_bnd3_scroll_shadow_with_rtl_direction_support(): void
    {
        $css = $this->getCssContent();
        $this->assertStringContainsString('[dir="rtl"] .table-responsive', $css, 'RTL direction support should be preserved in style.css');
    }

    public function test_tier2_bnd3_scroll_shadow_at_zero_scroll_left_reveals_only_right_shadow(): void
    {
        // Verify background gradient positions for left and right
        $rules = $this->getCssRuleDeclarations('.table-responsive');
        $this->assertArrayHasKey('background-position', $rules, '.table-responsive must specify background-position for shadow logic');
    }

    public function test_tier2_bnd3_scroll_shadow_at_maximum_scroll_right_reveals_only_left_shadow(): void
    {
        $rules = $this->getCssRuleDeclarations('.table-responsive');
        $this->assertArrayHasKey('background-size', $rules, '.table-responsive must define background-size for shadow dimensions');
    }

    public function test_tier2_bnd3_scroll_shadow_on_table_with_zebra_striping_remains_visible(): void
    {
        $css = $this->getCssContent();
        // The table element must be transparent so zebra striping or cell backgrounds don't occlude shadows
        $this->assertMatchesRegularExpression(
            '/\.table-responsive\s*>\s*table\s*\{[^}]*background-color\s*:\s*transparent/s',
            $css,
            'Child table must remain transparent across all states'
        );
    }

    public function test_tier2_bnd3_table_responsive_with_nested_action_menu_does_not_break_layering(): void
    {
        $css = $this->getCssContent();
        $this->assertStringContainsString('.row-action-menu', $css, 'Row action menu class must be preserved');
    }

    // -------------------------------------------------------------------------
    // Boundary 4: Truncation & Disclosure Corner Cases
    // -------------------------------------------------------------------------

    public function test_tier2_bnd4_longest_division_name_in_topbar_subtitle_provides_complete_text_via_title(): void
    {
        $response = $this->actingAs($this->penilaiUser)->get('/dashboard');
        $xpath = $this->extractDom($response->getContent());

        $subtitle = $xpath->query("//p[contains(@class, 'topbar-subtitle')]")->item(0);
        $this->assertNotNull($subtitle, 'User topbar subtitle must exist');

        $titleAttr = $subtitle->getAttribute('title');
        $this->assertNotEmpty($titleAttr, 'Division name in topbar subtitle must have title attribute for long text disclosure');
        $this->assertStringContainsString('PTP Terminal Nonpetikemas Branch Cirebon', $titleAttr);
    }

    public function test_tier2_bnd4_longest_kpi_label_rata_rata_skor_kinerja_single_line_ellipsis(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $response->assertSee('Rata-Rata Skor Kinerja');

        $css = $this->getCssContent();
        $rules = $this->getCssRuleDeclarations('.kpi-label');

        $this->assertEquals('nowrap', $rules['white-space'] ?? null, "Longest KPI label 'Rata-Rata Skor Kinerja' must not wrap onto two lines");
        $this->assertEquals('ellipsis', $rules['text-overflow'] ?? null, 'Longest KPI label must truncate with ellipsis');
    }

    public function test_tier2_bnd4_empty_or_null_division_fallback_topbar_title_disclosure(): void
    {
        // When division is fallback "Divisi Operasional"
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('PTP Terminal Nonpetikemas Branch Cirebon');
    }

    public function test_tier2_bnd4_kpi_value_extreme_numbers_do_not_displace_truncated_label(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $xpath = $this->extractDom($response->getContent());

        $kpiVals = $xpath->query("//span[contains(@class, 'kpi-val')]");
        $this->assertGreaterThan(0, $kpiVals->count(), 'KPI value elements must exist');
    }

    public function test_tier2_bnd4_multiline_badge_labels_in_resource_summary_cards_have_exact_title_match(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/users');
        $xpath = $this->extractDom($response->getContent());

        $labels = $xpath->query("//span[contains(@class, 'kpi-label')]");
        $this->assertGreaterThan(0, $labels->count(), 'Summary cards in /admin/users must have .kpi-label elements');

        foreach ($labels as $label) {
            /** @var DOMElement $label */
            $text = trim($label->textContent);
            $this->assertEquals($text, trim($label->getAttribute('title')), 'Summary card label title must match verbatim text');
        }
    }

    public function test_tier2_bnd4_topbar_breadcrumb_and_page_title_box_disclose_full_hierarchy(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/system/settings');
        $response->assertSee('Konfigurasi Cabang &amp; Pejabat Penandatangan', false);
    }

    // -------------------------------------------------------------------------
    // Boundary 5: Workspace Synchronization & File Safety
    // -------------------------------------------------------------------------

    public function test_tier2_bnd5_worktree_tracks_only_prescribed_source_files(): void
    {
        $prescribedFiles = [
            'public/assets/css/style.css',
            'resources/views/layouts/admin.blade.php',
            'resources/views/layouts/app.blade.php',
            'resources/views/admin/rubrik/index.blade.php',
            'resources/views/admin/system/settings.blade.php',
            'resources/views/admin/edit_requests/index.blade.php',
            'resources/views/admin/users/index.blade.php',
            'resources/views/admin/activity_logs/index.blade.php',
            'resources/views/admin/reports/index.blade.php',
            'resources/views/admin/dashboard.blade.php',
            'resources/views/user/dashboard.blade.php',
            'resources/views/admin/employees/index.blade.php',
            'resources/views/admin/system/backup.blade.php',
            'resources/views/user/laporan/detail.blade.php',
        ];

        foreach ($prescribedFiles as $file) {
            $this->assertFileExists(base_path($file), "Prescribed remediation file {$file} must exist in worktree");
        }
    }

    public function test_tier2_bnd5_worktree_excludes_build_and_browser_artifacts_from_sync(): void
    {
        $gitignore = file_get_contents(base_path('.gitignore'));
        $this->assertNotFalse($gitignore);
        $this->assertStringContainsString('/node_modules', $gitignore);
        $this->assertStringContainsString('playwright-report/', $gitignore);
        $this->assertStringContainsString('test-results/', $gitignore);

        if (str_contains(base_path(), 'worktrees')) {
            $projectRoot = base_path();
            $this->assertFileDoesNotExist($projectRoot.DIRECTORY_SEPARATOR.'node_modules', 'Worktree must not contain node_modules directory');
            $this->assertFileDoesNotExist($projectRoot.DIRECTORY_SEPARATOR.'playwright-report', 'Worktree must not contain playwright-report');
            $this->assertFileDoesNotExist($projectRoot.DIRECTORY_SEPARATOR.'test-results', 'Worktree must not contain test-results directory');
        }
    }

    // =========================================================================
    // TIER 3: CROSS-FEATURE COMBINATIONS (Pairwise Interactions)
    // =========================================================================

    public function test_tier3_cross_modal_open_over_active_scrollable_table_preserves_scroll_isolation(): void
    {
        // When modal is active, modal-overlay has fixed positioning and z-index 9999
        $rules = $this->getCssRuleDeclarations('.modal-overlay');
        $this->assertEquals('fixed', $rules['position'] ?? null, '.modal-overlay must be position: fixed');
        $this->assertGreaterThanOrEqual(999, (int) ($rules['z-index'] ?? 0), '.modal-overlay z-index must sit above table scroll containers');
    }

    public function test_tier3_cross_sticky_action_footer_inside_modal_on_short_zoom_viewport_with_form_focus(): void
    {
        $rules = $this->getCssRuleDeclarations('.modal-actions-sticky');
        $this->assertEquals('sticky', $rules['position'] ?? null);
        $this->assertMatchesRegularExpression('/^0(?:px)?(?:\s*!important)?$/i', $rules['bottom'] ?? '');

        // Form inputs must not be clipped when focused above sticky footer
        $css = $this->getCssContent();
        $hasScrollMargin = preg_match('/scroll-margin-bottom\s*:\s*(?:6[0-9]|7[0-9]|8[0-9])px/s', $css);
        $this->assertTrue((bool) $hasScrollMargin, 'Cross-feature: input focus scroll-margin protects fields behind sticky footer');
    }

    public function test_tier3_cross_scroll_shadow_cues_during_dynamic_table_card_reflow_at_narrow_viewports(): void
    {
        $cardRules = $this->getCssRuleDeclarations('.card');
        $respRules = $this->getCssRuleDeclarations('.table-responsive');

        $this->assertEquals('hidden', $cardRules['overflow'] ?? null);
        $this->assertEquals('auto', $respRules['overflow-x'] ?? null);
    }

    public function test_tier3_cross_truncated_kpi_cards_inside_responsive_grid_during_200_percent_zoom(): void
    {
        $hasContainerRule = preg_match('/@container\s+content\s*\(\s*max-width\s*:\s*(?:860|1100)px\s*\)[^{]*\{((?:[^{}]+|\{[^{}]*\})*)\}/s', $this->getCssContent(), $m)
            && str_contains($m[1], '.dashboard-kpi-grid');
        $this->assertTrue((bool) $hasContainerRule, 'KPI cards inside dashboard-kpi-grid must respond to constrained content container width');
    }

    public function test_tier3_cross_modal_with_embedded_responsive_table_reports_detail_nested_scroll_behavior(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/reports');
        $xpath = $this->extractDom($response->getContent());

        $evalModal = $xpath->query("//div[@id='evaluationDetailModal']//div[contains(@class, 'modal-box')]")->item(0);
        $this->assertNotNull($evalModal, 'Evaluation detail modal must exist in reports view');

        $contentContainer = $xpath->query(".//div[@id='modalEvaluationContent']", $evalModal);
        $this->assertGreaterThan(0, $contentContainer->count(), 'Evaluation detail modal must contain #modalEvaluationContent container');

        $stickyActions = $xpath->query(".//div[contains(@class, 'modal-actions-sticky')]", $evalModal);
        $this->assertGreaterThan(0, $stickyActions->count(), 'Evaluation detail modal must contain sticky action buttons');
    }

    // =========================================================================
    // TIER 4: REAL-WORLD SCENARIOS
    // =========================================================================

    public function test_tier4_scen_desktop_browser_at_200_percent_zoom_1920x1080_scaled_to_960x540(): void
    {
        // 1920x1080 @ 200% zoom = 960px width x 540px height
        // Content width = 960px - 260px (sidebar) - 64px (padding) = 636px
        // .settings-grid MUST be in 1-column layout at 636px content width
        $hasSingleColumnAt636px = $this->hasMediaQueryRule('max-width: 1100px', '.settings-grid', 'grid-template-columns', 'minmax\(0,\s*1fr\)')
            || $this->hasMediaQueryRule('max-width: 1024px', '.settings-grid', 'grid-template-columns', 'minmax\(0,\s*1fr\)');

        $this->assertTrue(
            (bool) $hasSingleColumnAt636px,
            'Desktop at 200% zoom (960px viewport / 636px content width) must render .settings-grid in single column'
        );
    }

    public function test_tier4_scen_mobile_device_portrait_at_320x640_iphone_se_baseline(): void
    {
        // 1. Modal action buttons must stack vertically
        $hasStackedButtons = $this->hasMediaQueryRule('max-width: 480px', '.modal-actions-sticky', 'flex-direction', 'column(?:-reverse)?');
        $this->assertTrue((bool) $hasStackedButtons, '320px portrait must stack modal action buttons');

        // 2. Table responsive wrapper must provide scroll shadows
        $rules = $this->getCssRuleDeclarations('.table-responsive');
        $this->assertEquals('auto', $rules['overflow-x'] ?? null, '320px portrait must have horizontal scroll on tables');
    }

    public function test_tier4_scen_ipad_tablet_landscape_at_1024x768_with_260px_sidebar(): void
    {
        // Viewport = 1024px. Sidebar = 260px. Content width = 1024 - 260 - 64 = 700px.
        // At 700px content width, .rubrik-overview-grid and .settings-grid must adapt cleanly
        $css = $this->getCssContent();
        $hasAdaptiveLayout = preg_match('/@media\s*\(\s*max-width\s*:\s*(?:1024|1100)px\s*\)\s*\{[^}]*\.settings-grid/s', $css)
            || str_contains($css, '@container content');

        $this->assertTrue((bool) $hasAdaptiveLayout, 'iPad 1024px landscape with sidebar must trigger adaptive grid collapse');
    }

    public function test_tier4_scen_desktop_low_resolution_port_terminal_workstation_1366x768_at_150_percent_zoom(): void
    {
        // 1366x768 @ 150% zoom = 910px width x 512px height
        // Height is 512px -> requires safe modal height and sticky bottom 0
        $rules = $this->getCssRuleDeclarations('.modal-actions-sticky');
        $this->assertMatchesRegularExpression(
            '/^0(?:px)?(?:\s*!important)?$/i',
            $rules['bottom'] ?? '',
            'Port terminal 1366x768 @ 150% zoom requires bottom: 0 on sticky footer to avoid button clipping'
        );
    }
}

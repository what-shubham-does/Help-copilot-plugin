<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Register settings ─────────────────────────────────── */

add_action( 'admin_init', 'ihc_register_settings' );
function ihc_register_settings() {
    $text   = [ 'sanitize_callback' => 'sanitize_text_field' ];
    $url    = [ 'sanitize_callback' => 'esc_url_raw' ];
    $int    = [ 'sanitize_callback' => 'absint' ];
    $colour = [ 'sanitize_callback' => 'sanitize_hex_color' ];

    register_setting( 'ihc_settings_group', 'ihc_copilot_url',          $url );
    register_setting( 'ihc_settings_group', 'ihc_direct_line_secret',   $text );
    register_setting( 'ihc_settings_group', 'ihc_enabled',              $int );
    register_setting( 'ihc_settings_group', 'ihc_content_enabled',      $int );
    register_setting( 'ihc_settings_group', 'ihc_content_selector',     $text );
    register_setting( 'ihc_settings_group', 'ihc_base_url_enabled',     $int );
    register_setting( 'ihc_settings_group', 'ihc_base_url_override',    $url );
    register_setting( 'ihc_settings_group', 'ihc_header_title',         $text );
    register_setting( 'ihc_settings_group', 'ihc_logo_letter',          $text );
    register_setting( 'ihc_settings_group', 'ihc_overlay_width',        $int );
    register_setting( 'ihc_settings_group', 'ihc_overlay_height',       $int );
    register_setting( 'ihc_settings_group', 'ihc_disclaimer_text',      $text );
    register_setting( 'ihc_settings_group', 'ihc_fab_logo_url',         $url );
    register_setting( 'ihc_settings_group', 'ihc_accent_color',         $colour );
    register_setting( 'ihc_settings_group', 'ihc_header_logo_url',      $url );
    register_setting( 'ihc_settings_group', 'ihc_whatsnew_enabled',     $int );
    register_setting( 'ihc_settings_group', 'ihc_breadcrumb_selector',  $text );
    register_setting( 'ihc_settings_group', 'ihc_q1',                   $text );
    register_setting( 'ihc_settings_group', 'ihc_q2',                   $text );
    register_setting( 'ihc_settings_group', 'ihc_q3',                   $text );
    register_setting( 'ihc_settings_group', 'ihc_q4',                   $text );
    register_setting( 'ihc_settings_group', 'ihc_kb_questions', [
        'sanitize_callback' => 'ihc_sanitize_kb_questions',
        'default'           => '{}',
    ] );
}

/*
 * Sanitise per-KB questions.
 * Handles two input shapes:
 *   array — from the questions tab form (ihc_kb_questions[slug][])
 *   string — from a hidden input on other tabs (existing JSON)
 */
function ihc_sanitize_kb_questions( $value ) {
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        if ( is_array( $decoded ) ) {
            $clean = [];
            foreach ( $decoded as $kb => $questions ) {
                $kb  = sanitize_key( $kb );
                if ( ! $kb ) continue;
                $qs  = array_map( 'sanitize_text_field', array_values( (array) $questions ) );
                if ( array_filter( $qs ) ) $clean[ $kb ] = $qs;
            }
            return wp_json_encode( $clean );
        }
        return get_option( 'ihc_kb_questions', '{}' );
    }

    if ( ! is_array( $value ) ) return get_option( 'ihc_kb_questions', '{}' );

    $clean = [];
    foreach ( $value as $kb => $questions ) {
        $kb = sanitize_key( $kb );
        if ( ! $kb ) continue;
        $qs = array_map( 'sanitize_text_field', array_values( (array) $questions ) );
        if ( array_filter( $qs ) ) $clean[ $kb ] = $qs;
    }
    return wp_json_encode( $clean );
}

/* ── Admin script / media enqueue ──────────────────────── */

add_action( 'admin_enqueue_scripts', 'ihc_admin_enqueue_scripts' );
function ihc_admin_enqueue_scripts( $hook ) {
    if ( $hook !== 'settings_page_ihc-settings' ) return;
    $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'connection';
    if ( $tab === 'appearance' ) wp_enqueue_media();
}

/* ── Admin menu ────────────────────────────────────────── */

add_action( 'admin_menu', 'ihc_add_settings_page' );
function ihc_add_settings_page() {
    add_options_page( 'Help Copilot', 'Help Copilot', 'manage_options', 'ihc-settings', 'ihc_render_settings_page' );
}

function ihc_get( $key, $default = '' ) {
    return get_option( $key, $default );
}

/* ── KB list ───────────────────────────────────────────── */

function ihc_kb_list() {
    return [
        'get-started'             => 'Get Started',
        'use'                     => 'Use — CMP &amp; Products',
        'configure-platform'      => 'Configure Platform',
        'ai'                      => 'AI &amp; Analytics',
        'use-govcon'              => 'GovCon',
        'sap-ariba-nextgen'       => 'SAP Ariba Next-Gen',
        'sap-ariba-integration'   => 'SAP Ariba Integration',
        'sap-ci-by-icertis'       => 'SAP CI by Icertis',
        'develop'                 => 'Developer',
        'administer'              => 'Platform Administration',
        'integrate'               => 'Solutions &amp; Integrate',
        'configure-business-apps' => 'Legacy Business Apps',
    ];
}

/* ── Settings page ─────────────────────────────────────── */

function ihc_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Connection
    $url              = ihc_get( 'ihc_copilot_url' );
    $secret           = ihc_get( 'ihc_direct_line_secret' );
    $enabled          = ihc_get( 'ihc_enabled', '1' );
    $content_enabled  = ihc_get( 'ihc_content_enabled', '' );
    $content_selector = ihc_get( 'ihc_content_selector', 'article .entry-content, .entry-content, article' );
    $base_url_enabled  = ihc_get( 'ihc_base_url_enabled', '' );
    $base_url_override = ihc_get( 'ihc_base_url_override', '' );
    // Appearance
    $title       = ihc_get( 'ihc_header_title', 'Icertis Help Center' );
    $letter      = ihc_get( 'ihc_logo_letter',  'H' );
    $width       = max( 300, min( 700, (int) ihc_get( 'ihc_overlay_width',  400 ) ) );
    $height      = max( 380, min( 700, (int) ihc_get( 'ihc_overlay_height', 580 ) ) );
    $disclaimer  = ihc_get( 'ihc_disclaimer_text', 'AI-generated responses may be inaccurate. Please verify with official documentation.' );
    $fab_logo    = ihc_get( 'ihc_fab_logo_url', '' );
    $accent      = sanitize_hex_color( ihc_get( 'ihc_accent_color', '#0057B8' ) ) ?: '#0057B8';
    $header_logo = ihc_get( 'ihc_header_logo_url', '' );
    $whatsnew_enabled    = ihc_get( 'ihc_whatsnew_enabled', '1' );
    $breadcrumb_selector = ihc_get( 'ihc_breadcrumb_selector', '.breadcrumbs a, .breadcrumb a, [aria-label="Breadcrumbs"] a, .site-breadcrumb a' );
    $q1 = ihc_get( 'ihc_q1', "What's new in the latest release?" );
    $q2 = ihc_get( 'ihc_q2', 'How do I create an agreement?' );
    $q3 = ihc_get( 'ihc_q3', 'How do I configure user roles?' );
    $q4 = ihc_get( 'ihc_q4', 'How do I contact Support?' );
    // Per-KB questions
    $kb_questions_raw = ihc_get( 'ihc_kb_questions', '{}' );
    $kb_questions     = json_decode( $kb_questions_raw, true ) ?: [];

    $is_live    = $enabled && ( $url || $secret );
    $mode       = $secret ? 'webchat' : ( $url ? 'iframe' : 'none' );
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'connection';
    $base_url   = admin_url( 'options-general.php?page=ihc-settings' );
    ?>
    <div class="wrap ihc-wrap">

        <div class="ihc-admin-header">
            <div class="ihc-admin-logo">
                <div class="ihc-admin-mark"><?php echo esc_html( $letter ?: 'H' ); ?></div>
                <div>
                    <h1>Icertis Help Copilot</h1>
                    <p class="ihc-admin-sub">Manage your AI chat overlay from one place.</p>
                </div>
            </div>
            <div class="ihc-status-pill <?php echo $is_live ? 'ihc-status-on' : 'ihc-status-off'; ?>">
                <?php if ( $is_live ) : ?>● Live <span class="ihc-mode-badge"><?php echo $mode === 'webchat' ? 'Webchat' : 'iFrame'; ?></span>
                <?php else : ?>○ Inactive<?php endif; ?>
            </div>
        </div>

        <?php settings_errors( 'ihc_settings_group' ); ?>

        <nav class="ihc-tabs">
            <?php foreach ( [ 'connection' => 'Connection', 'appearance' => 'Appearance', 'questions' => 'Questions', 'setup' => 'Setup guide' ] as $slug => $label ) : ?>
                <a href="<?php echo esc_url( $base_url . '&tab=' . $slug ); ?>" class="ihc-tab<?php echo $slug === $active_tab ? ' ihc-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="options.php" id="ihcForm">
            <?php settings_fields( 'ihc_settings_group' ); ?>

            <!-- ══ CONNECTION ══════════════════════════════ -->
            <?php if ( $active_tab === 'connection' ) : ?>

            <div class="ihc-card">
                <h2>Copilot connection</h2>
                <p class="ihc-card-desc">Choose how this site connects to your Copilot Studio agent.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_enabled">Overlay enabled</label></th>
                        <td>
                            <label class="ihc-switch"><input type="checkbox" id="ihc_enabled" name="ihc_enabled" value="1" <?php checked( $enabled, '1' ); ?>><span class="ihc-switch-track"></span></label>
                            <p class="description">Uncheck to hide the overlay sitewide without losing settings.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ihc-card">
                <div class="ihc-card-mode-header">
                    <div class="ihc-mode-tag ihc-mode-recommended">Recommended</div>
                    <h2>Webchat mode — Direct Line</h2>
                </div>
                <p class="ihc-card-desc">Found in <strong>Copilot Studio → Settings → Security → Web channel security</strong>.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_direct_line_secret">Direct Line secret</label></th>
                        <td>
                            <input type="password" id="ihc_direct_line_secret" name="ihc_direct_line_secret" value="<?php echo esc_attr( $secret ); ?>" class="large-text" placeholder="Paste Secret 1 from Copilot Studio" autocomplete="new-password" />
                            <?php if ( $secret ) : ?><p class="ihc-url-ok">✓ Secret saved — webchat mode active</p><?php endif; ?>
                            <p class="description">⚠ Exposed in browser JavaScript in v1.3.x. Moves server-side in v1.4.0.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ihc-card ihc-card-legacy">
                <h2>Legacy mode — iFrame URL</h2>
                <p class="ihc-card-desc">Found in <strong>Copilot Studio → Channels → Custom website → Copy</strong>.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_copilot_url">Copilot Studio URL</label></th>
                        <td>
                            <input type="url" id="ihc_copilot_url" name="ihc_copilot_url" value="<?php echo esc_attr( $url ); ?>" class="large-text" placeholder="https://copilotstudio.microsoft.com/environments/…" />
                            <?php if ( $url ) : ?><p class="ihc-url-ok">✓ URL saved</p><?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ihc-card">
                <h2>Page content</h2>
                <p class="ihc-card-desc">When enabled, sends the article text to the agent on documentation pages. Up to 6,000 characters.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_content_enabled">Send page content</label></th>
                        <td>
                            <label class="ihc-switch"><input type="checkbox" id="ihc_content_enabled" name="ihc_content_enabled" value="1" <?php checked( $content_enabled, '1' ); ?> onchange="document.getElementById('ihcContentSelectorRow').style.opacity=this.checked?'1':'0.45';"><span class="ihc-switch-track"></span></label>
                        </td>
                    </tr>
                    <tr id="ihcContentSelectorRow" style="opacity:<?php echo $content_enabled ? '1' : '0.45'; ?>;">
                        <th><label for="ihc_content_selector">Content CSS selector</label></th>
                        <td>
                            <input type="text" id="ihc_content_selector" name="ihc_content_selector" value="<?php echo esc_attr( $content_selector ); ?>" class="large-text" />
                            <p class="description">CSS selector for the main article body.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ihc-card">
                <h2>Base URL</h2>
                <p class="ihc-card-desc">Enable after updating knowledge files to use relative URL paths.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_base_url_enabled">Enable relative URL mode</label></th>
                        <td>
                            <label class="ihc-switch"><input type="checkbox" id="ihc_base_url_enabled" name="ihc_base_url_enabled" value="1" <?php checked( $base_url_enabled, '1' ); ?> onchange="document.getElementById('ihcBaseUrlOverrideRow').style.opacity=this.checked?'1':'0.45';"><span class="ihc-switch-track"></span></label>
                        </td>
                    </tr>
                    <tr id="ihcBaseUrlOverrideRow" style="opacity:<?php echo $base_url_enabled ? '1' : '0.45'; ?>;">
                        <th><label for="ihc_base_url_override">Override URL <span style="font-weight:400;">(optional)</span></label></th>
                        <td>
                            <input type="url" id="ihc_base_url_override" name="ihc_base_url_override" value="<?php echo esc_attr( $base_url_override ); ?>" class="large-text" placeholder="Leave empty — auto-detected from window.location.origin" />
                        </td>
                    </tr>
                </table>
            </div>

            <?php endif; // connection ?>

            <!-- ══ APPEARANCE ══════════════════════════════ -->
            <?php if ( $active_tab === 'appearance' ) : ?>

            <div class="ihc-card">
                <h2>Appearance</h2>
                <p class="ihc-card-desc">Control how the overlay looks. Changes apply after saving.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_header_title">Header title</label></th>
                        <td><input type="text" id="ihc_header_title" name="ihc_header_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" maxlength="60" /></td>
                    </tr>
                    <tr>
                        <th><label for="ihc_logo_letter">Logo mark letter</label></th>
                        <td><input type="text" id="ihc_logo_letter" name="ihc_logo_letter" value="<?php echo esc_attr( $letter ); ?>" class="small-text" maxlength="2" /><p class="description">Single letter when no header logo is set. Also used as the bot avatar initial.</p></td>
                    </tr>
                    <tr>
                        <th><label>Header logo</label></th>
                        <td>
                            <?php if ( $header_logo ) : ?><div class="ihc-fab-preview" id="ihcHeaderLogoPreview"><div class="ihc-fab-preview-btn" style="border-radius:8px;"><img src="<?php echo esc_url( $header_logo ); ?>" alt="" /></div><span class="ihc-fab-preview-label">Current logo &mdash; <a href="#" id="ihcHeaderLogoClear">Remove</a></span></div><?php endif; ?>
                            <input type="hidden" id="ihc_header_logo_url" name="ihc_header_logo_url" value="<?php echo esc_attr( $header_logo ); ?>" />
                            <button type="button" id="ihcHeaderLogoBtn" class="button"><?php echo $header_logo ? 'Change logo' : 'Choose logo from Media Library'; ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ihc_accent_color">Primary colour</label></th>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <input type="color" id="ihc_accent_color" name="ihc_accent_color" value="<?php echo esc_attr( $accent ); ?>" style="width:48px; height:36px; padding:2px; border:1px solid #DDD; border-radius:6px; cursor:pointer;" />
                                <code id="ihcAccentCode"><?php echo esc_html( $accent ); ?></code>
                            </div>
                            <script>document.getElementById("ihc_accent_color").addEventListener("input",function(){document.getElementById("ihcAccentCode").textContent=this.value;});</script>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ihc_overlay_width">Initial panel width</label></th>
                        <td><div class="ihc-slider-row"><input type="range" id="ihc_overlay_width" name="ihc_overlay_width" min="300" max="700" step="10" value="<?php echo esc_attr( $width ); ?>" oninput="document.getElementById('ihcWidthVal').textContent=this.value+'px'" /><span class="ihc-slider-val" id="ihcWidthVal"><?php echo esc_html( $width ); ?>px</span></div></td>
                    </tr>
                    <tr>
                        <th><label for="ihc_overlay_height">Initial panel height</label></th>
                        <td><div class="ihc-slider-row"><input type="range" id="ihc_overlay_height" name="ihc_overlay_height" min="380" max="700" step="10" value="<?php echo esc_attr( $height ); ?>" oninput="document.getElementById('ihcHeightVal').textContent=this.value+'px'" /><span class="ihc-slider-val" id="ihcHeightVal"><?php echo esc_html( $height ); ?>px</span></div></td>
                    </tr>
                    <tr>
                        <th><label for="ihc_disclaimer_text">Disclaimer text</label></th>
                        <td><input type="text" id="ihc_disclaimer_text" name="ihc_disclaimer_text" value="<?php echo esc_attr( $disclaimer ); ?>" class="large-text" maxlength="200" /></td>
                    </tr>
                    <tr>
                        <th><label for="ihc_fab_logo_url">Mobile button logo</label></th>
                        <td>
                            <?php if ( $fab_logo ) : ?><div class="ihc-fab-preview" id="ihcFabPreview"><div class="ihc-fab-preview-btn"><img src="<?php echo esc_url( $fab_logo ); ?>" alt="" /></div><span class="ihc-fab-preview-label">Current logo &mdash; <a href="#" id="ihcFabClear">Remove</a></span></div><?php endif; ?>
                            <input type="hidden" id="ihc_fab_logo_url" name="ihc_fab_logo_url" value="<?php echo esc_attr( $fab_logo ); ?>" />
                            <button type="button" id="ihcFabMediaBtn" class="button"><?php echo $fab_logo ? 'Change logo' : 'Choose logo from Media Library'; ?></button>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="ihc-card">
                <h2>Landing page</h2>
                <p class="ihc-card-desc">Controls the introduction page shown on fresh conversations. Configure per-KB suggestion chips on the <a href="<?php echo esc_url( $base_url . '&tab=questions' ); ?>">Questions tab</a>.</p>
                <table class="form-table ihc-form-table" role="presentation">
                    <tr>
                        <th><label for="ihc_whatsnew_enabled">"What's new" chip</label></th>
                        <td>
                            <label class="ihc-switch"><input type="checkbox" id="ihc_whatsnew_enabled" name="ihc_whatsnew_enabled" value="1" <?php checked( $whatsnew_enabled, '1' ); ?>><span class="ihc-switch-track"></span></label>
                            <p class="description">Shows a "What's new in [current page]?" chip on docs and release pages.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ihc_breadcrumb_selector">Breadcrumb CSS selector</label></th>
                        <td>
                            <input type="text" id="ihc_breadcrumb_selector" name="ihc_breadcrumb_selector" value="<?php echo esc_attr( $breadcrumb_selector ); ?>" class="large-text" />
                            <p class="description">Used to extract human-readable labels for chip meta badges and message context.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Default common questions</label></th>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <?php foreach ( [ 'ihc_q1' => $q1, 'ihc_q2' => $q2, 'ihc_q3' => $q3, 'ihc_q4' => $q4 ] as $key => $val ) : ?>
                                <input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" class="large-text" maxlength="120" />
                                <?php endforeach; ?>
                            </div>
                            <p class="description">Shown when no KB-specific questions are configured for the current page. Clear a field to remove that chip.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php endif; // appearance ?>

            <!-- ══ QUESTIONS ════════════════════════════════ -->
            <?php if ( $active_tab === 'questions' ) : ?>

            <div class="ihc-card">
                <h2>Per-KB suggestion questions</h2>
                <p class="ihc-card-desc">Configure up to 8 suggestion chips per knowledge base. When a user is on a page under that KB, these questions replace the default set. Leave all 8 fields empty to use the default questions for that KB.</p>
                <p class="ihc-card-desc" style="margin-top:0;">Default questions are managed on the <a href="<?php echo esc_url( $base_url . '&tab=appearance' ); ?>">Appearance tab</a>.</p>
            </div>

            <?php foreach ( ihc_kb_list() as $slug => $label ) :
                $kb_qs       = isset( $kb_questions[ $slug ] ) ? $kb_questions[ $slug ] : [];
                $configured  = count( array_filter( $kb_qs, function( $q ) { return trim( $q ) !== ''; } ) );
                $has_content = $configured > 0;
            ?>
            <details class="ihc-kb-accordion" <?php echo $has_content ? 'open' : ''; ?>>
                <summary class="ihc-kb-summary">
                    <span class="ihc-kb-chevron">&#8250;</span>
                    <span class="ihc-kb-label"><?php echo $label; ?></span>
                    <code class="ihc-kb-slug"><?php echo esc_html( $slug ); ?></code>
                    <?php if ( $has_content ) : ?>
                    <span class="ihc-kb-count"><?php echo $configured; ?> configured</span>
                    <?php endif; ?>
                </summary>
                <div class="ihc-kb-questions">
                    <?php for ( $i = 0; $i < 8; $i++ ) : ?>
                    <input
                        type="text"
                        name="ihc_kb_questions[<?php echo esc_attr( $slug ); ?>][]"
                        value="<?php echo esc_attr( isset( $kb_qs[ $i ] ) ? $kb_qs[ $i ] : '' ); ?>"
                        class="large-text"
                        maxlength="120"
                        placeholder="Question <?php echo $i + 1; ?>" />
                    <?php endfor; ?>
                </div>
            </details>
            <?php endforeach; ?>

            <?php endif; // questions ?>

            <!-- ══ SETUP GUIDE ════════════════════════════ -->
            <?php if ( $active_tab === 'setup' ) : ?>
            <div class="ihc-card">
                <h2>Setup guide</h2>
                <p class="ihc-card-desc">Follow these steps to get the overlay live.</p>
                <?php
                $steps = [
                    [ 'title' => 'Connect to your Copilot Studio agent', 'body' => 'Go to the <a href="' . esc_url( $base_url . '&tab=connection' ) . '">Connection tab</a>. Paste your Direct Line secret from <strong>Copilot Studio → Settings → Security → Web channel security</strong>.' ],
                    [ 'title' => 'Add the Browse / Ask toggle to your Blocksy header', 'body' => 'In <strong>Appearance → Blocksy → Header</strong>, add a <strong>Custom HTML</strong> block and paste: <span class="ihc-code-block">[icertis_help_toggle]</span>' ],
                    [ 'title' => 'Customise the look and landing page', 'body' => 'Go to the <a href="' . esc_url( $base_url . '&tab=appearance' ) . '">Appearance tab</a> to change colours, logos, panel size, and default suggestion chips.' ],
                    [ 'title' => 'Configure per-KB suggestion questions', 'body' => 'Go to the <a href="' . esc_url( $base_url . '&tab=questions' ) . '">Questions tab</a> to set up to 8 context-relevant questions per knowledge base.' ],
                    [ 'title' => 'Upgrade the Direct Line secret to server-side (v1.4.0)', 'body' => 'The secret is currently in browser JavaScript. In v1.4.0 a WordPress token endpoint will handle this server-side.' ],
                ];
                foreach ( $steps as $n => $step ) : ?>
                <div class="ihc-guide-step">
                    <div class="ihc-guide-num"><?php echo $n + 1; ?></div>
                    <div><strong><?php echo esc_html( $step['title'] ); ?></strong><p><?php echo $step['body']; ?></p></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( $active_tab !== 'setup' ) : ?>
            <div class="ihc-save-bar"><?php submit_button( 'Save settings', 'primary', 'submit', false ); ?></div>
            <?php endif; ?>

            <?php /* Preserve settings from inactive tabs */ ?>
            <?php if ( $active_tab !== 'connection' ) : ?>
                <input type="hidden" name="ihc_enabled"            value="<?php echo esc_attr( $enabled ); ?>" />
                <input type="hidden" name="ihc_direct_line_secret" value="<?php echo esc_attr( $secret ); ?>" />
                <input type="hidden" name="ihc_copilot_url"        value="<?php echo esc_attr( $url ); ?>" />
                <input type="hidden" name="ihc_content_enabled"    value="<?php echo esc_attr( $content_enabled ); ?>" />
                <input type="hidden" name="ihc_content_selector"   value="<?php echo esc_attr( $content_selector ); ?>" />
                <input type="hidden" name="ihc_base_url_enabled"   value="<?php echo esc_attr( $base_url_enabled ); ?>" />
                <input type="hidden" name="ihc_base_url_override"  value="<?php echo esc_attr( $base_url_override ); ?>" />
            <?php endif; ?>
            <?php if ( $active_tab !== 'appearance' ) : ?>
                <input type="hidden" name="ihc_header_title"        value="<?php echo esc_attr( $title ); ?>" />
                <input type="hidden" name="ihc_logo_letter"         value="<?php echo esc_attr( $letter ); ?>" />
                <input type="hidden" name="ihc_overlay_width"       value="<?php echo esc_attr( $width ); ?>" />
                <input type="hidden" name="ihc_overlay_height"      value="<?php echo esc_attr( $height ); ?>" />
                <input type="hidden" name="ihc_disclaimer_text"     value="<?php echo esc_attr( $disclaimer ); ?>" />
                <input type="hidden" name="ihc_fab_logo_url"        value="<?php echo esc_attr( $fab_logo ); ?>" />
                <input type="hidden" name="ihc_accent_color"        value="<?php echo esc_attr( $accent ); ?>" />
                <input type="hidden" name="ihc_header_logo_url"     value="<?php echo esc_attr( $header_logo ); ?>" />
                <input type="hidden" name="ihc_whatsnew_enabled"    value="<?php echo esc_attr( $whatsnew_enabled ); ?>" />
                <input type="hidden" name="ihc_breadcrumb_selector" value="<?php echo esc_attr( $breadcrumb_selector ); ?>" />
                <input type="hidden" name="ihc_q1"                  value="<?php echo esc_attr( $q1 ); ?>" />
                <input type="hidden" name="ihc_q2"                  value="<?php echo esc_attr( $q2 ); ?>" />
                <input type="hidden" name="ihc_q3"                  value="<?php echo esc_attr( $q3 ); ?>" />
                <input type="hidden" name="ihc_q4"                  value="<?php echo esc_attr( $q4 ); ?>" />
            <?php endif; ?>
            <?php if ( $active_tab !== 'questions' ) : ?>
                <input type="hidden" name="ihc_kb_questions" value="<?php echo esc_attr( $kb_questions_raw ); ?>" />
            <?php endif; ?>

        </form>
    </div>

    <style>
    .ihc-wrap{max-width:800px}
    .ihc-admin-header{display:flex;align-items:center;justify-content:space-between;margin:24px 0 0}
    .ihc-admin-logo{display:flex;align-items:center;gap:14px}
    .ihc-admin-logo h1{margin:0;font-size:20px}
    .ihc-admin-sub{margin:2px 0 0;color:#777;font-size:13px}
    .ihc-admin-mark{width:36px;height:36px;background:<?php echo esc_attr($accent);?>;border-radius:7px;color:#fff;font-size:16px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .ihc-status-pill{font-size:12px;font-weight:600;padding:5px 14px;border-radius:100px;display:flex;align-items:center;gap:8px}
    .ihc-status-on{background:#E6F9ED;color:#1A7F3C;border:1px solid #9FE0B4}
    .ihc-status-off{background:#F5F5F5;color:#888;border:1px solid #DDD}
    .ihc-mode-badge{background:rgba(0,0,0,.08);border-radius:4px;padding:2px 7px;font-size:11px;font-weight:500}
    .ihc-tabs{display:flex;gap:2px;border-bottom:2px solid #E5E3DB;margin:24px 0 0}
    .ihc-tab{padding:10px 18px;font-size:13px;font-weight:500;color:#555;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s}
    .ihc-tab:hover{color:<?php echo esc_attr($accent);?>}
    .ihc-tab-active{color:<?php echo esc_attr($accent);?>;border-bottom-color:<?php echo esc_attr($accent);?>;font-weight:600}
    .ihc-card{background:#fff;border:1px solid #E5E3DB;border-radius:10px;padding:28px 32px;margin-top:20px}
    .ihc-card-legacy{opacity:.75}
    .ihc-card h2{margin:0 0 4px;font-size:15px}
    .ihc-card-desc{color:#666;font-size:13px;margin:0 0 24px}
    .ihc-card-mode-header{display:flex;align-items:center;gap:10px;margin-bottom:4px}
    .ihc-card-mode-header h2{margin:0}
    .ihc-mode-tag{font-size:10px;font-weight:700;padding:3px 8px;border-radius:4px;letter-spacing:.3px;text-transform:uppercase}
    .ihc-mode-recommended{background:#E6F9ED;color:#1A7F3C;border:1px solid #9FE0B4}
    .ihc-form-table th{width:200px;font-weight:500;padding-left:0;vertical-align:top;padding-top:14px}
    .ihc-form-table td{padding-left:0}
    .ihc-switch{display:inline-flex;align-items:center;cursor:pointer}
    .ihc-switch input{opacity:0;width:0;height:0;position:absolute}
    .ihc-switch-track{width:40px;height:22px;background:#CCC;border-radius:11px;transition:background .2s;position:relative}
    .ihc-switch-track::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:transform .2s}
    .ihc-switch input:checked+.ihc-switch-track{background:<?php echo esc_attr($accent);?>}
    .ihc-switch input:checked+.ihc-switch-track::after{transform:translateX(18px)}
    .ihc-slider-row{display:flex;align-items:center;gap:14px}
    input[type=range]{width:220px;accent-color:<?php echo esc_attr($accent);?>}
    .ihc-slider-val{font-size:14px;font-weight:600;color:<?php echo esc_attr($accent);?>;min-width:48px}
    .ihc-url-ok{color:#1A7F3C;font-size:12px;margin-top:6px}
    .ihc-guide-step{display:flex;gap:16px;align-items:flex-start;padding:16px 0;border-bottom:1px solid #F0EFE8}
    .ihc-guide-step:last-child{border-bottom:none}
    .ihc-guide-num{width:26px;height:26px;flex-shrink:0;background:<?php echo esc_attr($accent);?>;color:#fff;border-radius:50%;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px}
    .ihc-guide-step strong{font-size:13px}
    .ihc-guide-step p{margin:4px 0 0;font-size:13px;color:#555}
    .ihc-code-block{font-family:monospace;font-size:14px;background:#F5F8FF;border:1px solid #C2D9F8;border-radius:6px;padding:8px 14px;display:inline-block;margin:6px 0;user-select:all}
    .ihc-save-bar{margin-top:20px;padding:16px 0;border-top:1px solid #E5E3DB}
    .ihc-fab-preview{display:flex;align-items:center;gap:12px;margin-bottom:8px}
    .ihc-fab-preview-btn{width:52px;height:52px;border-radius:50%;background:<?php echo esc_attr($accent);?>;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,30,70,.2);flex-shrink:0}
    .ihc-fab-preview-btn img{width:28px;height:28px;object-fit:contain;display:block}
    .ihc-fab-preview-label{font-size:12px;color:#888}
    /* KB accordion */
    .ihc-kb-accordion{border:1px solid #E5E3DB;border-radius:8px;margin-top:12px;background:#fff;overflow:hidden}
    .ihc-kb-summary{display:flex;align-items:center;gap:10px;padding:14px 20px;cursor:pointer;list-style:none;user-select:none}
    .ihc-kb-summary::-webkit-details-marker{display:none}
    .ihc-kb-chevron{color:#999;font-size:18px;transition:transform .2s;display:inline-block;line-height:1}
    details[open] .ihc-kb-chevron{transform:rotate(90deg)}
    .ihc-kb-label{font-size:13px;font-weight:600;color:#222;flex:1}
    .ihc-kb-slug{font-size:11px;background:#F5F8FF;border:1px solid #C2D9F8;border-radius:4px;padding:2px 7px;color:#5A7FAD;font-family:monospace}
    .ihc-kb-count{font-size:11px;font-weight:600;color:#1A7F3C;background:#E6F9ED;border:1px solid #9FE0B4;border-radius:100px;padding:2px 10px}
    .ihc-kb-questions{display:flex;flex-direction:column;gap:8px;padding:16px 20px 20px;border-top:1px solid #F0EFE8;background:#FAFAFA}
    </style>

    <?php if ( $active_tab === 'appearance' ) : ?>
    <script>
    (function(){
        function mediaBtn(fieldId,btnId,clearId,previewWrapperId,opts){
            var f=document.getElementById(fieldId),b=document.getElementById(btnId),c=document.getElementById(clearId),mf;
            if(!b||!f)return;
            b.addEventListener("click",function(e){
                e.preventDefault();
                if(mf){mf.open();return;}
                mf=wp.media({title:opts.title,button:{text:opts.btn},multiple:false,library:{type:["image"]}});
                mf.on("select",function(){
                    var a=mf.state().get("selection").first().toJSON();
                    f.value=a.url;
                    var ex=document.getElementById(previewWrapperId);
                    if(ex){ex.querySelector("img").src=a.url;}
                    else{var w=document.createElement("div");w.id=previewWrapperId;w.className="ihc-fab-preview";w.innerHTML='<div class="ihc-fab-preview-btn"'+(opts.square?' style="border-radius:8px;"':'')+'>  <img src="'+a.url+'" alt=""/></div><span class="ihc-fab-preview-label">Current logo</span>';f.closest("td").insertBefore(w,f);}
                });
                mf.open();
            });
            if(c){c.addEventListener("click",function(e){e.preventDefault();f.value="";var p=document.getElementById(previewWrapperId);if(p)p.remove();});}
        }
        mediaBtn("ihc_fab_logo_url","ihcFabMediaBtn","ihcFabClear","ihcFabPreview",{title:"Select mobile button logo",btn:"Use this logo"});
        mediaBtn("ihc_header_logo_url","ihcHeaderLogoBtn","ihcHeaderLogoClear","ihcHeaderLogoPreview",{title:"Select header logo",btn:"Use this logo",square:true});
    })();
    </script>
    <?php endif; ?>
    <?php
}

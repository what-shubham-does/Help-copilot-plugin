<?php
/**
 * Plugin Name:  Icertis Help Copilot
 * Description:  Embeds the Icertis Help Copilot overlay on the Help Center site.
 * Version:      1.3.8
 * Author:       Icertis
 * License:      Proprietary
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'IHC_VERSION', '1.3.8' );
define( 'IHC_DIR',     plugin_dir_path( __FILE__ ) );
define( 'IHC_URL',     plugin_dir_url( __FILE__ ) );

require_once IHC_DIR . 'includes/settings.php';

/* ── Preconnect hints ── */
add_action( 'wp_head', 'ihc_preconnect_hints', 1 );
function ihc_preconnect_hints() {
    if ( ! ihc_is_active() ) return;
    echo '<link rel="preconnect" href="https://cdn.botframework.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://directline.botframework.com" crossorigin>' . "\n";
    echo '<link rel="dns-prefetch" href="https://fonts.googleapis.com">' . "\n";
}

/* ── Enqueue assets ── */
add_action( 'wp_enqueue_scripts', 'ihc_enqueue_assets' );
function ihc_enqueue_assets() {
    if ( ! ihc_is_active() ) return;

    wp_enqueue_style(  'ihc-overlay',   IHC_URL . 'assets/css/overlay.css', [], IHC_VERSION );
    wp_enqueue_script( 'ihc-stack-nav', IHC_URL . 'assets/js/stack-nav.js', [], IHC_VERSION, true );
    wp_enqueue_script( 'ihc-overlay',   IHC_URL . 'assets/js/overlay.js',   [], IHC_VERSION, true );

    $accent      = sanitize_hex_color( get_option( 'ihc_accent_color', '#010172' ) ) ?: '#010172';
    $logo_height  = max( 20, min( 56, (int) get_option( 'ihc_header_logo_height', 32 ) ) );
    $font_size    = max( 12, min( 18, (int) get_option( 'ihc_chat_font_size', 14 ) ) );

    wp_localize_script( 'ihc-overlay', 'ihcSettings', [
        'copilotSrc'         => get_option( 'ihc_copilot_url', '' ),
        'directLineSecret'   => get_option( 'ihc_direct_line_secret', '' ),
        'logoLetter'         => get_option( 'ihc_logo_letter', 'H' ),
        'botName'            => get_option( 'ihc_header_title', 'Icertis Help Center' ),
        'accentColor'        => $accent,
        'contentEnabled'     => (bool) get_option( 'ihc_content_enabled', '' ),
        'contentSelector'    => get_option( 'ihc_content_selector', 'article .entry-content, .entry-content, article' ),
        'whatsnewEnabled'    => (bool) get_option( 'ihc_whatsnew_enabled', '1' ),
        'breadcrumbSelector' => get_option( 'ihc_breadcrumb_selector', '.breadcrumbs a, .breadcrumb a, [aria-label="Breadcrumbs"] a, .site-breadcrumb a' ),
        'q1'                 => get_option( 'ihc_q1', "What's new in the latest release?" ),
        'q2'                 => get_option( 'ihc_q2', 'How do I create an agreement?' ),
        'q3'                 => get_option( 'ihc_q3', 'How do I configure user roles?' ),
        'q4'                 => get_option( 'ihc_q4', 'How do I contact Support?' ),
        'kbQuestions'        => json_decode( get_option( 'ihc_kb_questions', '{}' ), true ) ?: (object)[],
    ] );

    $panel_w = max( 300, min( 700, (int) get_option( 'ihc_overlay_width',  400 ) ) );
    $panel_h = max( 380, min( 700, (int) get_option( 'ihc_overlay_height', 580 ) ) );

    wp_add_inline_style( 'ihc-overlay', "
        .hc-ask-overlay                              { width:{$panel_w}px; height:{$panel_h}px; font-size:{$font_size}px; }
        .hc-ask-header                               { background:{$accent}; border-bottom-color:{$accent}; }
        .hc-mobile-fab                               { background:{$accent}; }
        .hc-mobile-fab:hover                         { background:{$accent}; opacity:0.88; }
        .hc-toggle-opt.active                        { background:{$accent}; border-color:{$accent}; }
        .hc-ask-header .hc-toggle-opt.active         { color:{$accent}; }
        .hc-webchat-container a                      { color:{$accent}; text-decoration-color:rgba(0,0,0,.2); }
        .hc-btn-retry                                { color:{$accent}; }
        .hc-qa-icon svg                              { color:{$accent}; }
        .hc-land-textarea:focus                      { border-color:{$accent}; }
        .hc-land-send-btn                            { background:{$accent}; }
        .hc-btn-preview-send                         { background:{$accent}; }
        .hc-q-row:hover .hc-q-text                  { color:{$accent}; }
        .hc-spinner                                  { border-top-color:{$accent}; }
        .hc-ask-logo-img                             { height:{$logo_height}px; }
        .hc-webchat-container .webchat__bubble__content { font-size:{$font_size}px; }
    " );
}

/* ── Helper ── */
function ihc_is_active() {
    return get_option( 'ihc_enabled', '1' ) &&
           ( get_option( 'ihc_copilot_url', '' ) || get_option( 'ihc_direct_line_secret', '' ) );
}

/* ── Page context script ── */
add_action( 'wp_body_open', 'ihc_page_context_script' );
function ihc_page_context_script() {
    if ( ! ihc_is_active() ) return;
    $base_url_enabled  = get_option( 'ihc_base_url_enabled', '' );
    $base_url_override = esc_js( get_option( 'ihc_base_url_override', '' ) );
    ?>
    <script>
    (function () {
        var path     = window.location.pathname.replace(/\/$/, "");
        var segments = path.split("/").filter(Boolean);
        var rawTitle  = document.title;
        var pageTitle = rawTitle.replace(/\s*[|\-\u2013\u2014]\s*[^|\-\u2013\u2014]+$/, "").trim() || rawTitle;
        var surface, kb, category, article;
        if (segments[0] === "docs") {
            surface = "documentation";
            var docSegs = segments.slice(1);
            kb       = docSegs[0] || null;
            category = docSegs.length >= 3 ? docSegs[1] : null;
            article  = docSegs.length >= 2 ? docSegs[docSegs.length - 1] : null;
        } else if (segments[0] === "relpages") {
            surface = "release-landing"; kb = null; category = segments[1] || null; article = null;
        } else if (segments[0] && /^\d{4}$/.test(segments[0])) {
            surface = "release-note"; kb = null; category = null; article = segments[3] || null;
        } else {
            surface = "site"; kb = null; category = null; article = null;
        }
        var baseUrl = "";
        <?php if ( $base_url_enabled ) : ?>
            <?php if ( $base_url_override ) : ?>
            baseUrl = "<?php echo $base_url_override; ?>";
            <?php else : ?>
            baseUrl = window.location.origin;
            <?php endif; ?>
        <?php endif; ?>
        window.copilotPageContext = { surface:surface, kb:kb, category:category, article:article,
            path:path, title:pageTitle, language:document.documentElement.lang||"en",
            baseUrl:baseUrl, pageContent:"" };
    })();
    </script>
    <?php
}

/* ── Overlay HTML ── */
add_action( 'wp_footer', 'ihc_overlay_html' );
function ihc_overlay_html() {
    if ( ! ihc_is_active() ) return;
    $ihc_title       = get_option( 'ihc_header_title',    'Icertis Help Center' );
    $ihc_letter      = get_option( 'ihc_logo_letter',     'H' );
    $ihc_disclaimer  = get_option( 'ihc_disclaimer_text', 'Answers are drawn from our Help Center articles.' );
    $ihc_fab_logo    = get_option( 'ihc_fab_logo_url',    '' );
    $ihc_header_logo = get_option( 'ihc_header_logo_url', '' );
    $ihc_logo_height = max( 20, min( 56, (int) get_option( 'ihc_header_logo_height', 32 ) ) );
    include IHC_DIR . 'templates/overlay.php';
}

/* ── Shortcode ── */
add_shortcode( 'icertis_help_toggle', 'ihc_header_toggle_shortcode' );
function ihc_header_toggle_shortcode() {
    ob_start();
    include IHC_DIR . 'templates/header-toggle.php';
    return ob_get_clean();
}

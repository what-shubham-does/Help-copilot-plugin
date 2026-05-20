<?php
/**
 * Overlay template — v1.3.6
 * Variables set by ihc_overlay_html():
 *   $ihc_title       — header title text
 *   $ihc_letter      — logo mark letter
 *   $ihc_disclaimer  — disclaimer bar text
 *   $ihc_fab_logo    — mobile FAB logo URL (optional)
 *   $ihc_header_logo — header logo image URL (optional)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!-- ── Mobile floating trigger ───────────────────── -->
<button class="hc-mobile-fab" id="hcMobileFab" aria-label="Ask the Help Copilot">
    <?php if ( ! empty( $ihc_fab_logo ) ) : ?>
    <img src="<?php echo esc_url( $ihc_fab_logo ); ?>" alt="" aria-hidden="true" />
    <?php else : ?>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <path d="M12 3C7.03 3 3 6.58 3 11c0 2.12.9 4.05 2.37 5.47L4 21l4.7-1.55C9.99 19.8 10.98 20 12 20c4.97 0 9-3.58 9-9s-4.03-8-9-8z" fill="currentColor"/>
    </svg>
    <?php endif; ?>
</button>

<!-- ── Backdrop ──────────────────────────────────── -->
<div class="hc-ask-backdrop" id="hcAskBackdrop"></div>

<!-- ── Ask overlay ───────────────────────────────── -->
<div class="hc-ask-overlay" id="hcAskOverlay">

    <div id="hcResizeHandle" class="hc-resize-handle" title="Drag to resize" aria-hidden="true"></div>

    <div class="hc-ask-header">

        <div class="hc-ask-logo">
            <?php if ( ! empty( $ihc_header_logo ) ) : ?>
                <div class="hc-ask-logo-mark" style="background:rgba(255,255,255,0.15); border-color:rgba(255,255,255,0.25); padding:2px;">
                    <img src="<?php echo esc_url( $ihc_header_logo ); ?>" alt="" style="height:20px; width:auto; display:block;" />
                </div>
            <?php else : ?>
                <div class="hc-ask-logo-mark"><?php echo esc_html( $ihc_letter ?: 'H' ); ?></div>
            <?php endif; ?>
            <span><?php echo esc_html( $ihc_title ?: 'Icertis Help Center' ); ?></span>
        </div>

        <div class="hc-toggle-pill hc-overlay-toggle" id="hcOverlayToggle">
            <button class="hc-toggle-opt" data-mode="browse">Browse</button>
            <button class="hc-toggle-opt active" data-mode="ask">Ask</button>
        </div>

        <!-- Context badge removed — page context now travels with message text -->

        <div class="hc-ask-controls">
            <button class="hc-btn-new-chat" id="hcNewChat" title="Start a new conversation">
                <span style="font-size:14px;">+</span> New chat
            </button>
            <button class="hc-btn-close" id="hcClose" aria-label="Close">&#215;</button>
        </div>

    </div>

    <div class="hc-ask-body">

        <!-- Loading spinner -->
        <div class="hc-loading" id="hcLoading">
            <div class="hc-spinner"></div>
        </div>

        <!-- Error state -->
        <div class="hc-error hidden" id="hcError">
            <div class="hc-error-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                    <path d="M12 3C7.03 3 3 6.58 3 11c0 2.12.9 4.05 2.37 5.47L4 21l4.7-1.55C9.99 19.8 10.98 20 12 20c4.97 0 9-3.58 9-9s-4.03-8-9-8z" fill="#C2D9F8"/>
                    <path d="M12 7v5M12 14.5v.5" stroke="#0057B8" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </div>
            <p class="hc-error-msg">The assistant isn't available right now.</p>
            <button class="hc-btn-retry" id="hcRetry">Try again</button>
        </div>

        <!-- ── Landing page ────────────────────────────────── -->
        <div id="hcLanding" class="hc-landing" style="display:none;" aria-label="Help Copilot start">

            <div class="hc-land-welcome">
                <div class="hc-land-avatar"><?php echo esc_html( $ihc_letter ?: 'H' ); ?></div>
                <h2>Hi, I'm your Help Copilot</h2>
                <p>Ask a question, choose a suggestion, or type below.</p>
            </div>

            <!-- On this page — docs and release surfaces only -->
            <div id="hcPageSection" class="hc-land-section" style="display:none;">
                <div class="hc-land-section-label">On this page</div>
                <div class="hc-page-tag">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span id="hcPageTitleTag">Loading&hellip;</span>
                </div>
                <div class="hc-land-chips">
                    <!-- Summarize pill — hc-chip-meta populated by JS from breadcrumb -->
                    <button id="hcSummarizePill" class="hc-land-chip hc-land-chip--accent">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 6h16M4 10h16M4 14h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span>Summarize this page</span>
                        <span class="hc-chip-meta" id="hcSummarizeMeta"></span>
                    </button>
                    <!-- What's new chip — label and meta populated by JS -->
                    <button id="hcWhatsnewChip" class="hc-land-chip" style="display:none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 2l2.09 6.26H21l-5.47 3.97 2.09 6.26L12 14.52l-5.62 3.97 2.09-6.26L3 8.26h6.91L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                        </svg>
                        <span id="hcWhatsnewLabel">What's new in this area?</span>
                        <span class="hc-chip-meta" id="hcWhatsnewMeta"></span>
                    </button>
                </div>
            </div>

            <!-- Common questions — KB-specific or global fallback, built by JS -->
            <div class="hc-land-section" id="hcCommonSection">
                <div class="hc-land-section-label">Common questions</div>
                <div id="hcCommonChips" class="hc-land-chips"></div>
            </div>

        </div><!-- /hcLanding -->

        <!-- Webchat container -->
        <div id="hcWebchat" class="hc-webchat-container"></div>

        <!-- Legacy iframe fallback -->
        <iframe
            id="hcCopilotFrame"
            src=""
            loading="lazy"
            title="<?php echo esc_attr( $ihc_title ?: 'Icertis Help Copilot' ); ?>">
        </iframe>

    </div><!-- /hc-ask-body -->

    <!-- ── Landing compose ────────────────────────────────
         Shown alongside the landing page so users can type
         directly without selecting a chip. Hidden when the
         landing is dismissed and webchat takes over.
    ──────────────────────────────────────────────────── -->
    <div class="hc-land-compose" id="hcLandCompose" style="display:none;">
        <textarea
            id="hcLandInput"
            class="hc-land-textarea"
            placeholder="Or type your question…"
            rows="1"
            maxlength="500"
            aria-label="Type your question"></textarea>
        <button id="hcLandSend" class="hc-land-send-btn" aria-label="Send">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    <div class="hc-powered-bar">
        <?php echo esc_html( $ihc_disclaimer ); ?>
    </div>

</div>

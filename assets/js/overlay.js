document.addEventListener("DOMContentLoaded", function () {

    /* ── Settings from PHP ─────────────────────── */
    var SETTINGS           = window.ihcSettings || {};
    var DIRECT_LINE_SECRET = SETTINGS.directLineSecret || "";
    var COPILOT_SRC        = SETTINGS.copilotSrc       || "";
    var LOGO_LETTER        = SETTINGS.logoLetter        || "H";
    var ACCENT_COLOR       = SETTINGS.accentColor       || "#010172";
    var USE_WEBCHAT        = !!DIRECT_LINE_SECRET;
    var LOAD_TIMEOUT       = 12000;
    var kbQuestions        = SETTINGS.kbQuestions       || {};

    /* ── Conversation persistence ──────────────────────────
     * Three-way resumption on reconnect:
     *   1. token + conversationId
     *   2. secret + conversationId
     *   3. secret only — fresh conversation
     * TTL: 25 min. Scope: sessionStorage (per tab).
     * ─────────────────────────────────────────────────────*/
    var CONV_ID_KEY     = "ihc_conv_id";
    var CONV_TOKEN_KEY  = "ihc_conv_token";
    var CONV_EXPIRY_KEY = "ihc_conv_expiry";
    var TRANSCRIPT_KEY  = "ihc_transcript";
    var CONV_TTL_MS     = 25 * 60 * 1000;

    function getSavedConv() {
        try {
            var id     = sessionStorage.getItem(CONV_ID_KEY);
            var token  = sessionStorage.getItem(CONV_TOKEN_KEY);
            var expiry = parseInt(sessionStorage.getItem(CONV_EXPIRY_KEY) || "0", 10);
            if (id && Date.now() < expiry) return { id: id, token: token };
            clearSavedConv();
        } catch (e) {}
        return null;
    }

    function saveConv(id, token) {
        try {
            sessionStorage.setItem(CONV_ID_KEY, id);
            if (token && typeof token === "string" && token !== "null" && token !== "undefined") {
                sessionStorage.setItem(CONV_TOKEN_KEY, token);
            }
            sessionStorage.setItem(CONV_EXPIRY_KEY, String(Date.now() + CONV_TTL_MS));
        } catch (e) {}
    }

    function clearSavedConv() {
        try {
            sessionStorage.removeItem(CONV_ID_KEY);
            sessionStorage.removeItem(CONV_TOKEN_KEY);
            sessionStorage.removeItem(CONV_EXPIRY_KEY);
            sessionStorage.removeItem(TRANSCRIPT_KEY);
        } catch (e) {}
    }

    /* ── Transcript persistence ────────────────────────────
     * Direct Line does not replay history on reconnect.
     * Activities stored client-side and replayed into the
     * store on resume — zero credit cost.
     * ─────────────────────────────────────────────────────*/
    function saveActivity(activity) {
        try {
            var raw    = sessionStorage.getItem(TRANSCRIPT_KEY);
            var stored = raw ? JSON.parse(raw) : [];
            stored.push(activity);
            if (stored.length > 50) stored = stored.slice(-50);
            sessionStorage.setItem(TRANSCRIPT_KEY, JSON.stringify(stored));
        } catch (e) {}
    }

    function getSavedTranscript() {
        try {
            var raw = sessionStorage.getItem(TRANSCRIPT_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) { return []; }
    }

    /* ── Page content extraction ───────────────── */
    if (SETTINGS.contentEnabled &&
        window.copilotPageContext &&
        window.copilotPageContext.surface === "documentation") {
        var selector  = SETTINGS.contentSelector || "article .entry-content, .entry-content, article";
        var contentEl = document.querySelector(selector);
        if (contentEl) {
            window.copilotPageContext.pageContent = (contentEl.innerText || contentEl.textContent || "")
                .replace(/\s+/g, " ").trim().substring(0, 6000);
        }
    }

    /* ── DOM references ────────────────────────── */
    var overlay       = document.getElementById("hcAskOverlay");
    var backdrop      = document.getElementById("hcAskBackdrop");
    var webchatEl     = document.getElementById("hcWebchat");
    var iframe        = document.getElementById("hcCopilotFrame");
    var loading       = document.getElementById("hcLoading");
    var error         = document.getElementById("hcError");
    var retryBtn      = document.getElementById("hcRetry");
    var newChatBtn    = document.getElementById("hcNewChat");
    var closeBtn      = document.getElementById("hcClose");
    var headerToggle  = document.getElementById("hcHeaderToggle");
    var overlayToggle = document.getElementById("hcOverlayToggle");
    var mobileFab     = document.getElementById("hcMobileFab");
    var resizeHandle  = document.getElementById("hcResizeHandle");
    var landCompose   = document.getElementById("hcLandCompose");
    var landInput     = document.getElementById("hcLandInput");
    var landSend      = document.getElementById("hcLandSend");
    var preview       = document.getElementById("hcPreview");
    var previewTitle  = document.getElementById("hcPreviewTitle");
    var previewMsg    = document.getElementById("hcPreviewMsg");
    var previewPills  = document.getElementById("hcPreviewPills");
    var previewCancel = document.getElementById("hcPreviewCancel");
    var previewSend   = document.getElementById("hcPreviewSend");
    var whatsnewBadge = document.getElementById("hcWhatsnewBadge");

    /* ── State ─────────────────────────────────── */
    var iframeLoaded       = false;
    var webchatInitialised = false;
    var directLineInstance = null;
    var storeInstance      = null;
    var loadTimer          = null;
    var isResuming         = false;
    var isReplaying        = false;
    var landingVisible     = false;

    /* ════════════════════════════════════════════
       LANDING PAGE
    ════════════════════════════════════════════ */

    function showLanding() {
        var landing = document.getElementById("hcLanding");
        if (landing)     { landing.style.display = "flex"; landingVisible = true; }
        if (landCompose) landCompose.style.display = "flex";
        if (webchatEl)   webchatEl.style.display   = "none";
        if (overlay)     overlay.classList.add("hc-landing-active");
    }

    function hideLanding() {
        var landing = document.getElementById("hcLanding");
        if (landing)     { landing.style.display = "none"; landingVisible = false; }
        if (landCompose) landCompose.style.display = "none";
        if (overlay)     overlay.classList.remove("hc-landing-active");
    }

    function hidePreview() {
        if (preview) preview.style.display = "none";
        var landing = document.getElementById("hcLanding");
        if (landing)     landing.style.display     = "flex";
        if (landCompose) landCompose.style.display  = "flex";
    }

    /* ── Breadcrumb helpers ─────────────────────── */
    function getBreadcrumbItems() {
        var sel    = SETTINGS.breadcrumbSelector || ".breadcrumbs a, .breadcrumb a, [aria-label=\"Breadcrumbs\"] a, .site-breadcrumb a";
        var crumbs = Array.from(document.querySelectorAll(sel));
        var items  = crumbs
            .map(function (a) { return (a.textContent || "").trim(); })
            .filter(function (t) { return t && t.toLowerCase() !== "home"; });
        var ctx   = window.copilotPageContext || {};
        var title = ctx.title || "";
        if (title && (!items.length || items[items.length - 1] !== title)) items.push(title);
        return items;
    }

    function getBreadcrumbContext() {
        var items = getBreadcrumbItems();
        if (items.length) return items.join(" > ");
        var ctx = window.copilotPageContext || {};
        return ctx.title || (ctx.kb || "") + (ctx.category ? " / " + ctx.category : "");
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    /* ── Per-KB question selection ─────────────── */
    function getQuestionsForCurrentPage() {
        var ctx = window.copilotPageContext || {};
        var kb  = ctx.kb;
        if (kb && kbQuestions[kb]) {
            var kbQs = kbQuestions[kb].filter(function (q) { return q && q.trim(); });
            if (kbQs.length) return kbQs;
        }
        return [SETTINGS.q1, SETTINGS.q2, SETTINGS.q3, SETTINGS.q4]
            .filter(function (q) { return q && q.trim(); });
    }

    function buildCommonChips() {
        var container = document.getElementById("hcCommonChips");
        if (!container) return;
        var questions = getQuestionsForCurrentPage();
        container.innerHTML = "";
        questions.forEach(function (q) {
            var btn       = document.createElement("button");
            btn.className = "hc-q-row";
            btn.innerHTML =
                "<div class=\"hc-q-dot\"></div>" +
                "<div class=\"hc-q-text\">" + escHtml(q) + "</div>";
            btn.addEventListener("click", function () { sendMessageAndTransition(q); });
            container.appendChild(btn);
        });
    }

    /* ── What's new preview ─────────────────────── */
    function showWhatsnewPreview() {
        var ctx   = window.copilotPageContext || {};
        var items = getBreadcrumbItems();

        var areaLabel = items.length > 0 ? items[0] : (ctx.kb || "the Help Center");
        var category  = items.length > 1 ? items[1] : (ctx.category || "");
        var pageTitle = ctx.title || "";

        var message = "Tell me what latest features are released for \"" + areaLabel + "\"?";
        if (ctx.kb)    message += "\nContext: " + ctx.kb;
        if (category)  message += ", " + category;
        if (pageTitle) message += ", " + pageTitle;

        if (previewSend) previewSend._message = message;
        if (previewTitle) previewTitle.textContent = "What\u2019s new in " + areaLabel + "?";

        if (previewMsg) {
            previewMsg.innerHTML =
                "Tell me what latest features are released for <strong>" +
                escHtml(areaLabel) + "</strong>?";
        }

        if (previewPills) {
            previewPills.innerHTML = "";
            var pills = [];
            if (areaLabel) pills.push({ cls: "hc-ctx-kb",   text: areaLabel });
            if (category)  pills.push({ cls: "hc-ctx-cat",  text: category });
            if (pageTitle) pills.push({ cls: "hc-ctx-page", text: pageTitle });
            pills.forEach(function (p) {
                var span       = document.createElement("span");
                span.className = "hc-ctx-pill " + p.cls;
                span.textContent = p.text;
                previewPills.appendChild(span);
            });
        }

        var landing = document.getElementById("hcLanding");
        if (landing)     landing.style.display     = "none";
        if (landCompose) landCompose.style.display  = "none";
        if (preview)     preview.style.display      = "flex";
    }

    function initLanding() {
        var ctx         = window.copilotPageContext || {};
        var isPageAware = ctx.surface === "documentation" ||
                          ctx.surface === "release-note"  ||
                          ctx.surface === "release-landing";

        var pageSection = document.getElementById("hcPageSection");
        if (pageSection) pageSection.style.display = isPageAware ? "block" : "none";

        if (isPageAware && ctx.title) {
            var tagEl = document.getElementById("hcPageTitleTag");
            if (tagEl) {
                tagEl.textContent = ctx.title.length > 44
                    ? ctx.title.substring(0, 41) + "\u2026"
                    : ctx.title;
            }
        }

        var items = getBreadcrumbItems();

        /* ── Summarize — full context matching What's new ── */
        var summarizePill = document.getElementById("hcSummarizePill");
        if (summarizePill) {
            var sectionLabel = items.length > 0 ? items[0] : (ctx.kb || "");
            var breadcrumb   = getBreadcrumbContext();

            summarizePill.onclick = function () {
                var msg;
                if (ctx.surface === "release-note" || ctx.surface === "release-landing") {
                    msg = "Summarize the release note titled: " + (ctx.title || "this page");
                } else {
                    msg = "Summarize the Help Center article titled: " + (ctx.title || "this page");
                }
                if (breadcrumb) msg += "\n[Context: " + breadcrumb + "]";
                var meta = [];
                if (ctx.kb)       meta.push("kb: " + ctx.kb);
                if (ctx.category) meta.push("category: " + ctx.category);
                if (meta.length)  msg += "\n[" + meta.join(", ") + "]";
                sendMessageAndTransition(msg);
            };
        }

        /* ── What's new ── */
        var whatsnewChip = document.getElementById("hcWhatsnewChip");
        if (whatsnewChip) {
            var showWhatsnew = SETTINGS.whatsnewEnabled && isPageAware;
            whatsnewChip.style.display = showWhatsnew ? "flex" : "none";
            if (showWhatsnew) {
                var areaLabel = items.length > 0 ? items[0] : (ctx.kb || "");
                if (whatsnewBadge) whatsnewBadge.textContent = areaLabel;
            }
            whatsnewChip.onclick = function () { showWhatsnewPreview(); };
        }

        buildCommonChips();
    }

    /* ── Send and transition to chat ────────────── */
    function sendMessageAndTransition(text) {
        hideLanding();
        if (preview)   preview.style.display   = "none";
        if (landInput) landInput.placeholder    = "Ask a follow-up\u2026";
        if (webchatEl) webchatEl.style.display  = "flex";
        if (storeInstance) {
            storeInstance.dispatch({
                type:    "WEB_CHAT/SEND_MESSAGE",
                payload: { text: text }
            });
        }
    }

    /* ════════════════════════════════════════════
       RESPONSE CLEANING
    ════════════════════════════════════════════ */

    function cleanBotResponse(text) {
        if (!text) return text;
        return text
            .replace(/:\s*cite:\d+\s*"Citation-\d+"/gi, "")
            .replace(/\[\s*(?:doc\s*)?\d+\s*\]/gi, "")
            .replace(/\[([^\]]+)\]\(sandbox:[^)]*\)/g, "$1")
            .replace(/\[([^\]]+)\]\(https?:\/\/[^)]*\.(?:md|pdf|docx|txt)[^)]*\)/gi, "$1")
            .replace(/\n{1,2}(?:References|Sources|Citations|Learn more):[\s\S]*$/i, "")
            .replace(/[ \t]+$/gm, "")
            .replace(/\n{3,}/g, "\n\n")
            .trim();
    }

    /* ════════════════════════════════════════════
       WEBCHAT MODE
    ════════════════════════════════════════════ */

    function initWebchat() {
        /* Dynamic SDK load — saves ~3.5 MB on page load for non-chat visitors */
        if (!window.WebChat) {
            var script     = document.createElement("script");
            script.src     = "https://cdn.botframework.com/botframework-webchat/latest/webchat.js";
            script.onload  = function () { initWebchat(); };
            script.onerror = function () { showError(); };
            document.head.appendChild(script);
            showLoading();
            return;
        }

        if (directLineInstance) {
            try { directLineInstance.end(); } catch (e) {}
            directLineInstance = null;
        }

        showLoading();

        var saved         = getSavedConv();
        var savedMessages = getSavedTranscript();

        /*
         * hasHistory: true only if the user actually sent/received messages.
         * isResuming: true if a saved conversationId exists (for connection).
         *
         * These are deliberately decoupled:
         *   - isResuming controls which Direct Line connection params to use
         *   - hasHistory controls whether to show the landing or the transcript
         *
         * This fixes the bug where opening the overlay without chatting saves
         * a conversationId, causing the landing to be skipped on the next page.
         */
        var hasHistory = savedMessages.length > 0;
        isResuming     = !!saved;

        directLineInstance = window.WebChat.createDirectLine(
            saved && saved.token
                ? { token: saved.token, conversationId: saved.id }
                : saved
                ? { secret: DIRECT_LINE_SECRET, conversationId: saved.id }
                : { secret: DIRECT_LINE_SECRET }
        );

        storeInstance = window.WebChat.createStore(
            {},
            function (store) {
                return function (next) {
                    return function (action) {

                        if (action.type === "DIRECT_LINE/CONNECT_FULFILLED") {
                            clearTimeout(loadTimer);
                            webchatInitialised = true;
                            hideLoading();

                            if (isResuming && hasHistory) {
                                /* Real resume: user had messages — skip landing, show transcript */
                                hideLanding();
                                if (webchatEl) webchatEl.style.display = "flex";
                                isReplaying = true;
                                setTimeout(function () {
                                    savedMessages.forEach(function (act) {
                                        store.dispatch({
                                            type:    "DIRECT_LINE/INCOMING_ACTIVITY",
                                            payload: { activity: act }
                                        });
                                    });
                                    isReplaying = false;
                                }, 300);
                            } else {
                                /* No messages yet — always show landing */
                                showLanding();
                            }

                            isResuming = false;

                            if (directLineInstance && directLineInstance.conversationId) {
                                saveConv(
                                    directLineInstance.conversationId,
                                    directLineInstance.token || null
                                );
                            }
                        }

                        if (action.type === "DIRECT_LINE/CONNECT_REJECTED") {
                            clearTimeout(loadTimer);
                            if (isResuming) {
                                isResuming = false;
                                clearSavedConv();
                                setTimeout(initWebchat, 300);
                            } else {
                                showError();
                            }
                        }

                        if (action.type === "DIRECT_LINE/INCOMING_ACTIVITY") {
                            var activity = action.payload && action.payload.activity;

                            if (activity && activity.type === "message" && landingVisible) {
                                hideLanding();
                                if (webchatEl) webchatEl.style.display = "flex";
                            }

                            if (activity &&
                                activity.type === "message" &&
                                activity.from &&
                                activity.from.role === "bot" &&
                                activity.text) {
                                action = Object.assign({}, action, {
                                    payload: Object.assign({}, action.payload, {
                                        activity: Object.assign({}, activity, {
                                            text: cleanBotResponse(activity.text)
                                        })
                                    })
                                });
                            }

                            if (!isReplaying &&
                                action.payload &&
                                action.payload.activity &&
                                action.payload.activity.type === "message") {
                                saveActivity(action.payload.activity);
                            }
                        }

                        return next(action);
                    };
                };
            }
        );

        /* Build landing chips for this page; if resuming with history the
           landing is hidden immediately, but initLanding is cheap to run. */
        if (!hasHistory) { initLanding(); }

        window.WebChat.renderWebChat(
            {
                directLine: directLineInstance,
                store:      storeInstance,
                locale:     "en-US",
                styleOptions: {
                    accent:                         ACCENT_COLOR,
                    backgroundColor:                "transparent",

                    bubbleBackground:               "#ECEEFB",
                    bubbleBorderColor:              "#D9DCF7",
                    bubbleBorderRadius:             10,
                    bubbleBorderStyle:              "solid",
                    bubbleBorderWidth:              1,
                    bubbleTextColor:                "#303030",
                    bubbleMaxWidth:                 9999,

                    bubbleFromUserBackground:       ACCENT_COLOR,
                    bubbleFromUserBorderColor:      ACCENT_COLOR,
                    bubbleFromUserBorderRadius:     10,
                    bubbleFromUserBorderStyle:      "solid",
                    bubbleFromUserBorderWidth:      1,
                    bubbleFromUserTextColor:        "#FFFFFF",
                    bubbleFromUserMaxWidth:         9999,

                    botAvatarInitials:              LOGO_LETTER,
                    userAvatarInitials:             "You",
                    botAvatarBackgroundColor:       ACCENT_COLOR,
                    userAvatarBackgroundColor:      "#6b6b80",

                    sendBoxBackground:              "#F8F8FC",
                    sendBoxButtonColor:             ACCENT_COLOR,
                    sendBoxButtonHoverColor:        ACCENT_COLOR,
                    sendBoxHeight:                  48,
                    sendBoxTextColor:               "#303030",
                    sendBoxPlaceholderColor:        "#8888a0",
                    hideUploadButton:               true,

                    suggestedActionBackground:      "#ECEEFB",
                    suggestedActionBorderColor:     "#D9DCF7",
                    suggestedActionBorderRadius:    8,
                    suggestedActionTextColor:       ACCENT_COLOR,

                    groupTimestamp:                 15000,
                    timestampColor:                 "#8888a0",
                    timestampFormat:                "relative",
                    markdownRespectCRLF:            true,
                }
            },
            webchatEl
        );

        loadTimer = setTimeout(function () {
            if (!webchatInitialised) showError();
        }, LOAD_TIMEOUT);
    }

    /* ════════════════════════════════════════════
       LEGACY IFRAME MODE
    ════════════════════════════════════════════ */

    function loadIframe() {
        if (!COPILOT_SRC) { showError(); return; }
        iframe.style.display = "block";
        iframe.src = COPILOT_SRC;
        loadTimer = setTimeout(showError, LOAD_TIMEOUT);
        iframe.addEventListener("load", function () {
            clearTimeout(loadTimer);
            iframeLoaded = true;
            hideLoading();
        }, { once: true });
    }

    /* ════════════════════════════════════════════
       SHARED LOAD / STATE
    ════════════════════════════════════════════ */

    function startLoad() {
        showLoading();
        hideError();
        if (USE_WEBCHAT) { initWebchat(); } else { loadIframe(); }
    }

    function resetAndReload() {
        clearSavedConv();
        clearTimeout(loadTimer);
        webchatInitialised = false;
        iframeLoaded       = false;
        landingVisible     = false;
        isReplaying        = false;

        var landing = document.getElementById("hcLanding");
        if (landing)     landing.style.display     = "none";
        if (landCompose) landCompose.style.display  = "none";
        if (preview)     preview.style.display      = "none";
        if (landInput)   landInput.placeholder       = "Ask a question\u2026";

        if (USE_WEBCHAT) {
            if (directLineInstance) {
                try { directLineInstance.end(); } catch (e) {}
                directLineInstance = null;
            }
            storeInstance = null;
        } else {
            if (iframe) iframe.src = "";
        }

        setTimeout(startLoad, 100);
    }

    function showLoading() { if (loading) loading.classList.remove("hidden"); }
    function hideLoading() { if (loading) loading.classList.add("hidden"); }

    function showError() {
        hideLoading();
        if (webchatEl) webchatEl.style.display = "none";
        if (iframe)    iframe.style.display    = "none";
        if (error)     error.classList.remove("hidden");
    }

    function hideError() { if (error) error.classList.add("hidden"); }

    /* ════════════════════════════════════════════
       OPEN / CLOSE
    ════════════════════════════════════════════ */

    function openAskMode() {
        overlay.classList.add("visible");
        backdrop.classList.add("visible");
        document.body.classList.add("hc-overlay-open");
        /* Body scroll lock is CSS-only and mobile-only (see overlay.css @media) */
        updateToggleState(headerToggle, "ask");
        updateToggleState(overlayToggle, "ask");
        if (!webchatInitialised && !iframeLoaded) startLoad();
    }

    function closeAskMode() {
        overlay.classList.remove("visible");
        backdrop.classList.remove("visible");
        document.body.classList.remove("hc-overlay-open");
        updateToggleState(headerToggle, "browse");
    }

    function updateToggleState(container, mode) {
        if (!container) return;
        container.querySelectorAll(".hc-toggle-opt").forEach(function (opt) {
            opt.classList.toggle("active", opt.dataset.mode === mode);
        });
    }

    /* ════════════════════════════════════════════
       EVENT LISTENERS
    ════════════════════════════════════════════ */

    if (headerToggle) {
        headerToggle.addEventListener("click", function (e) {
            var opt = e.target.closest(".hc-toggle-opt");
            if (!opt) return;
            opt.dataset.mode === "ask" ? openAskMode() : closeAskMode();
        });
    }

    if (overlayToggle) {
        overlayToggle.addEventListener("click", function (e) {
            var opt = e.target.closest(".hc-toggle-opt");
            if (opt && opt.dataset.mode === "browse") closeAskMode();
        });
    }

    if (mobileFab) mobileFab.addEventListener("click", openAskMode);
    if (closeBtn)  closeBtn.addEventListener("click",  closeAskMode);
    if (backdrop)  backdrop.addEventListener("click",  closeAskMode);

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && overlay && overlay.classList.contains("visible")) closeAskMode();
    });

    if (retryBtn)   retryBtn.addEventListener("click",  resetAndReload);
    if (newChatBtn) newChatBtn.addEventListener("click", resetAndReload);

    if (previewCancel) previewCancel.addEventListener("click", hidePreview);

    if (previewSend) previewSend.addEventListener("click", function () {
        var msg = previewSend._message || "";
        if (!msg) return;
        hidePreview();
        sendMessageAndTransition(msg);
    });

    if (landSend && landInput) {
        landSend.addEventListener("click", function () {
            var text = (landInput.value || "").trim();
            if (text) {
                landInput.value = "";
                landInput.style.height = "auto";
                sendMessageAndTransition(text);
            }
        });
        landInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                var text = (landInput.value || "").trim();
                if (text) {
                    landInput.value = "";
                    landInput.style.height = "auto";
                    sendMessageAndTransition(text);
                }
            }
        });
        landInput.addEventListener("input", function () {
            this.style.height = "auto";
            this.style.height = Math.min(this.scrollHeight, 96) + "px";
        });
    }

    /* ════════════════════════════════════════════
       RESIZE HANDLE
    ════════════════════════════════════════════ */

    if (resizeHandle && overlay) {
        var isResizing = false;
        var rsStartX, rsStartY, rsStartW, rsStartH;

        resizeHandle.addEventListener("mousedown", function (e) {
            isResizing = true;
            rsStartX   = e.clientX;
            rsStartY   = e.clientY;
            rsStartW   = parseInt(getComputedStyle(overlay).width,  10);
            rsStartH   = parseInt(getComputedStyle(overlay).height, 10);
            document.body.style.userSelect = "none";
            document.body.style.cursor     = "nw-resize";
            e.preventDefault();
        });

        document.addEventListener("mousemove", function (e) {
            if (!isResizing) return;
            var newW = Math.max(300, Math.min(rsStartW + (rsStartX - e.clientX), window.innerWidth  - 48));
            var newH = Math.max(380, Math.min(rsStartH + (rsStartY - e.clientY), window.innerHeight - 48));
            overlay.style.width  = newW + "px";
            overlay.style.height = newH + "px";
        });

        document.addEventListener("mouseup", function () {
            if (!isResizing) return;
            isResizing                     = false;
            document.body.style.userSelect = "";
            document.body.style.cursor     = "";
        });
    }

});

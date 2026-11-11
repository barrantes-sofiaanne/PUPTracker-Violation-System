@verbatim
/* =========================================================
   PUPTRACKER — PORTAL LOGIN (shared by Student, Security,
   OSS Staff and Administrator)
   Included inline by layouts/portal-login.blade.php.
   Everything is scoped under .spl-* ; the active module sets
   its colours through the .spl-theme-* classes below.
   ========================================================= */

/* -------------------------
   1. MODULE THEMES
   ------------------------- */
.spl-theme-student {
    --spl-g1: rgba(106, 10, 22, 0.86);
    --spl-g2: rgba(122, 12, 26, 0.68);
    --spl-g3: rgba(84, 8, 18, 0.90);
    --spl-swoosh: 70, 6, 14;
    --spl-brand-base: #5a0912;

    --spl-panel: #fff4f0;
    --spl-icon-bg: #f8dcdb;
    --spl-accent: #7a0b18;
    --spl-btn-from: #96101d;
    --spl-btn-to: #8a0009;
    --spl-btn-shadow: 122, 11, 24;
    --spl-ink: #6b0a14;
    --spl-sub: #c03a3a;
    --spl-muted: #85706f;
    --spl-border: #ecd3d0;
}

.spl-theme-security {
    --spl-g1: rgba(10, 38, 96, 0.88);
    --spl-g2: rgba(16, 64, 146, 0.66);
    --spl-g3: rgba(6, 24, 68, 0.92);
    --spl-swoosh: 4, 16, 52;
    --spl-brand-base: #0a2a6a;

    --spl-panel: #eef4fd;
    --spl-icon-bg: #d8e5f9;
    --spl-accent: #0b3d91;
    --spl-btn-from: #1655b8;
    --spl-btn-to: #0b3d91;
    --spl-btn-shadow: 11, 61, 145;
    --spl-ink: #0b2a5e;
    --spl-sub: #2f63b5;
    --spl-muted: #66748e;
    --spl-border: #cddbf0;
}

.spl-theme-oss {
    --spl-g1: rgba(8, 72, 38, 0.88);
    --spl-g2: rgba(14, 104, 54, 0.66);
    --spl-g3: rgba(5, 48, 25, 0.92);
    --spl-swoosh: 3, 36, 18;
    --spl-brand-base: #0a4a26;

    --spl-panel: #eef8f0;
    --spl-icon-bg: #d3eedb;
    --spl-accent: #0b6a2e;
    --spl-btn-from: #13873f;
    --spl-btn-to: #0b6a2e;
    --spl-btn-shadow: 11, 106, 46;
    --spl-ink: #0a4a22;
    --spl-sub: #2e8049;
    --spl-muted: #65806d;
    --spl-border: #cde5d4;
}

.spl-theme-admin {
    --spl-g1: rgba(62, 18, 116, 0.88);
    --spl-g2: rgba(92, 32, 156, 0.66);
    --spl-g3: rgba(42, 10, 84, 0.92);
    --spl-swoosh: 30, 6, 64;
    --spl-brand-base: #3f1272;

    --spl-panel: #f6f1fc;
    --spl-icon-bg: #e3d6f5;
    --spl-accent: #5b21b6;
    --spl-btn-from: #6f2fcb;
    --spl-btn-to: #5a1d99;
    --spl-btn-shadow: 90, 29, 153;
    --spl-ink: #3f1470;
    --spl-sub: #7a4cb0;
    --spl-muted: #7a6f8e;
    --spl-border: #dacdee;
}

/* -------------------------
   2. TOKENS & RESETS
   ------------------------- */
.spl-page {
    --spl-gold: #e8b934;
    --spl-gold-light: #f7d046;
    --spl-serif: "Playfair Display", Georgia, "Times New Roman", serif;
    --spl-sans: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;

    min-height: 100vh;
    min-height: 100svh;
    background: var(--spl-brand-base);
    color: var(--spl-ink);
    font-family: var(--spl-sans);
}

.spl-page *,
.spl-page *::before,
.spl-page *::after {
    box-sizing: border-box;
    font-family: inherit; /* beats the global "* { font-family }" in app.css */
}

.spl-page h1,
.spl-page h2,
.spl-page p {
    margin: 0;
}

.spl-page a {
    text-decoration: none;
}

.spl-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* -------------------------
   3. FULL-SCREEN SPLIT LAYOUT
   ------------------------- */
.spl-card {
    position: relative;
    isolation: isolate;
    display: grid;
    grid-template-columns: 49% 51%;

    width: 100%;
    min-height: 100vh;
    min-height: 100svh;
    overflow: hidden;
    background: var(--spl-panel);

    animation: spl-fade 0.5s ease both;
}

@keyframes spl-fade {
    from { opacity: 0; }
    to   { opacity: 1; }
}

/* -------------------------
   4. LEFT — BRAND PANEL
   ------------------------- */
.spl-brand {
    position: relative;
    z-index: 1;

    display: flex;
    flex-direction: column;

    padding: clamp(28px, 3.6vw, 72px);
    padding-right: clamp(64px, 7vw, 120px);   /* clear the curved edge */

    color: #fff;
    background-color: var(--spl-brand-base);
    background-image:
        linear-gradient(180deg, var(--spl-g1) 0%, var(--spl-g2) 42%, var(--spl-g3) 100%),
        var(--spl-photo);
    background-size: cover;
    background-position: center;
}

.spl-brand::after {
    content: "";
    position: absolute;
    left: -25%;
    bottom: -18%;
    width: 120%;
    height: 48%;
    z-index: -1;
    background: radial-gradient(ellipse at 20% 100%,
        rgba(var(--spl-swoosh), 0.85) 0%,
        rgba(var(--spl-swoosh), 0.55) 45%,
        transparent 72%);
    pointer-events: none;
}

.spl-logo {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    gap: 12px;
    color: #fff;
}

.spl-logo img {
    display: block;
    width: clamp(40px, 3.2vw, 56px);
    height: auto;
    flex: 0 0 auto;
    filter: drop-shadow(0 3px 8px rgba(0, 0, 0, 0.3));
}

.spl-logo-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    line-height: 1.1;
}

.spl-logo-text strong {
    font-family: var(--spl-serif);
    font-size: clamp(1.15rem, 1.6vw, 1.5rem);
    font-weight: 700;
    letter-spacing: 0.01em;
}

.spl-logo-text small {
    font-size: clamp(0.72rem, 0.9vw, 0.88rem);
    font-weight: 500;
    color: rgba(255, 255, 255, 0.84);
}

.spl-brand-body {
    margin-top: clamp(36px, 10vh, 120px);
    margin-bottom: auto;
}

.spl-campus {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    max-width: 40ch;
    margin-bottom: 22px !important;

    color: var(--spl-gold);
    font-size: clamp(0.66rem, 0.8vw, 0.82rem);
    font-weight: 600;
    line-height: 1.55;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.spl-campus .spl-dash {
    margin-top: 0.7em;
}

.spl-dash {
    display: block;
    flex: 0 0 34px;
    width: 34px;
    height: 2px;
    background: var(--spl-gold);
}

.spl-title {
    font-family: var(--spl-serif);
    font-size: clamp(2.5rem, 4.8vw, 4.6rem);
    font-weight: 700;
    line-height: 1.04;
    letter-spacing: -0.01em;
    color: #fff;
}

.spl-title--long {
    font-size: clamp(2.1rem, 3.9vw, 3.8rem);
}

.spl-title span {
    display: block;
    color: var(--spl-gold-light);
}

.spl-desc {
    max-width: 24ch;
    margin-top: 20px !important;

    font-size: clamp(1rem, 1.5vw, 1.3rem);
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.94);
}

.spl-tagline {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 32px !important;

    font-family: var(--spl-serif);
    font-style: italic;
    font-weight: 500;
    font-size: clamp(1rem, 1.2vw, 1.2rem);
    color: rgba(255, 244, 234, 0.95);
}

/* -------------------------
   5. RIGHT — SIGN-IN PANEL
   ------------------------- */
.spl-panel {
    position: relative;
    z-index: 2;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: clamp(28px, 4vw, 72px);
    background: var(--spl-panel);
}

.spl-curve {
    position: absolute;
    top: 0;
    left: -59px;
    width: 60px;
    height: 100%;
    overflow: visible;
    pointer-events: none;
}

.spl-curve--h {
    display: none;
}

.spl-curve-fill {
    fill: var(--spl-panel);
}

.spl-curve-line {
    fill: none;
    stroke: var(--spl-gold);
    stroke-width: 3px;
    stroke-linecap: round;
    filter: drop-shadow(0 0 6px rgba(232, 185, 52, 0.35));
}

.spl-panel-inner {
    position: relative;
    width: 100%;
    max-width: clamp(340px, 30vw, 460px);
    text-align: center;
}

.spl-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 68px;
    height: 68px;
    margin-bottom: 20px;

    border-radius: 50%;
    background: var(--spl-icon-bg);
    color: var(--spl-accent);
    font-size: 1.75rem;
}

.spl-heading {
    font-family: var(--spl-serif);
    font-size: clamp(1.9rem, 3vw, 2.5rem);
    font-weight: 700;
    line-height: 1.15;
    color: var(--spl-ink);
}

.spl-subtitle {
    margin-top: 10px !important;
    margin-bottom: 28px !important;

    font-size: 0.95rem;
    color: var(--spl-sub);
}

/* Alerts */
.spl-error {
    display: flex;
    align-items: flex-start;
    gap: 10px;

    margin-bottom: 16px;
    padding: 12px 14px;

    text-align: left;
    font-size: 0.875rem;
    line-height: 1.45;
    color: #7a1020;
    background: #fde4e4;
    border: 1px solid #f3b9bd;
    border-radius: 10px;
}

.spl-error i {
    margin-top: 2px;
    color: #b3202f;
}

/* -------------------------
   6. FORM
   ------------------------- */
.spl-form {
    text-align: left;
}

.spl-field-group {
    margin-bottom: 14px;
}

.spl-field {
    position: relative;
    display: flex;
    align-items: center;
}

.spl-field-icon {
    position: absolute;
    left: 16px;
    font-size: 1.05rem;
    color: var(--spl-muted);
    pointer-events: none;
}

.spl-field input {
    width: 100%;
    height: 52px;
    padding: 0 16px 0 46px;

    font-size: 0.95rem;
    color: #2c1a1c;
    background: #fff;
    border: 1px solid var(--spl-border);
    border-radius: 12px;
    outline: none;

    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}

.spl-field input::placeholder {
    color: var(--spl-muted);
    opacity: 0.85;
}

.spl-field input:focus {
    border-color: var(--spl-accent);
    box-shadow: 0 0 0 4px rgba(var(--spl-btn-shadow), 0.14);
}

.spl-field.has-error input {
    border-color: #c0343f;
}

.spl-field--password input {
    padding-right: 50px;
}

.spl-toggle {
    position: absolute;
    right: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 40px;
    height: 40px;

    color: var(--spl-muted);
    font-size: 1.1rem;
    background: transparent;
    border: 0;
    border-radius: 10px;
    cursor: pointer;
}

.spl-toggle:hover {
    color: var(--spl-accent);
}

.spl-field-error {
    margin-top: 6px !important;
    font-size: 0.8rem;
    color: #b3202f;
}

.spl-row {
    display: flex;
    justify-content: flex-end;
    margin: 2px 0 18px;
}

.spl-forgot {
    padding: 4px 2px;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--spl-ink);
}

.spl-forgot:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
}

/* Buttons */
.spl-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    width: 100%;
    min-height: 54px;
    padding: 0 22px;

    color: #fff !important;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;

    background: linear-gradient(180deg, var(--spl-btn-from) 0%, var(--spl-btn-to) 100%);
    border: 0;
    border-radius: 12px;
    box-shadow: 0 12px 24px -8px rgba(var(--spl-btn-shadow), 0.55);

    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

.spl-btn i {
    font-size: 1.1rem;
    transition: transform 0.18s ease;
}

.spl-btn:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 16px 28px -8px rgba(var(--spl-btn-shadow), 0.6);
}

.spl-btn:hover i {
    transform: translateX(4px);
}

.spl-btn:active {
    transform: translateY(0);
}

.spl-btn.is-busy {
    pointer-events: none;
    filter: saturate(0.8) brightness(0.95);
}

.spl-btn.is-busy i {
    display: none;
}

.spl-btn.is-busy::after {
    content: "";
    width: 18px;
    height: 18px;
    border: 2px solid rgba(255, 255, 255, 0.45);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spl-spin 0.7s linear infinite;
}

@keyframes spl-spin {
    to { transform: rotate(360deg); }
}

/* Secondary (outlined) button — PUP IDP */
.spl-btn--ghost {
    justify-content: center;
    min-height: 52px;

    color: var(--spl-ink) !important;
    background: #fff;
    border: 1px solid var(--spl-border);
    box-shadow: 0 6px 16px -10px rgba(var(--spl-btn-shadow), 0.4);
}

.spl-btn--ghost i {
    color: var(--spl-accent);
}

.spl-btn--ghost:hover {
    filter: none;
    background: #fff;
    border-color: var(--spl-accent);
    box-shadow: 0 10px 20px -10px rgba(var(--spl-btn-shadow), 0.5);
}

.spl-btn--ghost:hover i {
    transform: none;
}

.spl-btn--ghost.is-busy::after {
    border-color: rgba(var(--spl-btn-shadow), 0.25);
    border-top-color: var(--spl-accent);
}

.spl-or {
    display: flex;
    align-items: center;
    gap: 14px;
    margin: 18px 0;

    font-size: 0.8rem;
    color: var(--spl-muted);
}

.spl-or::before,
.spl-or::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--spl-border);
}

.spl-note {
    margin-top: 18px !important;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--spl-muted);
}

.spl-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    margin-top: 26px;
    padding: 10px 6px;

    font-size: 0.95rem;
    font-weight: 600;
    color: var(--spl-ink);
}

.spl-back:hover {
    text-decoration: underline;
    text-underline-offset: 4px;
}

.spl-back i {
    transition: transform 0.18s ease;
}

.spl-back:hover i {
    transform: translateX(-3px);
}

.spl-btn:focus-visible,
.spl-back:focus-visible,
.spl-forgot:focus-visible,
.spl-toggle:focus-visible,
.spl-logo:focus-visible {
    outline: 3px solid var(--spl-gold);
    outline-offset: 3px;
    border-radius: 12px;
}

/* -------------------------
   7. REDIRECT OVERLAY (admin → PUP IDP)
   ------------------------- */
.spl-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;

    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;

    background: rgba(20, 8, 40, 0.55);
    backdrop-filter: blur(6px);
}

.spl-overlay[hidden] {
    display: none;
}

.spl-overlay-card {
    width: 100%;
    max-width: 380px;
    padding: 32px 28px;

    text-align: center;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.35);
}

.spl-overlay-card h3 {
    margin: 18px 0 8px;
    font-family: var(--spl-serif);
    font-size: 1.35rem;
    color: var(--spl-ink);
}

.spl-overlay-card p {
    font-size: 0.9rem;
    line-height: 1.5;
    color: var(--spl-muted);
}

.spl-overlay-card a {
    font-weight: 600;
    color: var(--spl-accent);
    text-decoration: underline !important;
}

.spl-overlay-spinner {
    width: 44px;
    height: 44px;
    margin: 0 auto;
    border: 4px solid var(--spl-icon-bg);
    border-top-color: var(--spl-accent);
    border-radius: 50%;
    animation: spl-spin 0.8s linear infinite;
}

/* -------------------------
   8. RESPONSIVE
   ------------------------- */

@media (max-width: 1024px) {
    .spl-brand {
        padding-right: 72px;
    }
}

/* ---- Stacked layout: tablets & phones (tall screens) ---- */
@media (max-width: 600px), (max-width: 860px) and (min-height: 521px) {
    .spl-card {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
    }

    .spl-brand {
        padding: 26px 28px 78px;      /* extra bottom room for the wave */
    }

    .spl-brand-body {
        margin-top: 28px;
        margin-bottom: 0;
    }

    .spl-title,
    .spl-title span {
        display: inline;
    }

    .spl-title,
    .spl-title--long {
        font-size: clamp(2.1rem, 8vw, 3rem);
    }

    .spl-desc {
        max-width: 34ch;
        margin-top: 14px !important;
    }

    .spl-tagline {
        display: none;
    }

    .spl-curve--v {
        display: none;
    }

    .spl-curve--h {
        display: block;
        top: -59px;
        left: 0;
        width: 100%;
        height: 60px;
    }

    .spl-panel {
        padding: 12px 28px 44px;
        align-items: flex-start;
    }

    .spl-panel-inner {
        max-width: 440px;
        margin: 0 auto;
    }

    .spl-icon {
        margin-top: -4px;
    }
}

/* ---- Phones ---- */
@media (max-width: 560px) {
    .spl-brand {
        padding: max(20px, env(safe-area-inset-top)) 22px 60px;
    }

    .spl-logo img {
        width: 38px;
    }

    .spl-logo-text strong {
        font-size: 1.1rem;
    }

    .spl-brand-body {
        margin-top: 22px;
    }

    .spl-campus {
        font-size: 0.62rem;
        margin-bottom: 14px !important;
    }

    .spl-desc {
        font-size: 0.95rem;
    }

    .spl-panel {
        padding: 8px 22px max(32px, env(safe-area-inset-bottom));
    }

    .spl-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
        margin-bottom: 16px;
    }

    .spl-subtitle {
        margin-bottom: 22px !important;
    }

    .spl-back {
        margin-top: 14px;
    }
}

@media (max-width: 360px) {
    .spl-brand,
    .spl-panel {
        padding-left: 16px;
        padding-right: 16px;
    }

    .spl-btn {
        padding: 0 16px;
        font-size: 0.95rem;
    }

    .spl-campus {
        letter-spacing: 0.08em;
    }
}

/* ---- Short landscape screens (phones turned sideways) ---- */
@media (max-height: 520px) and (min-width: 601px) {
    .spl-brand {
        padding: 22px 64px 22px 26px;
    }

    .spl-brand-body {
        margin-top: 18px;
    }

    .spl-campus {
        margin-bottom: 10px !important;
        font-size: 0.6rem;
    }

    .spl-title,
    .spl-title--long {
        font-size: 2.1rem;
    }

    .spl-desc {
        margin-top: 10px !important;
        font-size: 0.9rem;
    }

    .spl-tagline {
        margin-top: 14px !important;
        font-size: 0.9rem;
    }

    .spl-panel {
        padding: 20px 26px;
    }

    .spl-icon {
        width: 48px;
        height: 48px;
        margin-bottom: 8px;
        font-size: 1.25rem;
    }

    .spl-heading {
        font-size: 1.5rem;
    }

    .spl-subtitle {
        margin-top: 4px !important;
        margin-bottom: 12px !important;
    }

    .spl-field-group {
        margin-bottom: 8px;
    }

    .spl-field input {
        height: 42px;
    }

    .spl-row {
        margin-bottom: 8px;
    }

    .spl-btn {
        min-height: 44px;
    }

    .spl-btn--ghost {
        min-height: 42px;
    }

    .spl-or {
        margin: 8px 0;
    }

    .spl-note {
        margin-top: 10px !important;
    }

    .spl-back {
        margin-top: 6px;
        padding: 6px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .spl-card { animation: none; }
    .spl-btn,
    .spl-btn i,
    .spl-back i,
    .spl-field input { transition: none; }
    .spl-btn.is-busy::after,
    .spl-overlay-spinner { animation-duration: 1.6s; }
}
@endverbatim

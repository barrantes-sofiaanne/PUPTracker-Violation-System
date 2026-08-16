/**
 * PUPTracker landing page — light, dependency-free interactions.
 *
 * - Staggered entrance reveal (respects prefers-reduced-motion)
 * - Interactive feature preview (hover + keyboard)
 * - Subtle desktop-only ambient drift
 */
(function () {
    "use strict";

    var prefersReducedMotion = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)").matches
        : false;

    // Enable reveal animation only when motion is allowed. Adding this class
    // lets the CSS apply the hidden start state; without it (no-JS / reduced
    // motion) content stays fully visible. This prevents FOUC.
    if (!prefersReducedMotion) {
        document.documentElement.classList.add("js-anim");
    }

    function ready(fn) {
        if (document.readyState !== "loading") {
            fn();
        } else {
            document.addEventListener("DOMContentLoaded", fn);
        }
    }

    ready(function () {
        initReveal();
        initFeaturePreview();
        initAmbientDrift();
        initScrollReveal();
        initStickyHeader();
        initPointerGlow();
    });

    /**
     * Scroll reveal for below-fold sections. Uses IntersectionObserver so it
     * only triggers once, when the section scrolls into view. With reduced
     * motion, CSS keeps the sections visible (JS adds the hidden start state
     * only when motion is allowed).
     */
    function initScrollReveal() {
        var revealables = document.querySelectorAll("[data-scroll-reveal]");
        if (!revealables.length) {
            return;
        }

        // When reduced motion is on, leave everything visible.
        if (prefersReducedMotion || !("IntersectionObserver" in window)) {
            revealables.forEach(function (el) {
                el.classList.add("is-revealed");
            });
            return;
        }

        var observer = new IntersectionObserver(
            function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-revealed");
                        obs.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: "0px 0px -8% 0px" },
        );

        revealables.forEach(function (el) {
            observer.observe(el);
        });
    }

    /**
     * Sticky frosted-glass header: adds a subtle background + shadow once the
     * page scrolls, so the nav reads as a premium app bar without shifting
     * layout. Skipped for reduced motion (visual only).
     */
    function initStickyHeader() {
        var header = document.getElementById("lpHeader");
        if (!header) {
            return;
        }

        var onScroll = function () {
            var scrolled = window.scrollY > 8;
            header.classList.toggle("is-scrolled", scrolled);
        };

        window.addEventListener("scroll", onScroll, { passive: true });
        onScroll();
    }

    /**
     * Subtle pointer-follow ambient glow (desktop, motion allowed only).
     * One soft radial light that eases toward the cursor. Purely decorative,
     * never affects readability or pointer events.
     */
    function initPointerGlow() {
        var glow = document.querySelector(".lp-glow");
        if (!glow) {
            return;
        }

        var canHover = window.matchMedia
            ? window.matchMedia("(hover: hover) and (pointer: fine)").matches
            : false;

        if (prefersReducedMotion || !canHover) {
            return;
        }

        var raf = null;
        var x = window.innerWidth / 2;
        var y = 0;
        var tx = x;
        var ty = y;

        var loop = function () {
            x += (tx - x) * 0.08;
            y += (ty - y) * 0.08;
            glow.style.transform =
                "translate3d(" + x + "px, " + y + "px, 0) scale(1)";
            raf = null;
        };

        document.addEventListener(
            "pointermove",
            function (event) {
                tx = event.clientX;
                ty = event.clientY;
                glow.classList.add("is-visible");
                if (!raf) {
                    raf = window.requestAnimationFrame(loop);
                }
            },
            { passive: true },
        );
    }

    /**
     * Staggered entrance reveal. All [data-reveal] elements become visible
     * once the page is ready. With reduced motion, they're already visible
     * via CSS (no JS needed to force anything).
     */
    function initReveal() {
        var items = document.querySelectorAll("[data-reveal]");
        items.forEach(function (item, i) {
            item.style.setProperty("--reveal-i", String(i));
        });

        // Slight delay so the paint settles, but fast enough to not block use.
        window.requestAnimationFrame(function () {
            window.setTimeout(function () {
                document
                    .querySelector(".landing-page")
                    ?.classList.add("is-ready");
            }, 60);
        });
    }

    /**
     * Interactive feature preview: switching between feature rows updates the
     * active panel. Works with hover AND keyboard focus (Enter/Space/arrows).
     */
    function initFeaturePreview() {
        var card = document.querySelector(".lp-visual-card");
        if (!card) {
            return;
        }

        var tabs = Array.prototype.slice.call(
            card.querySelectorAll(".lp-visual-feature"),
        );
        var panels = Array.prototype.slice.call(
            card.querySelectorAll(".lp-visual-panel"),
        );

        function activate(feature) {
            var tab = tabs.find(function (t) {
                return t.dataset.feature === feature;
            });
            if (!tab) {
                return;
            }

            tabs.forEach(function (t) {
                var isActive = t === tab;
                t.classList.toggle("is-active", isActive);
                t.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            panels.forEach(function (panel) {
                var isActive = panel.id === tab.getAttribute("aria-controls");
                panel.classList.toggle("is-open", isActive);
                panel.hidden = !isActive;
            });
        }

        tabs.forEach(function (tab) {
            // Mouse interaction
            tab.addEventListener("mouseenter", function () {
                activate(tab.dataset.feature);
            });
            tab.addEventListener("focus", function () {
                activate(tab.dataset.feature);
            });
            // Touch / click (mobile)
            tab.addEventListener("click", function () {
                activate(tab.dataset.feature);
            });

            // Keyboard interaction
            tab.addEventListener("keydown", function (event) {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    activate(tab.dataset.feature);
                    return;
                }

                // Arrow key navigation between tabs
                var index = tabs.indexOf(tab);
                var nextIndex = null;
                if (event.key === "ArrowDown" || event.key === "ArrowRight") {
                    nextIndex = (index + 1) % tabs.length;
                } else if (
                    event.key === "ArrowUp" ||
                    event.key === "ArrowLeft"
                ) {
                    nextIndex = (index - 1 + tabs.length) % tabs.length;
                }

                if (nextIndex !== null) {
                    event.preventDefault();
                    var next = tabs[nextIndex];
                    activate(next.dataset.feature);
                    next.focus();
                }
            });
        });
    }

    /**
     * Subtle ambient drift. Only runs on desktop and when motion is allowed.
     * The glows are purely decorative (CSS pseudo-elements); we just let the
     * CSS animation run. Nothing heavy here.
     */
    function initAmbientDrift() {
        if (prefersReducedMotion) {
            document.documentElement.classList.add("lp-no-motion");
        }
    }
})();

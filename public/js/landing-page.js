/**
 * PUPTracker landing page — light, dependency-free interactions.
 *
 * - Staggered entrance reveal (respects prefers-reduced-motion)
 * - Interactive feature preview (hover + keyboard)
 * - Honest system status check against the real /up endpoint
 * - Subtle desktop-only ambient drift
 */
(function () {
    "use strict";

    var prefersReducedMotion = window.matchMedia
        ? window.matchMedia("(prefers-reduced-motion: reduce)").matches
        : false;

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
        initSystemStatus();
        initAmbientDrift();
    });

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
     * Honest system status. Fetches the real Laravel /up health endpoint.
     * - 200 + ok:  "System Operational" (green)
     * - any failure: "System Status" stays neutral (no fake offline claim)
     */
    function initSystemStatus() {
        var statusEl = document.getElementById("lpSystemStatus");
        if (!statusEl) {
            return;
        }

        var url = statusEl.getAttribute("data-status-url") || "/up";
        var dot = statusEl.querySelector(".lp-status-dot");

        // Fetch with a short timeout so the page never hangs on this.
        var controller =
            "AbortController" in window ? new AbortController() : null;
        var timeoutId = controller
            ? window.setTimeout(function () {
                  controller.abort();
              }, 4000)
            : null;

        fetch(url, {
            method: "GET",
            headers: { Accept: "application/json" },
            cache: "no-store",
            signal: controller ? controller.signal : undefined,
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error("not ok");
                }
                return res.json();
            })
            .then(function (data) {
                if (data && data.status === "OK") {
                    statusEl.classList.add("is-operational");
                    statusEl.lastChild.textContent = " System Operational";
                    statusEl.title = "System is operational";
                }
                // If the endpoint responds but is not "OK", stay neutral.
            })
            .catch(function () {
                // Stay neutral ("System Status") — never fake a status.
            })
            .finally(function () {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
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

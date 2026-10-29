/* =========================================================
   PUPTRACKER — LANDING PAGE INTERACTIONS
   Mobile navigation | Sticky header | Replayable reveals
   Feature previews | Ambient pointer glow | Image slideshows
   ========================================================= */

(() => {
    "use strict";

    function initializeLandingPage() {
        const page = document.querySelector(".landing-page");

        if (!page) return;

        page.classList.add("is-ready");

        /* =============================================
           CAMPUS PHOTO SLIDESHOWS (ABOUT + OUR GOAL)
           ============================================= */

        const slideshowReducedMotion = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        );

        page.querySelectorAll("[data-slideshow]").forEach((slideshow) => {
            const slides = Array.from(slideshow.querySelectorAll(".lp-slide"));

            const dotsContainer = slideshow.querySelector(".lp-slideshow-dots");

            if (slides.length < 2) return;

            let activeIndex = Math.max(
                0,
                slides.findIndex((slide) =>
                    slide.classList.contains("is-active"),
                ),
            );

            let intervalId = null;

            const dots = dotsContainer
                ? slides.map((_, index) => {
                      const dot = document.createElement("span");

                      if (index === activeIndex) {
                          dot.classList.add("is-active");
                      }

                      dot.setAttribute("aria-hidden", "true");
                      dotsContainer.appendChild(dot);

                      return dot;
                  })
                : [];

            function showSlide(nextIndex) {
                activeIndex = (nextIndex + slides.length) % slides.length;

                slides.forEach((slide, index) => {
                    const active = index === activeIndex;

                    slide.classList.toggle("is-active", active);
                    slide.setAttribute("aria-hidden", String(!active));

                    if (dots[index]) {
                        dots[index].classList.toggle("is-active", active);
                    }
                });
            }

            function stopSlideshow() {
                if (intervalId !== null) {
                    window.clearInterval(intervalId);
                    intervalId = null;
                }
            }

            function startSlideshow() {
                stopSlideshow();

                if (!slideshowReducedMotion.matches && !document.hidden) {
                    intervalId = window.setInterval(() => {
                        showSlide(activeIndex + 1);
                    }, 5000);
                }
            }

            showSlide(activeIndex);
            startSlideshow();

            slideshow.addEventListener("mouseenter", stopSlideshow);

            slideshow.addEventListener("mouseleave", startSlideshow);

            slideshow.addEventListener("focusin", stopSlideshow);

            slideshow.addEventListener("focusout", startSlideshow);

            document.addEventListener("visibilitychange", () => {
                if (document.hidden) {
                    stopSlideshow();
                } else {
                    startSlideshow();
                }
            });

            const handleSlideshowMotionPreference = () => {
                if (slideshowReducedMotion.matches) {
                    stopSlideshow();
                } else {
                    startSlideshow();
                }
            };

            if (slideshowReducedMotion.addEventListener) {
                slideshowReducedMotion.addEventListener(
                    "change",
                    handleSlideshowMotionPreference,
                );
            } else if (slideshowReducedMotion.addListener) {
                slideshowReducedMotion.addListener(
                    handleSlideshowMotionPreference,
                );
            }
        });

        /* =============================================
           HEADER / NAVIGATION
           ============================================= */

        const header =
            document.getElementById("lpHeader") ||
            page.querySelector(".lp-nav");

        const menuToggle = page.querySelector(".lp-menu-toggle");
        const navigation = page.querySelector(".lp-navigation");

        function updateHeader() {
            if (!header) return;

            const isScrolled = window.scrollY > 20;

            header.classList.toggle("is-scrolled", isScrolled);
        }

        updateHeader();

        window.addEventListener("scroll", updateHeader, {
            passive: true,
        });

        window.addEventListener("resize", updateHeader, {
            passive: true,
        });

        /* =============================================
           MOBILE MENU
           ============================================= */

        function closeMobileMenu() {
            if (!menuToggle || !navigation) return;

            navigation.classList.remove("is-open");
            menuToggle.setAttribute("aria-expanded", "false");
        }

        if (menuToggle && navigation) {
            menuToggle.setAttribute("aria-expanded", "false");
            menuToggle.setAttribute("aria-controls", "lpNavigation");

            if (!navigation.id) {
                navigation.id = "lpNavigation";
            }

            menuToggle.addEventListener("click", () => {
                const isOpen = navigation.classList.toggle("is-open");

                menuToggle.setAttribute("aria-expanded", String(isOpen));
            });

            navigation.querySelectorAll("a").forEach((link) => {
                link.addEventListener("click", closeMobileMenu);
            });

            document.addEventListener("keydown", (event) => {
                if (event.key === "Escape") {
                    closeMobileMenu();
                }
            });

            document.addEventListener("click", (event) => {
                if (
                    !navigation.classList.contains("is-open") ||
                    header?.contains(event.target)
                ) {
                    return;
                }

                closeMobileMenu();
            });

            window.addEventListener("resize", () => {
                if (window.innerWidth > 760) {
                    closeMobileMenu();
                }
            });
        }

        /* =============================================
           REPLAYABLE SCROLL REVEALS
           ============================================= */

        const revealElements = page.querySelectorAll(
            "[data-scroll-reveal], [data-reveal]",
        );

        const reducedMotion = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        );

        if (reducedMotion.matches) {
            revealElements.forEach((element) => {
                element.classList.remove("scroll-reveal");
                element.classList.add("is-revealed");
            });
        } else if ("IntersectionObserver" in window) {
            revealElements.forEach((element) => {
                element.classList.add("scroll-reveal");
            });

            const revealObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        const element = entry.target;

                        if (entry.isIntersecting) {
                            element.classList.add("is-revealed");
                        } else {
                            element.classList.remove("is-revealed");
                        }
                    });
                },
                {
                    threshold: 0.12,
                    rootMargin: "0px 0px -40px 0px",
                },
            );

            revealElements.forEach((element) => {
                revealObserver.observe(element);
            });

            const handleMotionPreference = () => {
                if (!reducedMotion.matches) return;

                revealObserver.disconnect();

                revealElements.forEach((element) => {
                    element.classList.remove("scroll-reveal");
                    element.classList.add("is-revealed");
                });
            };

            if (reducedMotion.addEventListener) {
                reducedMotion.addEventListener(
                    "change",
                    handleMotionPreference,
                );
            } else if (reducedMotion.addListener) {
                reducedMotion.addListener(handleMotionPreference);
            }
        } else {
            revealElements.forEach((element) => {
                element.classList.add("is-revealed");
            });
        }

        /* =============================================
           OPTIONAL INTERACTIVE FEATURE PREVIEWS
           ============================================= */

        const featureTriggers = page.querySelectorAll("[data-feature-target]");

        const featurePreviews = page.querySelectorAll("[data-feature-preview]");

        function activateFeature(featureName) {
            featureTriggers.forEach((trigger) => {
                const active = trigger.dataset.featureTarget === featureName;

                trigger.classList.toggle("is-active", active);
                trigger.setAttribute("aria-pressed", String(active));
            });

            featurePreviews.forEach((preview) => {
                const active = preview.dataset.featurePreview === featureName;

                preview.classList.toggle("is-active", active);
                preview.classList.toggle("is-inactive", !active);
                preview.setAttribute("aria-hidden", String(!active));
            });
        }

        featureTriggers.forEach((trigger) => {
            trigger.addEventListener("click", () => {
                activateFeature(trigger.dataset.featureTarget);
            });
        });

        if (featureTriggers.length && featurePreviews.length) {
            const initiallyActive = Array.from(featureTriggers).find(
                (trigger) => trigger.classList.contains("is-active"),
            );

            const firstFeature = initiallyActive
                ? initiallyActive.dataset.featureTarget
                : featureTriggers[0].dataset.featureTarget;

            activateFeature(firstFeature);
        }

        /* =============================================
           POINTER GLOW
           ============================================= */

        const glowElements = page.querySelectorAll(".lp-glow");

        if (
            !reducedMotion.matches &&
            window.matchMedia("(hover: hover) and (pointer: fine)").matches
        ) {
            glowElements.forEach((element) => {
                element.addEventListener("pointermove", (event) => {
                    const bounds = element.getBoundingClientRect();

                    const x = event.clientX - bounds.left;
                    const y = event.clientY - bounds.top;

                    element.style.setProperty("--glow-x", `${x}px`);

                    element.style.setProperty("--glow-y", `${y}px`);
                });
            });
        }

        /* =============================================
           SMOOTH INTERNAL NAVIGATION
           ============================================= */

        page.querySelectorAll('a[href^="#"]').forEach((link) => {
            link.addEventListener("click", (event) => {
                const href = link.getAttribute("href");

                if (!href || href === "#") return;

                let target;

                try {
                    target = document.querySelector(href);
                } catch {
                    return;
                }

                if (!target) return;

                event.preventDefault();

                target.scrollIntoView({
                    behavior: reducedMotion.matches ? "auto" : "smooth",
                    block: "start",
                });

                if (!target.hasAttribute("tabindex")) {
                    target.setAttribute("tabindex", "-1");
                }

                target.focus({ preventScroll: true });
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initializeLandingPage, {
            once: true,
        });
    } else {
        initializeLandingPage();
    }
})();

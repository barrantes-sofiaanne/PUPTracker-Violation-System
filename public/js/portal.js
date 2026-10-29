document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("portalSidebar");
    const toggle = document.getElementById("portalSidebarToggle");
    const closeBtn = document.getElementById("portalSidebarClose");
    const backdrop = document.getElementById("portalSidebarBackdrop");

    if (!sidebar || !toggle || !backdrop) {
        return;
    }

    const isMobile = () => window.matchMedia("(max-width: 991.98px)").matches;

    const openSidebar = () => {
        if (!isMobile()) {
            return;
        }

        sidebar.classList.add("open");
        backdrop.classList.add("show");
        toggle.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
    };

    const closeSidebar = () => {
        sidebar.classList.remove("open");
        backdrop.classList.remove("show");
        toggle.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    };

    toggle.addEventListener("click", function () {
        if (sidebar.classList.contains("open")) {
            closeSidebar();
            return;
        }

        openSidebar();
    });

    if (closeBtn) {
        closeBtn.addEventListener("click", closeSidebar);
    }

    backdrop.addEventListener("click", closeSidebar);

    window.addEventListener("resize", function () {
        if (!isMobile()) {
            closeSidebar();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeSidebar();
        }
    });
});

/*
 * Student module only (body.student-module): presentation helpers.
 *  - mirrors the bell's unread count onto the sidebar "Notifications" badge
 *  - gives browsers without cross-document View Transitions a short fade-out
 *    before following a sidebar / menu link (plain navigation, nothing blocked)
 */
document.addEventListener("DOMContentLoaded", function () {
    if (!document.body.classList.contains("student-module")) {
        return;
    }

    const navBadge = document.querySelector("[data-nav-unread]");
    const bellBadge = () =>
        document.querySelector(".admin-notif-bell-btn .notif-bell-badge");

    const syncNavBadge = () => {
        if (!navBadge) {
            return;
        }

        const source = bellBadge();
        const text = source ? source.textContent.trim() : "";

        navBadge.textContent = text;
        navBadge.hidden = text === "" || text === "0";
    };

    syncNavBadge();

    const bell = document.querySelector(".admin-notif-bell");

    if (bell && navBadge) {
        new MutationObserver(syncNavBadge).observe(bell, {
            childList: true,
            subtree: true,
            characterData: true,
        });
    }

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;
    const hasViewTransitions = "CSSViewTransitionRule" in window;
    const page = document.querySelector(".portal-main .student-page");

    if (reducedMotion || hasViewTransitions || !page) {
        return;
    }

    document.addEventListener("click", function (event) {
        const link = event.target.closest(
            ".portal-nav-list a, .user-menu-item[href], .admin-notif-view-all",
        );

        if (
            !link ||
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            (link.target && link.target !== "_self") ||
            link.origin !== window.location.origin ||
            link.href === window.location.href
        ) {
            return;
        }

        event.preventDefault();
        page.classList.add("is-leaving");
        window.setTimeout(() => window.location.assign(link.href), 160);
    });

    window.addEventListener("pageshow", function (event) {
        if (event.persisted) {
            page.classList.remove("is-leaving");
        }
    });
});

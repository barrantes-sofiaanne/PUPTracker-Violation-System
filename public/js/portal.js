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
        document.body.style.overflow = "hidden";
    };

    const closeSidebar = () => {
        sidebar.classList.remove("open");
        backdrop.classList.remove("show");
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

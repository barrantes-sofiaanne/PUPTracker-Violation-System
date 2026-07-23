document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".sidebar");
    const sidebarToggle = document.getElementById("sidebarToggle");
    const sidebarClose = document.getElementById("sidebarClose");
    const sidebarBackdrop = document.getElementById("sidebarBackdrop");

    if (!sidebar || !sidebarToggle || !sidebarBackdrop) {
        return;
    }

    const isMobile = () => window.matchMedia("(max-width: 991.98px)").matches;

    const openSidebar = () => {
        if (!isMobile()) {
            return;
        }

        sidebar.classList.add("open");
        sidebarBackdrop.classList.add("show");
        document.body.style.overflow = "hidden";
    };

    const closeSidebar = () => {
        sidebar.classList.remove("open");
        sidebarBackdrop.classList.remove("show");
        document.body.style.overflow = "";
    };

    sidebarToggle.addEventListener("click", function () {
        if (sidebar.classList.contains("open")) {
            closeSidebar();
            return;
        }

        openSidebar();
    });

    if (sidebarClose) {
        sidebarClose.addEventListener("click", closeSidebar);
    }

    sidebarBackdrop.addEventListener("click", closeSidebar);

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

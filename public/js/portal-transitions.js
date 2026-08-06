document.addEventListener("DOMContentLoaded", function () {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (reduceMotion) {
        return;
    }

    document.querySelectorAll("a[href]").forEach(function (link) {
        link.addEventListener("click", function (event) {
            const href = link.getAttribute("href");

            if (
                event.defaultPrevented ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                link.target ||
                link.hasAttribute("download") ||
                !href ||
                href.startsWith("#") ||
                href.startsWith("mailto:") ||
                href.startsWith("tel:") ||
                new URL(link.href, window.location.href).origin !== window.location.origin
            ) {
                return;
            }

            event.preventDefault();
            document.documentElement.classList.add("is-navigating");
            window.setTimeout(function () {
                window.location.assign(link.href);
            }, 180);
        });
    });
});

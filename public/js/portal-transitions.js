function createBrandItem(tagName, className, text) {
    const element = document.createElement(tagName);
    element.className = className;
    element.textContent = text;

    return element;
}

function enhanceAuthBrandPanel() {
    const brand = document.querySelector(".auth-portal-brand");

    if (!brand || brand.dataset.enhanced === "true") {
        return;
    }

    const logo = brand.querySelector(".auth-brand-mark");
    const campus = brand.querySelector(".auth-brand-campus");
    const title = brand.querySelector("h1");
    const system = brand.querySelector(".auth-brand-system");
    const description = brand.querySelector(".auth-brand-description");

    if (!logo || !campus || !title || !system || !description) {
        return;
    }

    const heading = document.createElement("div");
    heading.className = "auth-brand-heading";
    const headingCopy = document.createElement("div");

    campus.textContent =
        "Polytechnic University of the Philippines Taguig Campus";
    system.textContent = "Violation System";
    headingCopy.append(campus, title, system);
    heading.append(logo, headingCopy);
    brand.insertBefore(heading, description);

    const highlights = document.createElement("div");
    highlights.className = "auth-brand-highlights";
    [
        ["bi-shield-check", "Secure Access"],
        ["bi-clipboard2-check", "Organized Records"],
        ["bi-graph-up-arrow", "Faster Tracking"],
    ].forEach(function ([iconName, label]) {
        const item = document.createElement("span");
        const icon = document.createElement("i");
        icon.className = "bi " + iconName;
        icon.setAttribute("aria-hidden", "true");
        item.append(icon, document.createTextNode(" " + label));
        highlights.append(item);
    });

    const footer = document.createElement("div");
    footer.className = "auth-brand-footer";
    [
        ["https://www.pup.edu.ph/privacy/", "Privacy Statement", true],
        ["https://www.pup.edu.ph/terms/", "Terms of Use", true],
        ["mailto:puptrackervs@gmail.com", "Contact Us", false],
    ].forEach(function ([href, label, external], index) {
        if (index > 0) {
            footer.append(
                createBrandItem("span", "auth-brand-separator", "\u2022"),
            );
        }

        const link = document.createElement("a");
        link.href = href;
        link.textContent = label;

        if (external) {
            link.target = "_blank";
            link.rel = "noopener noreferrer";
        }

        footer.append(link);
    });

    description.insertAdjacentElement("afterend", highlights);
    highlights.insertAdjacentElement("afterend", footer);
    brand.dataset.enhanced = "true";

    document.querySelectorAll("a[href]").forEach(function (link) {
        if (link.textContent.includes("Back to Login")) {
            link.textContent = "\u2190 Back to Login";
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    enhanceAuthBrandPanel();
    const reduceMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
    ).matches;

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
                new URL(link.href, window.location.href).origin !==
                    window.location.origin
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

    window.addEventListener("pageshow", function () {
        document.documentElement.classList.remove("is-navigating");
    });
});

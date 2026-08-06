function createBrandLink(href, label, external) {
    const link = document.createElement("a");
    link.href = href;
    link.textContent = label;

    if (external) {
        link.target = "_blank";
        link.rel = "noopener noreferrer";
    }

    return link;
}

const portalSystemVersion = "Version 2.0.0";
const portalShellColumns = "minmax(0, 1.05fr) minmax(320px, 0.95fr)";

function injectPortalEnhancementStyles() {
    if (document.getElementById("portal-enhancement-styles")) {
        return;
    }

    const style = document.createElement("style");
    style.id = "portal-enhancement-styles";
    style.textContent = `
        .landing-page {
            display: grid;
            place-items: center;
            padding: 2rem 1rem;
            background:
                linear-gradient(115deg, rgba(18, 7, 10, 0.9), rgba(62, 15, 22, 0.72)),
                url("/assets/images/PUPT-picture.jpg") center/cover no-repeat;
        }

        .landing-shell {
            animation: none !important;
            grid-template-columns: ${portalShellColumns};
            width: min(1040px, calc(100vw - 2rem));
            margin: 0 auto;
        }

        .landing-access {
            animation: none !important;
        }

        .landing-access .portal-buttons {
            animation: landing-access-enter 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .landing-access .portal-btn {
            border-color: rgba(122, 23, 32, 0.14);
            box-shadow: 0 10px 24px rgba(52, 21, 24, 0.08);
            transition:
                transform 220ms ease,
                border-color 220ms ease,
                box-shadow 220ms ease,
                background-color 220ms ease,
                color 220ms ease;
        }

        .landing-access .portal-btn:hover {
            border-color: rgba(122, 23, 32, 0.4);
            box-shadow: 0 18px 32px rgba(52, 21, 24, 0.16);
            transform: translateY(-4px);
        }

        .landing-access .portal-btn:hover .portal-arrow {
            transform: translate(4px, -4px);
        }

        .landing-access .portal-btn:focus-visible,
        .auth-brand-footer a:focus-visible,
        .footer-links a:focus-visible {
            outline: 3px solid rgba(245, 198, 93, 0.82);
            outline-offset: 3px;
        }

        .auth-portal-page {
            overflow: auto;
            background:
                linear-gradient(115deg, rgba(18, 7, 10, 0.9), rgba(62, 15, 22, 0.72)),
                url("/assets/images/PUPT-picture.jpg") center/cover fixed;
        }

        .auth-portal-shell {
            grid-template-columns: ${portalShellColumns};
            width: min(1040px, calc(100vw - 2rem));
            margin: 0 auto;
        }

        .auth-brand-description {
            max-width: 340px;
            line-height: 1.6;
        }

        .auth-brand-highlights span {
            color: #fff7dd;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .auth-brand-highlights i {
            color: #f5c65d;
        }

        .auth-brand-footer a {
            color: inherit;
            text-decoration: none;
        }

        .auth-brand-footer a:hover {
            color: #f5c65d;
        }

        .auth-brand-version {
            display: inline-flex;
            align-items: center;
            margin-top: 0.85rem;
            padding: 0.34rem 0.58rem;
            color: rgba(255, 255, 255, 0.74);
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
        }

        .landing-version {
            display: inline-flex;
            align-items: center;
            margin-top: 0.65rem;
            padding: 0.32rem 0.56rem;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.66rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
        }

        .auth-content-card {
            animation: auth-content-enter 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        html.is-navigating .landing-access .portal-buttons,
        html.is-navigating .auth-content-card {
            animation: none !important;
            opacity: 0;
            transform: translateX(22px);
            transition: opacity 180ms ease, transform 180ms ease;
        }

        @media (max-width: 768px) {
            .landing-page {
                padding: 0.75rem;
            }

            .landing-shell {
                width: min(560px, calc(100vw - 1.5rem));
                margin: 0 auto;
            }
        }

        @media (max-width: 850px) {
            .auth-portal-shell {
                width: min(560px, calc(100vw - 1.5rem));
                margin: 0 auto;
            }

            .auth-brand-version {
                display: none;
            }
        }

        @keyframes landing-access-enter {
            from {
                opacity: 0;
                transform: translateX(22px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes auth-content-enter {
            from {
                opacity: 0;
                transform: translateX(22px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .landing-access .portal-buttons,
            .auth-content-card {
                animation: none !important;
                transition: none !important;
            }
        }
    `;

    document.head.append(style);
}

function enhanceAuthBrandPanel() {
    const brand = document.querySelector(".auth-portal-brand");

    if (!brand) {
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

    if (!brand.querySelector(".auth-brand-heading")) {
        const heading = document.createElement("div");
        const headingCopy = document.createElement("div");
        heading.className = "auth-brand-heading";

        headingCopy.append(campus, title, system);
        heading.append(logo, headingCopy);
        brand.insertBefore(heading, description);
    }

    campus.textContent =
        "Polytechnic University of the Philippines Taguig Campus";
    system.textContent = "Violation System";
    description.textContent =
        "Secure access to campus conduct records and updates.";

    const existingHighlights = brand.querySelector(".auth-brand-highlights");

    if (existingHighlights) {
        existingHighlights.remove();
    }

    const highlights = document.createElement("div");
    highlights.className = "auth-brand-highlights";

    [
        ["bi-shield-check", "Secure Access"],
        ["bi-graph-up-arrow", "Faster Tracking"],
    ].forEach(function ([iconName, label]) {
        const item = document.createElement("span");
        const icon = document.createElement("i");
        icon.className = "bi " + iconName;
        icon.setAttribute("aria-hidden", "true");
        item.append(icon, document.createTextNode(" " + label));
        highlights.append(item);
    });

    const existingFooter = brand.querySelector(".auth-brand-footer");

    if (existingFooter) {
        existingFooter.remove();
    }

    const footer = document.createElement("div");
    footer.className = "auth-brand-footer";

    [
        ["https://www.pup.edu.ph/privacy/", "Privacy Statement", true],
        ["https://www.pup.edu.ph/terms/", "Terms of Use", true],
        ["mailto:puptrackervs@gmail.com", "Contact Us", false],
    ].forEach(function ([href, label, external], index) {
        if (index > 0) {
            const separator = document.createElement("span");
            separator.className = "auth-brand-separator";
            separator.setAttribute("aria-hidden", "true");
            separator.textContent = "\u2022";
            footer.append(separator);
        }

        footer.append(createBrandLink(href, label, external));
    });

    description.insertAdjacentElement("afterend", highlights);
    highlights.insertAdjacentElement("afterend", footer);

    const existingVersion = brand.querySelector(".auth-brand-version");

    if (existingVersion) {
        existingVersion.remove();
    }

    const versionBadge = document.createElement("span");
    versionBadge.className = "auth-brand-version";
    versionBadge.textContent = portalSystemVersion;
    footer.insertAdjacentElement("afterend", versionBadge);
}

function normalizeBackLinks() {
    document.querySelectorAll(".footer-links a[href]").forEach(function (link) {
        if (link.textContent.includes("Back to Login")) {
            link.textContent = "\u2190 Back to Login";
        }
    });
}

function enhanceLandingFooter() {
    const footer = document.querySelector(".landing-intro .footer-links");

    if (!footer) {
        return;
    }

    const existingVersion = document.querySelector(".landing-version");

    if (existingVersion) {
        existingVersion.remove();
    }

    const versionBadge = document.createElement("span");
    versionBadge.className = "landing-version";
    versionBadge.textContent = portalSystemVersion;
    footer.insertAdjacentElement("afterend", versionBadge);
}

document.addEventListener("DOMContentLoaded", function () {
    injectPortalEnhancementStyles();
    enhanceAuthBrandPanel();
    enhanceLandingFooter();
    normalizeBackLinks();

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
});

window.addEventListener("pageshow", function () {
    document.documentElement.classList.remove("is-navigating");
});

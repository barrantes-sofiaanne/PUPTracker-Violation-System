document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-auth-spa-root]");
    const shell = document.querySelector("[data-auth-shell]");
    const card = document.querySelector(".auth-content-card");

    if (!root || !shell || !card) {
        return;
    }

    const authPathPattern =
        /^\/($|(student|security|admin)\/login$|password\/forgot\/(student|security|admin)$|password\/reset\/(student|security|admin)\/[^/]+$|mfa\/verify$)/;

    let isNavigating = false;
    const CARD_FADE_OUT_MS = 170;
    const CARD_ENTER_DURATION_MS = 340;
    const NAVIGATION_CLASS_RELEASE_MS = 90;

    function isNavigablePath(pathname) {
        return authPathPattern.test(pathname);
    }

    function shouldHandleLink(link, event) {
        if (!link || event.defaultPrevented) {
            return false;
        }

        if (
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            event.button !== 0
        ) {
            return false;
        }

        const href = link.getAttribute("href");
        if (
            !href ||
            href.startsWith("#") ||
            href.startsWith("mailto:") ||
            href.startsWith("tel:") ||
            link.target ||
            link.hasAttribute("download")
        ) {
            return false;
        }

        return true;
    }

    function dispatchContentUpdated() {
        document.dispatchEvent(new CustomEvent("auth:content-updated"));
    }

    function swapContent(htmlText, resolvedUrl, pushState) {
        const parser = new DOMParser();
        const parsed = parser.parseFromString(htmlText, "text/html");
        const nextRoot = parsed.querySelector("[data-auth-spa-root]");
        const nextCard = parsed.querySelector(".auth-content-card");

        if (!nextRoot || !nextCard) {
            window.location.assign(resolvedUrl);
            return;
        }

        root.className = nextRoot.className;
        card.setAttribute("data-portal", nextCard.dataset.portal || "access");
        card.innerHTML = nextCard.innerHTML;
        document.title = parsed.title || document.title;

        if (pushState) {
            window.history.pushState({ authSpa: true }, "", resolvedUrl);
        }

        card.classList.remove("is-entering");
        void card.offsetWidth;
        card.classList.add("is-entering");
        window.setTimeout(function () {
            card.classList.remove("is-entering");
        }, CARD_ENTER_DURATION_MS);

        shell.scrollTop = 0;
        card.scrollTop = 0;

        dispatchContentUpdated();
    }

    function loadIntoCard(url, options) {
        if (isNavigating) {
            return;
        }

        isNavigating = true;
        document.documentElement.classList.add("is-navigating");

        // Keep the current card on screen while the next page loads. Once it
        // arrives, fade it out, swap the DOM, and fade the new card in — a
        // continuous crossfade with no blank pause in between.
        function crossfadeTo(htmlText, resolvedUrl) {
            card.classList.add("is-leaving");
            shell.classList.add("is-swapping");

            window.setTimeout(function () {
                swapContent(htmlText, resolvedUrl, options.pushState);
            }, CARD_FADE_OUT_MS);
        }

        fetch(url, options)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Request failed");
                }

                return response.text().then(function (htmlText) {
                    crossfadeTo(htmlText, response.url || url);
                });
            })
            .catch(function () {
                window.location.assign(url);
            })
            .finally(function () {
                isNavigating = false;
                window.setTimeout(function () {
                    shell.classList.remove("is-swapping");
                    card.classList.remove("is-leaving");
                    document.documentElement.classList.remove("is-navigating");
                }, NAVIGATION_CLASS_RELEASE_MS);
            });
    }

    document.addEventListener("click", function (event) {
        const link = event.target.closest("a[href]");
        if (!shouldHandleLink(link, event)) {
            return;
        }

        const targetUrl = new URL(link.href, window.location.href);
        if (
            targetUrl.origin !== window.location.origin ||
            !isNavigablePath(targetUrl.pathname)
        ) {
            return;
        }

        event.preventDefault();
        loadIntoCard(targetUrl.toString(), {
            method: "GET",
            headers: {
                Accept: "text/html",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
            pushState: true,
        });
    });

    document.addEventListener("submit", function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (
            !card.contains(form) ||
            form.target ||
            form.hasAttribute("data-no-spa")
        ) {
            return;
        }

        const method = (form.method || "GET").toUpperCase();
        const action = form.getAttribute("action") || window.location.href;
        const targetUrl = new URL(action, window.location.href);

        if (
            targetUrl.origin !== window.location.origin ||
            !isNavigablePath(targetUrl.pathname)
        ) {
            return;
        }

        event.preventDefault();
        const formData = new FormData(form);

        if (method === "GET") {
            const searchParams = new URLSearchParams(formData);
            targetUrl.search = searchParams.toString();
            loadIntoCard(targetUrl.toString(), {
                method: "GET",
                headers: {
                    Accept: "text/html",
                    "X-Requested-With": "XMLHttpRequest",
                },
                credentials: "same-origin",
                pushState: true,
            });
            return;
        }

        loadIntoCard(targetUrl.toString(), {
            method: method,
            body: formData,
            headers: {
                Accept: "text/html",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
            pushState: true,
        });
    });

    window.addEventListener("popstate", function () {
        loadIntoCard(window.location.href, {
            method: "GET",
            headers: {
                Accept: "text/html",
                "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
            pushState: false,
        });
    });
});

/*
 * PUPTracker authentication portal — presentation-only behaviour.
 *
 *  - Soft navigation between the portal selection and the login pages: the
 *    shell (photo, panels, curve) stays in place and MORPHS to the next
 *    page's look while its content is fetched in the background — no blank
 *    reload, no waiting on a frozen screen. It is an ordinary GET of the
 *    same URL the link points to; if anything looks unusual (redirect,
 *    error, unexpected markup) the browser simply follows the link normally.
 *  - Password show / hide.
 *  - Busy state on the sign-in button (and a double-submit guard).
 *  - "Redirecting to PUP IDP" overlay for IDP links.
 *
 * Form submissions are never intercepted. Without JavaScript every link
 * and form still works.
 */
(function () {
    "use strict";

    var page = document.querySelector(".ap-page");

    // Legacy auth pages (recovery / MFA / IDP registration) use other scripts.
    if (!page) {
        return;
    }

    var isNavigating = false;

    function prefersReducedMotion() {
        return (
            window.matchMedia &&
            window.matchMedia("(prefers-reduced-motion: reduce)").matches
        );
    }

    function wait(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function nextFrame() {
        return new Promise(function (resolve) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(resolve);
            });
        });
    }

    function setTheme(theme) {
        Array.prototype.slice.call(page.classList).forEach(function (name) {
            if (name.indexOf("ap-theme-") === 0) {
                page.classList.remove(name);
            }
        });

        page.classList.add("ap-theme-" + theme);
    }

    function focusFirstField() {
        if (window.matchMedia && window.matchMedia("(pointer: fine)").matches) {
            var first = document.querySelector(
                '.ap-form input:not([type="hidden"]):not([disabled]):not([readonly])',
            );

            if (first) {
                first.focus({ preventScroll: true });
            }
        }
    }

    /* ------------------------------------------------------------------
       1. Soft navigation (portal selection ⇄ login)
       ------------------------------------------------------------------ */
    function canSoftNavigate() {
        return (
            !prefersReducedMotion() &&
            typeof window.fetch === "function" &&
            typeof window.DOMParser === "function" &&
            window.history &&
            typeof window.history.pushState === "function"
        );
    }

    // Pages are fetched when a card is hovered / focused / touched, so by the
    // time it is clicked the next page has usually already arrived.
    var prefetched = {};

    function fetchPage(destination) {
        var key = destination.href;
        var cached = prefetched[key];

        if (cached && Date.now() - cached.at < 30000) {
            return cached.promise;
        }

        var promise = window
            .fetch(key, {
                credentials: "same-origin",
                headers: { Accept: "text/html" },
            })
            .then(function (response) {
                var finalUrl = new URL(response.url, window.location.href);
                var type = response.headers.get("content-type") || "";

                if (
                    !response.ok ||
                    response.redirected ||
                    finalUrl.pathname !== destination.pathname ||
                    type.indexOf("text/html") === -1
                ) {
                    throw new Error("unexpected response");
                }

                return response.text();
            });

        promise.catch(function () {
            delete prefetched[key];
        });

        prefetched[key] = { at: Date.now(), promise: promise };

        return promise;
    }

    function prefetchLink(event) {
        var link =
            event.target.closest &&
            event.target.closest("[data-portal-card], [data-soft-nav]");

        if (!link || link.tagName !== "A" || !canSoftNavigate()) {
            return;
        }

        try {
            var destination = new URL(link.href, window.location.href);

            if (destination.origin === window.location.origin) {
                fetchPage(destination);
            }
        } catch (error) {
            /* ignore */
        }
    }

    document.addEventListener("pointerover", prefetchLink);
    document.addEventListener("focusin", prefetchLink);
    document.addEventListener("touchstart", prefetchLink, { passive: true });

    function softNavigate(link) {
        var destination = new URL(link.href, window.location.href);
        var theme = link.getAttribute("data-portal-theme");
        var brandBody = page.querySelector(".ap-brand-body");
        var panelInner = page.querySelector(".ap-panel-inner");
        var panel = page.querySelector(".ap-panel");
        var startedAt = Date.now();
        var loader = null;

        function fallback() {
            window.location.assign(destination.href);
        }

        if (!theme || !brandBody || !panelInner || !panel) {
            fallback();
            return;
        }

        isNavigating = true;
        link.classList.add("is-selected");
        link.setAttribute("aria-busy", "true");

        // t = 0: content fades out and the shell starts morphing straight away.
        page.classList.add("is-leaving");
        setTheme(theme);

        // A small spinner only if the next page is slow to arrive.
        var loaderTimer = window.setTimeout(function () {
            loader = document.createElement("span");
            loader.className = "ap-loading";
            loader.setAttribute("aria-hidden", "true");
            panel.appendChild(loader);
        }, 450);

        fetchPage(destination)
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, "text/html");
                var nextPage = doc.querySelector(".ap-page");
                var nextBrand = doc.querySelector(".ap-brand-body");
                var nextInner = doc.querySelector(".ap-panel-inner");

                if (
                    !nextPage ||
                    !nextBrand ||
                    !nextInner ||
                    nextInner.classList.contains("ap-panel-inner--legacy")
                ) {
                    throw new Error("unexpected markup");
                }

                // Let the fade-out finish before swapping anything.
                return wait(Math.max(0, 230 - (Date.now() - startedAt))).then(
                    function () {
                        window.clearTimeout(loaderTimer);

                        if (loader && loader.parentNode) {
                            loader.parentNode.removeChild(loader);
                        }

                        // Swap while everything is invisible, parked below.
                        page.classList.add("is-arriving");
                        page.classList.remove("is-leaving");

                        setTheme(
                            (nextPage.className.match(/ap-theme-([a-z]+)/) ||
                                [])[1] || theme,
                        );

                        brandBody.innerHTML = nextBrand.innerHTML;
                        panelInner.className = nextInner.className;
                        panelInner.innerHTML = nextInner.innerHTML;
                        document.title = doc.title || document.title;

                        window.history.pushState(
                            { apSoft: true },
                            "",
                            destination.pathname +
                                destination.search +
                                destination.hash,
                        );

                        return nextFrame();
                    },
                );
            })
            .then(function () {
                // Release: content rises into place.
                page.classList.remove("is-arriving");
                isNavigating = false;
                focusFirstField();
            })
            .catch(function () {
                window.clearTimeout(loaderTimer);
                fallback();
            });
    }

    document.addEventListener("click", function (event) {
        var link = event.target.closest("[data-portal-card], [data-soft-nav]");

        if (!link || link.tagName !== "A") {
            return;
        }

        if (
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey ||
            (link.target && link.target !== "_self") ||
            link.hasAttribute("download")
        ) {
            return;
        }

        var destination;

        try {
            destination = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }

        if (destination.origin !== window.location.origin) {
            return;
        }

        // Reduced motion / old browsers: ordinary navigation.
        if (!canSoftNavigate()) {
            return;
        }

        event.preventDefault();

        // A second click while a transition runs is ignored.
        if (isNavigating) {
            return;
        }

        softNavigate(link);
    });

    // Browser Back / Forward after a soft navigation: load the real page.
    window.addEventListener("popstate", function () {
        window.location.reload();
    });

    /* ------------------------------------------------------------------
       2. Show / hide password
       ------------------------------------------------------------------ */
    document.addEventListener("click", function (event) {
        var toggle = event.target.closest("[data-toggle-password]");

        if (!toggle) {
            return;
        }

        var input = document.getElementById(
            toggle.getAttribute("aria-controls") || "password",
        );

        if (!input) {
            return;
        }

        var reveal = input.type === "password";
        input.type = reveal ? "text" : "password";
        toggle.setAttribute(
            "aria-label",
            reveal ? "Hide password" : "Show password",
        );

        var icon = toggle.querySelector("i");

        if (icon) {
            icon.className = reveal ? "bi bi-eye-slash" : "bi bi-eye";
        }
    });

    /* ------------------------------------------------------------------
       3. Sign-in busy state + double-submit guard
       (delegated, so it also covers forms swapped in by soft navigation)
       ------------------------------------------------------------------ */
    document.addEventListener("submit", function (event) {
        var form = event.target;

        if (
            event.defaultPrevented ||
            !form.classList ||
            !form.classList.contains("ap-form")
        ) {
            return;
        }

        var button = form.querySelector('button[type="submit"]');

        if (!button) {
            return;
        }

        if (button.classList.contains("is-busy")) {
            event.preventDefault();
            return;
        }

        button.classList.add("is-busy");
        button.setAttribute("aria-busy", "true");
    });

    /* ------------------------------------------------------------------
       4. PUP IDP links (button state + "Redirecting…" overlay)
       ------------------------------------------------------------------ */
    document.addEventListener("click", function (event) {
        var trigger = event.target.closest("[data-idp-redirect]");

        if (
            !trigger ||
            event.defaultPrevented ||
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        if (trigger.classList.contains("is-busy")) {
            event.preventDefault();
            return;
        }

        var overlay = document.getElementById("apIdpOverlay");

        if (overlay) {
            overlay.hidden = false;
        }

        // The browser is about to leave for the IDP: make the password form
        // inert so a stray Enter key cannot submit a second login attempt.
        document.querySelectorAll(".ap-form").forEach(function (form) {
            form.querySelectorAll("input, button").forEach(function (el) {
                el.disabled = true;
            });
        });

        trigger.classList.add("is-busy");
        trigger.setAttribute("aria-busy", "true");
    });

    /* ------------------------------------------------------------------
       5. Reset password: live requirements checklist
       Mirrors the server rule (min 12, upper + lower case, number, symbol).
       The server stays the authority; this only guides the user and keeps
       the button disabled until the rule is met and both fields match.
       ------------------------------------------------------------------ */
    (function initPasswordPolicy() {
        var password = document.getElementById("newPassword");
        var confirmation = document.getElementById("passwordConfirmation");
        var submit = document.getElementById("resetPasswordSubmit");
        var mismatch = document.getElementById("passwordMatchHint");

        if (!password || !confirmation || !submit) {
            return;
        }

        var requirements = {
            length: function (value) {
                return value.length >= 12;
            },
            lowercase: function (value) {
                return /[a-z]/.test(value);
            },
            uppercase: function (value) {
                return /[A-Z]/.test(value);
            },
            number: function (value) {
                return /\d/.test(value);
            },
            symbol: function (value) {
                return /[^A-Za-z0-9]/.test(value);
            },
        };

        function update() {
            var value = password.value;
            var allMet = true;

            Object.keys(requirements).forEach(function (name) {
                var met = requirements[name](value);
                var item = document.querySelector(
                    '[data-policy="' + name + '"]',
                );

                allMet = allMet && met;

                if (item) {
                    item.classList.toggle("is-met", met);
                    item.querySelector("i").className = met
                        ? "bi bi-check-circle-fill"
                        : "bi bi-circle";
                }
            });

            var matches = value === confirmation.value;

            if (mismatch) {
                mismatch.hidden = !(confirmation.value !== "" && !matches);
            }

            submit.disabled = !(allMet && matches);
        }

        password.addEventListener("input", update);
        confirmation.addEventListener("input", update);
        update();
    })();

    /* ------------------------------------------------------------------
       6. Student signup wizard
       ONE form, four visual steps. Nothing is submitted until the final
       "Create Account"; the server validates everything again. The messages
       used here are the ones the server itself returns.
       ------------------------------------------------------------------ */
    (function initWizard() {
        var root = document.querySelector(".ap-wizard");
        var form = root && root.querySelector("[data-wizard-form]");

        if (!form) {
            return;
        }

        var panels = Array.prototype.slice.call(
            form.querySelectorAll("[data-step-panel]"),
        );
        var indicators = Array.prototype.slice.call(
            root.querySelectorAll("[data-step-indicator]"),
        );
        var viewport = form.querySelector("[data-wizard-viewport]");
        var backButton = form.querySelector("[data-wizard-back]");
        var nextButton = form.querySelector("[data-wizard-next]");
        var submitButton = form.querySelector("[data-wizard-submit]");
        var last = panels.length - 1;
        var current = 0;
        var completed = {};
        var animating = false;

        root.classList.add("is-stepper");
        form.noValidate = true; // the messages below replace the browser bubbles

        /* ---------- ladderized question (program dependent) ---------- */
        var program = document.getElementById("program_id");
        var ladderWrap = document.getElementById("ladderizedWrap");
        var ladderIds = [];

        try {
            ladderIds = JSON.parse(
                (program && program.dataset.ladderizedPrograms) || "[]",
            ).map(Number);
        } catch (error) {
            ladderIds = [];
        }

        function syncLadderized() {
            if (!program || !ladderWrap) {
                return;
            }

            var offered = ladderIds.indexOf(Number(program.value)) !== -1;
            ladderWrap.hidden = !offered;

            if (!offered) {
                ladderWrap
                    .querySelectorAll('input[type="radio"]')
                    .forEach(function (radio) {
                        radio.checked = false;
                    });
                setError("ladderized", "");
            }
        }

        if (program) {
            program.addEventListener("change", syncLadderized);
        }

        syncLadderized();

        /* ---------- validation ---------- */
        function errorBox(name) {
            return form.querySelector('[data-error-for="' + name + '"]');
        }

        function setError(name, message) {
            var box = errorBox(name);

            if (box) {
                box.textContent = message;
            }

            var control = form.elements[name];
            var first = control && control.length ? control[0] : control;

            if (!first) {
                return;
            }

            var holder = first.closest(".ap-field, .ap-check, .ap-choice-row");

            if (holder) {
                holder.classList.toggle("has-error", message !== "");
            }

            if (first.setAttribute) {
                if (message !== "") {
                    first.setAttribute("aria-invalid", "true");
                } else {
                    first.removeAttribute("aria-invalid");
                }
            }
        }

        function messageFor(field) {
            if (field.type === "radio") {
                var group = form.querySelectorAll(
                    'input[type="radio"][name="' + field.name + '"]',
                );
                var any = Array.prototype.some.call(group, function (radio) {
                    return radio.checked;
                });

                return any ? "" : group[0].dataset.requiredMessage || "";
            }

            if (field.type === "checkbox") {
                return field.checked ? "" : field.dataset.requiredMessage || "";
            }

            if (field.required && field.value.trim() === "") {
                return (
                    field.dataset.requiredMessage || "This field is required."
                );
            }

            return "";
        }

        function fieldsOf(panel) {
            var seen = {};

            return Array.prototype.filter.call(
                panel.querySelectorAll("input[name], select[name]"),
                function (field) {
                    if (field.disabled || field.type === "hidden") {
                        return false;
                    }

                    if (field.closest("[hidden]")) {
                        return false;
                    }

                    if (field.type === "radio") {
                        if (seen[field.name]) {
                            return false;
                        }

                        seen[field.name] = true;
                    }

                    return true;
                },
            );
        }

        function validatePanel(index) {
            var firstInvalid = null;

            fieldsOf(panels[index]).forEach(function (field) {
                var message = messageFor(field);

                setError(field.name, message);

                if (message !== "" && !firstInvalid) {
                    firstInvalid = field;
                }
            });

            if (!firstInvalid && indicators[index]) {
                indicators[index].classList.remove("has-error");
            }

            return firstInvalid;
        }

        // Live feedback: clear / re-check a field as soon as it is edited.
        form.addEventListener("input", function (event) {
            if (event.target.name) {
                setError(event.target.name, messageFor(event.target));
            }
        });

        form.addEventListener("change", function (event) {
            if (event.target.name) {
                setError(event.target.name, messageFor(event.target));
            }
        });

        /* ---------- review ---------- */
        function optionText(select) {
            var option = select.options[select.selectedIndex];

            return select.value && option ? option.text.trim() : "—";
        }

        function fillReview() {
            var values = {
                gender_id: optionText(form.elements.gender_id),
                student_number:
                    form.elements.student_number.value.trim() || "—",
                program_id: optionText(form.elements.program_id),
                year_id: optionText(form.elements.year_id),
                section_id: optionText(form.elements.section_id),
                privacy_acknowledged: form.elements.privacy_acknowledged.checked
                    ? "Acknowledged"
                    : "Not yet acknowledged",
            };

            var ladderRow = form.querySelector(
                '[data-review-row="ladderized"]',
            );
            var offered = ladderWrap && !ladderWrap.hidden;

            if (ladderRow) {
                ladderRow.hidden = !offered;
            }

            if (offered) {
                var chosen = form.querySelector(
                    'input[name="ladderized"]:checked',
                );
                values.ladderized = chosen
                    ? chosen.value === "1"
                        ? "Yes"
                        : "No"
                    : "—";
            }

            Object.keys(values).forEach(function (key) {
                var cell = form.querySelector(
                    '[data-review-value="' + key + '"]',
                );

                if (cell) {
                    cell.textContent = values[key];
                }
            });
        }

        /* ---------- progress + buttons ---------- */
        function renderChrome() {
            indicators.forEach(function (item, index) {
                var isCurrent = index === current;
                var isDone = !isCurrent && completed[index] === true;

                item.classList.toggle("is-current", isCurrent);
                item.classList.toggle("is-done", isDone);

                var dot = item.querySelector(".ap-step-dot");

                if (dot) {
                    dot.innerHTML = isDone
                        ? '<i class="bi bi-check-lg" aria-hidden="true"></i>'
                        : String(index + 1);
                }

                if (isCurrent) {
                    item.setAttribute("aria-current", "step");
                } else {
                    item.removeAttribute("aria-current");
                }
            });

            backButton.hidden = current === 0;
            nextButton.hidden = current === last;
            submitButton.hidden = current !== last;
        }

        function focusPanel(index) {
            var title = panels[index].querySelector(".ap-step-title");

            if (title) {
                title.focus({ preventScroll: true });
            }
        }

        function go(index) {
            if (index === current || animating || index < 0 || index > last) {
                return;
            }

            var direction = index > current ? "forward" : "back";
            var from = panels[current];
            var to = panels[index];

            current = index;

            if (index === last) {
                fillReview();
            }

            renderChrome();

            if (prefersReducedMotion()) {
                panels.forEach(function (panel) {
                    panel.classList.remove("is-active");
                });
                to.classList.add("is-active");
                focusPanel(index);
                return;
            }

            animating = true;
            viewport.style.height = viewport.offsetHeight + "px";
            from.classList.add("is-leaving-" + direction);

            window.setTimeout(function () {
                from.classList.remove(
                    "is-active",
                    "is-leaving-forward",
                    "is-leaving-back",
                );
                to.classList.add("is-active", "is-entering-" + direction);

                // Measure the new panel, then animate the height to it.
                var startHeight = viewport.style.height;
                viewport.style.height = "auto";
                var targetHeight = viewport.offsetHeight;
                viewport.style.height = startHeight;
                void viewport.offsetHeight;
                viewport.style.height = targetHeight + "px";

                void to.offsetHeight;
                to.classList.remove("is-entering-forward", "is-entering-back");

                window.setTimeout(function () {
                    viewport.style.height = "";
                    animating = false;
                    focusPanel(index);
                }, 280);
            }, 120);
        }

        /* ---------- controls ---------- */
        function attemptNext() {
            var invalid = validatePanel(current);

            if (invalid) {
                invalid.focus({ preventScroll: false });
                return;
            }

            completed[current] = true;
            go(current + 1);
        }

        nextButton.addEventListener("click", attemptNext);

        backButton.addEventListener("click", function () {
            go(current - 1);
        });

        form.addEventListener("click", function (event) {
            var edit = event.target.closest("[data-goto-step]");

            if (edit) {
                go(Number(edit.getAttribute("data-goto-step")) - 1);
            }
        });

        // Enter inside a field moves forward instead of submitting early.
        form.addEventListener("keydown", function (event) {
            if (
                event.key === "Enter" &&
                current < last &&
                event.target.tagName === "INPUT" &&
                event.target.type !== "checkbox" &&
                event.target.type !== "radio"
            ) {
                event.preventDefault();
                attemptNext();
            }
        });

        // Final submit: every step must pass here too; the server validates again.
        form.addEventListener("submit", function (event) {
            if (current < last) {
                event.preventDefault();
                attemptNext();
                return;
            }

            for (var index = 0; index <= last; index += 1) {
                var invalid = validatePanel(index);

                if (invalid) {
                    event.preventDefault();

                    if (indicators[index]) {
                        indicators[index].classList.add("has-error");
                    }

                    completed[index] = false;
                    go(index);
                    window.setTimeout(function () {
                        invalid.focus();
                    }, 420);
                    return;
                }
            }
        });

        /* ---------- start ---------- */
        var start = Math.min(
            Math.max((parseInt(form.dataset.initialStep, 10) || 1) - 1, 0),
            last,
        );

        indicators.forEach(function (item, index) {
            if (index !== start && !item.classList.contains("has-error")) {
                completed[index] = start > index || start === last;
            }
        });

        current = start;
        panels[start].classList.add("is-active");

        if (start === last) {
            fillReview();
        }

        renderChrome();
    })();

    /* ------------------------------------------------------------------
       7. Focus the first field on desktop (not on touch devices)
       ------------------------------------------------------------------ */
    focusFirstField();

    /* ------------------------------------------------------------------
       8. Restore the UI when returning via browser Back / Forward (bfcache)
       ------------------------------------------------------------------ */
    window.addEventListener("pageshow", function (event) {
        if (!event.persisted) {
            return;
        }

        isNavigating = false;
        page.classList.remove("is-leaving", "is-arriving");

        document
            .querySelectorAll(".is-selected, .is-busy, [aria-busy]")
            .forEach(function (el) {
                el.classList.remove("is-selected", "is-busy");
                el.removeAttribute("aria-busy");
            });

        document.querySelectorAll(".ap-form").forEach(function (form) {
            form.querySelectorAll("input, button").forEach(function (el) {
                el.disabled = false;
            });
        });

        var overlay = document.getElementById("apIdpOverlay");

        if (overlay) {
            overlay.hidden = true;
        }
    });
})();

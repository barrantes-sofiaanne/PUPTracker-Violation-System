/**
 * PUPTracker Admin — Unified UI helpers
 * Toast notifications, confirmation modals, and loading-state controls.
 * Loaded globally from layouts/admin.blade.php.
 */
(function () {
    "use strict";

    /* ------------------------------------------------------------------ *
     * Toast notifications
     * ------------------------------------------------------------------ */
    const TOAST_ICONS = {
        success: "bi-check-circle-fill",
        error: "bi-x-octagon-fill",
        warning: "bi-exclamation-triangle-fill",
        info: "bi-info-circle-fill",
    };

    function getToastContainer() {
        let container = document.getElementById("adminToastContainer");
        if (!container) {
            container = document.createElement("div");
            container.id = "adminToastContainer";
            container.className = "admin-toast-container";
            container.setAttribute("aria-live", "polite");
            container.setAttribute("aria-atomic", "false");
            document.body.appendChild(container);
        }
        return container;
    }

    function showToast(type, message, options) {
        const opts = Object.assign(
            { duration: 4000, closable: true },
            options || {},
        );
        const container = getToastContainer();
        const toast = document.createElement("div");
        toast.className = "admin-toast " + (type || "info");
        toast.setAttribute("role", "status");

        const icon = TOAST_ICONS[type] || TOAST_ICONS.info;

        toast.innerHTML =
            '<span class="admin-toast-icon" aria-hidden="true"><i class="bi ' +
            icon +
            '"></i></span>' +
            '<span class="admin-toast-body"></span>' +
            (opts.closable
                ? '<button type="button" class="admin-toast-close" aria-label="Close notification"><i class="bi bi-x-lg"></i></button>'
                : "");

        toast.querySelector(".admin-toast-body").textContent = message;

        const closeBtn = toast.querySelector(".admin-toast-close");
        if (closeBtn) {
            closeBtn.addEventListener("click", function () {
                dismissToast(toast);
            });
        }

        container.appendChild(toast);

        if (opts.duration > 0) {
            setTimeout(function () {
                dismissToast(toast);
            }, opts.duration);
        }

        return toast;
    }

    function dismissToast(toast) {
        if (!toast || toast.classList.contains("leaving")) {
            return;
        }
        toast.classList.add("leaving");
        toast.addEventListener("animationend", function () {
            toast.remove();
        });
        // Fallback in case animationend doesn't fire.
        setTimeout(function () {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }

    /* ------------------------------------------------------------------ *
     * Confirmation modals
     * A confirmation button with [data-target="modalId"] will:
     *   - submit a form with id "confirm-form-<modalId>" if present, or
     *   - dispatch a CustomEvent "puptracker:confirm-<modalId>".
     * ------------------------------------------------------------------ */
    function initConfirmModals() {
        document.addEventListener("click", function (event) {
            const actionBtn = event.target.closest(".admin-confirm-action");
            if (!actionBtn) {
                return;
            }

            const modalId = actionBtn.getAttribute("data-target");
            if (!modalId) {
                return;
            }

            const form = document.getElementById("confirm-form-" + modalId);
            if (form) {
                form.submit();
                return;
            }

            const modalEl = document.getElementById(modalId);
            const instance = window.bootstrap
                ? bootstrap.Modal.getInstance(modalEl)
                : null;
            if (instance) {
                instance.hide();
            }

            document.dispatchEvent(
                new CustomEvent("puptracker:confirm-" + modalId),
            );
        });
    }

    /* ------------------------------------------------------------------ *
     * data-confirm forms
     * Any <form data-confirm="Message"> intercepts submit and shows a
     * SweetAlert2 confirmation (consistent with the rest of the app).
     * Optional data-confirm-title and data-confirm-confirm-label override
     * the dialog copy. No browser confirm() dialogs.
     * ------------------------------------------------------------------ */
    function initDataConfirmForms() {
        document.addEventListener("submit", function (event) {
            const form = event.target.closest("form[data-confirm]");
            if (!form || !window.Swal) {
                return;
            }

            event.preventDefault();

            Swal.fire({
                title:
                    form.getAttribute("data-confirm-title") || "Are you sure?",
                text:
                    form.getAttribute("data-confirm") ||
                    "This action cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#800000",
                cancelButtonColor: "#6b7280",
                confirmButtonText:
                    form.getAttribute("data-confirm-confirm-label") ||
                    "Confirm",
                cancelButtonText: "Cancel",
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    }

    /* ------------------------------------------------------------------ *
     * Loading helpers
     * window.PUPLoading.start('id') / stop('id')
     * If the loading element has [data-button="btnId"], that button is
     * disabled (and its label replaced by the loading label) while active.
     * ------------------------------------------------------------------ */
    function setLoading(id, active) {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }

        if (active) {
            el.classList.remove("d-none");
        } else {
            el.classList.add("d-none");
        }

        const buttonId = el.getAttribute("data-button");
        if (buttonId) {
            const btn = document.getElementById(buttonId);
            if (btn) {
                if (active) {
                    btn.dataset.pupOriginalHtml = btn.innerHTML;
                    btn.disabled = true;
                    const label = el.querySelector(".admin-loading-label");
                    const text = label ? label.textContent : "Please wait...";
                    btn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' +
                        text;
                } else {
                    btn.disabled = false;
                    if (btn.dataset.pupOriginalHtml) {
                        btn.innerHTML = btn.dataset.pupOriginalHtml;
                        delete btn.dataset.pupOriginalHtml;
                    }
                }
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Global helpers
     * ------------------------------------------------------------------ */
    window.PUPToast = {
        success: function (message, options) {
            return showToast("success", message, options);
        },
        error: function (message, options) {
            return showToast("error", message, options);
        },
        warning: function (message, options) {
            return showToast("warning", message, options);
        },
        info: function (message, options) {
            return showToast("info", message, options);
        },
        show: showToast,
        dismiss: dismissToast,
    };

    window.PUPConfirm = {
        /**
         * Confirm a destructive/non-destructive action.
         * If a form id is given, the form is submitted on confirm.
         * Otherwise, resolves true/false (usable with the confirm modal).
         */
        submitForm: function (formId) {
            const form = document.getElementById(formId);
            if (form) {
                form.submit();
            }
        },
    };

    window.PUPLoading = {
        start: function (id) {
            setLoading(id, true);
        },
        stop: function (id) {
            setLoading(id, false);
        },
    };

    /* ------------------------------------------------------------------ *
     * Duplicate-submission protection
     * Any <form data-submit-guard="Busy label"> disables its submit button
     * while the request is in flight and restores it if the request fails
     * (so the user can retry). Applies to normal (non-AJAX) form submits.
     * ------------------------------------------------------------------ */
    function initSubmitGuards() {
        document.addEventListener("submit", function (event) {
            const form = event.target.closest("form[data-submit-guard]");
            if (!form) {
                return;
            }

            const submitter =
                event.submitter || form.querySelector('[type="submit"]');
            if (!submitter || submitter.disabled) {
                return;
            }

            const busyLabel =
                form.getAttribute("data-submit-guard") || "Submitting...";
            submitter.dataset.pupOriginalHtml = submitter.innerHTML;
            submitter.dataset.pupOriginalText = submitter.textContent.trim();
            submitter.disabled = true;
            submitter.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' +
                busyLabel;

            // If the browser aborts navigation (e.g. validation errors), restore the button.
            // Listen for invalid events to re-enable on client-side validation failure.
            const restore = function () {
                submitter.disabled = false;
                if (submitter.dataset.pupOriginalHtml) {
                    submitter.innerHTML = submitter.dataset.pupOriginalHtml;
                    delete submitter.dataset.pupOriginalHtml;
                }
            };

            form.addEventListener(
                "invalid",
                function () {
                    restore();
                },
                { once: true },
            );

            // Re-enable shortly after submit in case the server redirects back
            // with validation errors (best-effort; full page load resets anyway).
            window.setTimeout(restore, 8000);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        initConfirmModals();
        initDataConfirmForms();
        initSubmitGuards();
    });
})();

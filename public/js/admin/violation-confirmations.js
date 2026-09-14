/**
 * Admin → Violations → Violation Confirmations tab.
 *
 * Behaviour lifted verbatim from the former standalone
 * `admin/violations/confirmations.blade.php` inline script when the two pages
 * were combined. Extracted to a file because the markup now lives inside a tab
 * panel that is rendered on the shared Violations page.
 *
 * Routes are injected by the Blade view via `window.ViolationConfirmationRoutes`
 * so no URL is hard-coded here.
 */
document.addEventListener("DOMContentLoaded", function () {
    const routes = window.ViolationConfirmationRoutes || {};
    const confirmUrlTemplate = routes.confirm || "";
    const denyUrlTemplate = routes.deny || "";

    let currentViolationId = null;

    function renderEvidence(evidenceRaw) {
        const container = document.getElementById("confirmModalEvidence");
        if (!container) {
            return;
        }

        let items = [];
        try {
            items = evidenceRaw ? JSON.parse(evidenceRaw) : [];
        } catch (e) {
            items = [];
        }

        if (!items.length) {
            container.innerHTML =
                '<span class="text-muted small">No evidence attached.</span>';
            return;
        }

        container.innerHTML = items
            .map(function (ev) {
                const preview = ev.is_image
                    ? '<img src="' +
                      ev.url +
                      '" alt="evidence" style="max-width:110px;max-height:90px;object-fit:cover;border-radius:6px;display:block">'
                    : '<i class="bi bi-file-earmark-pdf-fill" style="font-size:2.2rem;color:#b91c1c"></i>';
                const methodTag =
                    ev.method === "camera"
                        ? '<span class="badge text-bg-light border mt-1" title="Captured with watermark">camera</span>'
                        : '<span class="badge text-bg-light border mt-1">upload</span>';
                const wmTag = ev.watermarked
                    ? '<span class="badge text-bg-success-subtle border mt-1" title="Watermark baked into image">watermarked</span>'
                    : "";
                return (
                    '<div style="text-align:center">' +
                    '<a href="' +
                    ev.url +
                    '" target="_blank" rel="noopener" title="' +
                    ev.name +
                    '">' +
                    preview +
                    "</a>" +
                    '<div class="small text-muted mt-1" style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' +
                    (ev.code || ev.name) +
                    "</div>" +
                    '<div class="d-flex justify-content-center gap-1 flex-wrap">' +
                    methodTag +
                    wmTag +
                    "</div>" +
                    '<button type="button" class="btn btn-sm btn-outline-secondary mt-1 evidence-security-events-btn" data-events-url="' +
                    ev.events_url +
                    '" data-evidence-code="' +
                    (ev.code || ev.name) +
                    '">' +
                    '<i class="bi bi-shield-lock me-1"></i>Security Events</button>' +
                    "</div>"
                );
            })
            .join("");
    }

    // Evidence security events timeline.
    const evidenceEventsModalEl = document.getElementById(
        "evidenceEventsModal",
    );
    const evidenceEventsList = document.getElementById("evidenceEventsList");
    const evidenceEventsCode = document.getElementById("evidenceEventsCode");
    const evidenceEventsEmpty = document.getElementById("evidenceEventsEmpty");
    const evidenceEventsLoading = document.getElementById(
        "evidenceEventsLoading",
    );

    function loadEvidenceEvents(eventsUrl) {
        if (!evidenceEventsModalEl) {
            return;
        }
        if (evidenceEventsList) {
            evidenceEventsList.innerHTML = "";
        }
        if (evidenceEventsCode) {
            evidenceEventsCode.textContent = "";
        }
        if (evidenceEventsLoading) {
            evidenceEventsLoading.classList.remove("d-none");
        }
        if (evidenceEventsEmpty) {
            evidenceEventsEmpty.classList.add("d-none");
        }

        fetch(eventsUrl, { headers: { Accept: "application/json" } })
            .then(function (resp) {
                if (!resp.ok) {
                    throw new Error("Unable to load events.");
                }
                return resp.json();
            })
            .then(function (data) {
                if (evidenceEventsLoading) {
                    evidenceEventsLoading.classList.add("d-none");
                }
                if (evidenceEventsCode) {
                    evidenceEventsCode.textContent =
                        data.evidence.code +
                        " — " +
                        (data.evidence.student || "N/A");
                }
                if (!data.events || !data.events.length) {
                    if (evidenceEventsEmpty) {
                        evidenceEventsEmpty.classList.remove("d-none");
                    }
                    return;
                }
                if (evidenceEventsList) {
                    evidenceEventsList.innerHTML = data.events
                        .map(function (e) {
                            const sevIcon =
                                e.severity === "warning" ||
                                e.severity === "critical"
                                    ? '<i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>'
                                    : '<i class="bi bi-info-circle-fill text-muted me-2"></i>';
                            let metaText = "";
                            if (e.meta) {
                                try {
                                    const m = JSON.parse(e.meta);
                                    metaText = m.detail ? " — " + m.detail : "";
                                } catch (err) {
                                    /* ignore */
                                }
                            }
                            return (
                                '<li class="list-group-item d-flex align-items-start">' +
                                sevIcon +
                                '<div class="flex-grow-1"><div class="fw-semibold small">' +
                                e.type +
                                "</div>" +
                                '<div class="text-muted small">' +
                                (e.created_at || "") +
                                metaText +
                                "</div>" +
                                '<div class="text-muted small" style="font-size:0.75rem">' +
                                (e.ip ? "IP " + e.ip : "") +
                                (e.user_agent
                                    ? " &middot; " + e.user_agent
                                    : "") +
                                "</div>" +
                                "</div></li>"
                            );
                        })
                        .join("");
                }
            })
            .catch(function () {
                if (evidenceEventsLoading) {
                    evidenceEventsLoading.classList.add("d-none");
                }
                if (evidenceEventsEmpty) {
                    evidenceEventsEmpty.textContent =
                        "Unable to load security events.";
                    evidenceEventsEmpty.classList.remove("d-none");
                }
            });
    }

    // Delegated: the tab panel and its rows can be re-rendered by a filter
    // submit, so binding directly to `.evidence-security-events-btn` would
    // silently stop working after pagination.
    document.addEventListener("click", function (event) {
        const btn = event.target.closest(".evidence-security-events-btn");
        if (!btn) {
            return;
        }
        const eventsUrl = btn.dataset.eventsUrl;
        if (!eventsUrl) {
            return;
        }
        const modal = bootstrap.Modal.getOrCreateInstance(
            evidenceEventsModalEl,
        );
        loadEvidenceEvents(eventsUrl);
        modal.show();
    });

    document
        .querySelectorAll('[data-bs-target="#violationDetailModal"]')
        .forEach(function (button) {
            button.addEventListener("click", function () {
                currentViolationId = button.dataset.violationId || "";
                document.getElementById("confirmModalStudent").textContent =
                    button.dataset.student || "-";
                document.getElementById("confirmModalViolation").textContent =
                    button.dataset.violation || "-";
                document.getElementById("confirmModalDate").textContent =
                    button.dataset.date || "-";
                document.getElementById("confirmModalRecordedBy").textContent =
                    button.dataset.recordedBy || "-";
                document.getElementById("confirmModalDescription").textContent =
                    button.dataset.description || "-";
                document.getElementById("confirmViolationForm").action =
                    confirmUrlTemplate.replace("__ID__", currentViolationId);
                document.getElementById("denyViolationForm").action =
                    denyUrlTemplate.replace("__ID__", currentViolationId);
                renderEvidence(button.dataset.evidence || "");
            });
        });

    const denyModalEl = document.getElementById("denyViolationModal");
    if (denyModalEl) {
        denyModalEl.addEventListener("show.bs.modal", function () {
            document.getElementById("denyModalStudent").textContent =
                document.getElementById("confirmModalStudent").textContent;
        });
    }
});

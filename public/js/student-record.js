document.addEventListener("DOMContentLoaded", function () {
    const confirmButton = document.getElementById("confirmRequestSanctionBtn");
    const modalElement = document.getElementById("requestSanctionConfirmModal");
    const modalInstance =
        modalElement && typeof bootstrap !== "undefined"
            ? bootstrap.Modal.getOrCreateInstance(modalElement)
            : null;
    let selectedViolationTypeId = null;
    let isSubmitting = false;
    async function submitSanctionRequest(violationTypeId) {
        if (!violationTypeId || isSubmitting) return;
        if (!modalInstance) {
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: "error",
                    title: "Confirmation Unavailable",
                    text: "The confirmation dialog could not be opened. Refresh the page and try again.",
                });
            }
            return;
        }
        if (typeof Swal === "undefined") {
            console.error("SweetAlert2 is not loaded.");
            return;
        }
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta?.getAttribute("content");
        if (!csrfToken) {
            Swal.fire({
                icon: "error",
                title: "Security Error",
                text: "The security token is missing. Refresh the page and try again.",
            });
            return;
        }
        isSubmitting = true;
        if (confirmButton) {
            confirmButton.disabled = true;
        }
        try {
            const response = await fetch("/student/request-sanction", {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    violation_type_id: violationTypeId,
                }),
            });
            const contentType = response.headers.get("content-type") || "";
            let data = {};
            if (contentType.includes("application/json")) {
                data = await response.json();
            }
            if (!response.ok || !data.success) {
                let message = data.message;
                if (response.status === 419) {
                    message =
                        "Your session may have expired. Refresh the page and log in again.";
                } else if (response.status === 401) {
                    message = "Please log in to your student account again.";
                } else if (response.status === 403) {
                    message = "You are not authorized to submit this request.";
                } else if (!message && response.redirected) {
                    message =
                        "Your session may have expired. Refresh the page and log in again.";
                } else if (!message) {
                    message = `The request failed (HTTP ${response.status}).`;
                }
                throw new Error(
                    message || "Unable to submit the sanction request.",
                );
            }
            modalInstance.hide();
            await Swal.fire({
                icon: "success",
                title: "Request Submitted",
                text:
                    data.message ||
                    "Your sanction request has been submitted successfully.",
            });
            window.location.reload();
        } catch (error) {
            console.error("Sanction request error:", error);
            Swal.fire({
                icon: "error",
                title: "Unable to Submit Request",
                text:
                    error.message ||
                    "An unexpected error occurred. Please try again.",
            });
        } finally {
            isSubmitting = false;
            if (confirmButton) {
                confirmButton.disabled = false;
            }
        }
    }
    // Open the confirmation modal for a sanction request.
    document.addEventListener("click", function (event) {
        const target = event.target instanceof Element ? event.target : null;
        const button = target?.closest(".request-sanction-btn");
        if (!button) return;
        selectedViolationTypeId = button.dataset.violationTypeId || null;
        if (!selectedViolationTypeId) {
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: "error",
                    title: "Missing Information",
                    text: "The violation type could not be identified.",
                });
            }
            return;
        }
        if (!modalInstance) {
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: "error",
                    title: "Confirmation Unavailable",
                    text: "The confirmation dialog could not be opened. Refresh the page and try again.",
                });
            }
            return;
        }
        modalInstance.show();
    });
    // Submit only after the student confirms.
    if (confirmButton) {
        confirmButton.addEventListener("click", function () {
            submitSanctionRequest(selectedViolationTypeId);
        });
    }
    // Individual Violations Log filters.
    const statusFilter = document.getElementById("filterSanctionStatus");
    const filterForm = document.querySelector(
        "#violationRecord .individual-log-filters",
    );
    const filterCount = document.getElementById("individualFilterCount");
    const tableBody = document.querySelector(
        "#violationRecord .individual-violations-table tbody",
    );
    function applySanctionStatusFilter() {
        if (!statusFilter || !tableBody) return;
        const rows = Array.from(
            tableBody.querySelectorAll("tr[data-sanction-status]"),
        );
        const selectedStatus = statusFilter.value || "all";
        let visibleCount = 0;
        rows.forEach(function (row) {
            const rowStatus = row.dataset.sanctionStatus || "needs";
            const matches =
                selectedStatus === "all" || rowStatus === selectedStatus;
            row.hidden = !matches;
            if (matches) {
                visibleCount++;
            }
        });
        if (filterCount) {
            filterCount.textContent = `${visibleCount} of ${rows.length} records shown`;
        }
    }
    // Apply the status filter on initial page load.
    applySanctionStatusFilter();
    // Automatically submit all filters, including sanction status.
    // This preserves filter selections in the URL when the page reloads.
    if (filterForm) {
        const autoApplySelectors = [
            '[name="category_id"]',
            '[name="violation_type_id"]',
            '[name="date_from"]',
            '[name="date_to"]',
            '[name="sanction_status"]',
        ];
        autoApplySelectors.forEach(function (selector) {
            const control = filterForm.querySelector(selector);
            if (!control) return;
            control.addEventListener("change", function () {
                filterForm.requestSubmit();
            });
        });
    }
});

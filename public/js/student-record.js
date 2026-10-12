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
        if (confirmButton) confirmButton.disabled = true;

        let succeeded = false;

        try {
            const response = await fetch("/student/request-sanction", {
                method: "POST",
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
                throw new Error(
                    data.message ||
                        (response.status === 419
                            ? "Your session may have expired. Refresh the page and log in again."
                            : response.status === 401
                              ? "Please log in to your student account again."
                              : response.status === 403
                                ? "You are not authorized to submit this request."
                                : `The request failed (HTTP ${response.status}).`),
                );
            }

            succeeded = true;

            await Swal.fire({
                icon: "success",
                title: "Request Submitted",
                text:
                    data.message ||
                    "Your sanction request has been submitted successfully.",
            });

            window.location.reload();
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "Unable to Submit Request",
                text:
                    error.message ||
                    "An unexpected error occurred. Please try again.",
            });
        } finally {
            isSubmitting = false;
            if (confirmButton) confirmButton.disabled = false;

            // Keep the modal open on failure so the student can retry.
            if (succeeded && modalInstance) {
                modalInstance.hide();
            }
        }
    }

    document.addEventListener("click", function (event) {
        const button = event.target.closest(".request-sanction-btn");
        if (!button) return;

        selectedViolationTypeId = button.dataset.violationTypeId || null;

        if (!selectedViolationTypeId) {
            Swal.fire({
                icon: "error",
                title: "Missing Information",
                text: "The violation type could not be identified.",
            });
            return;
        }

        if (modalInstance) {
            modalInstance.show();
        } else {
            submitSanctionRequest(selectedViolationTypeId);
        }
    });

    if (confirmButton) {
        confirmButton.addEventListener("click", function () {
            submitSanctionRequest(selectedViolationTypeId);
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const confirmButton = document.getElementById("confirmRequestSanctionBtn");
    const modalElement = document.getElementById("requestSanctionConfirmModal");

    const modalInstance =
        modalElement && typeof bootstrap !== "undefined"
            ? new bootstrap.Modal(modalElement)
            : null;

    let selectedViolationTypeId = null;
    let isSubmitting = false;

    const submitSanctionRequest = function (violationTypeId) {
        if (!violationTypeId || isSubmitting) {
            return;
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : null;

        if (!csrfToken) {
            Swal.fire({
                icon: "error",
                title: "Security Error",
                text: "The security token is missing. Please refresh the page and try again.",
            });
            return;
        }

        isSubmitting = true;

        if (confirmButton) {
            confirmButton.disabled = true;
        }

        fetch("/student/request-sanction", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
                Accept: "application/json",
            },
            body: JSON.stringify({
                violation_type_id: violationTypeId,
            }),
        })
            .then(async function (response) {
                const data = await response.json();

                if (!response.ok && !data.message) {
                    data.message = "Unable to submit your sanction request.";
                }

                return data;
            })
            .then(function (data) {
                Swal.fire({
                    icon: data.success ? "success" : "error",
                    title: data.success ? "Success" : "Error",
                    text: data.message || "The request could not be processed.",
                });

                if (data.success) {
                    location.reload();
                }
            })
            .catch(function () {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "An unexpected error occurred. Please try again.",
                });
            })
            .finally(function () {
                isSubmitting = false;

                if (confirmButton) {
                    confirmButton.disabled = false;
                }

                if (modalInstance) {
                    modalInstance.hide();
                }
            });
    };

    // Event delegation: supports Request Sanction and Request Again buttons.
    document.addEventListener("click", function (event) {
        const button = event.target.closest(".request-sanction-btn");

        if (!button) {
            return;
        }

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
            return;
        }

        submitSanctionRequest(selectedViolationTypeId);
    });

    if (confirmButton) {
        confirmButton.addEventListener("click", function () {
            submitSanctionRequest(selectedViolationTypeId);
        });
    }
});

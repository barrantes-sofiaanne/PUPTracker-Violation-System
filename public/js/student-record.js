document.addEventListener("DOMContentLoaded", function () {
    const buttons = document.querySelectorAll(".request-sanction-btn");
    const confirmButton = document.getElementById("confirmRequestSanctionBtn");
    const modalElement = document.getElementById("requestSanctionConfirmModal");
    const modalInstance = modalElement
        ? new bootstrap.Modal(modalElement)
        : null;
    let selectedViolationTypeId = null;

    const submitSanctionRequest = function (violationTypeId) {
        if (!violationTypeId) {
            return;
        }

        if (confirmButton) {
            confirmButton.disabled = true;
        }

        fetch("/student/request-sanction", {
            method: "POST",

            headers: {
                "Content-Type": "application/json",

                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content"),

                Accept: "application/json",
            },

            body: JSON.stringify({
                violation_type_id: violationTypeId,
            }),
        })
            .then(function (response) {
                return response.json();
            })

            .then(function (data) {
                Swal.fire({
                    icon: data.success ? "success" : "error",
                    title: data.success ? "Success" : "Error",
                    text: data.message,
                });

                if (data.success) {
                    location.reload();
                }
            })

            .catch(function () {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "An unexpected error occurred.",
                });
            })

            .finally(function () {
                if (confirmButton) {
                    confirmButton.disabled = false;
                }

                if (modalInstance) {
                    modalInstance.hide();
                }
            });
    };

    buttons.forEach(function (button) {
        button.addEventListener("click", function () {
            selectedViolationTypeId = this.dataset.violationTypeId || null;

            if (modalInstance) {
                modalInstance.show();
                return;
            }

            submitSanctionRequest(selectedViolationTypeId);
        });
    });

    if (confirmButton) {
        confirmButton.addEventListener("click", function () {
            submitSanctionRequest(selectedViolationTypeId);
        });
    }
});

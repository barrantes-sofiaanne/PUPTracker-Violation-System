document.addEventListener("DOMContentLoaded", function () {
    const buttons = document.querySelectorAll(".request-sanction-btn");

    buttons.forEach(function (button) {
        button.addEventListener("click", function () {
            if (
                !confirm(
                    "Are you sure you want to request this disciplinary sanction?",
                )
            ) {
                return;
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
                    violation_type_id: this.dataset.violationTypeId,
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
                });
        });
    });
});

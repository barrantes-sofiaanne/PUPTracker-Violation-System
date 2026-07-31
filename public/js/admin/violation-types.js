document.addEventListener("DOMContentLoaded", function () {
    const modal = new bootstrap.Modal(
        document.getElementById("violationTypeModal"),
    );

    const form = document.getElementById("violationTypeForm");

    /*
    |--------------------------------------------------------------------------
    | Add Violation Type
    |--------------------------------------------------------------------------
    */

    document.addEventListener("click", function (e) {
        const btn = e.target.closest(".addTypeBtn");

        if (!btn) return;

        form.reset();
        document.getElementById("violation_type_id").value = "";
        document.getElementById("violation_category_id").value = btn.dataset.id;
        document.getElementById("category_name_display").value =
            btn.dataset.name;
        document.getElementById("violationTypeModalLabel").textContent =
            "Add Violation Type";
        modal.show();
    });

    document.addEventListener("click", async function (e) {
        const editBtn = e.target.closest(".editTypeBtn");

        if (!editBtn) return;

        try {
            const response = await fetch(
                window.ViolationRoutes.typeShow.replace(
                    ":id",
                    editBtn.dataset.id,
                ),
                {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                },
            );

            const result = await response.json();

            if (!response.ok) {
                throw result;
            }

            form.reset();
            document.getElementById("violation_type_id").value =
                result.violation_type_id;
            document.getElementById("violation_category_id").value =
                result.violation_category_id;
            document.getElementById("category_name_display").value =
                result.violation_category?.category_name || "";
            document.getElementById("violation_type").value =
                result.violation_type || "";
            document.getElementById("violation_description").value =
                result.violation_description || "";
            document.getElementById("resolution_number").value =
                result.resolution_number || "";
            document.getElementById("severity_level").value =
                result.severity_level || "1";
            document.getElementById("violationTypeModalLabel").textContent =
                "Edit Violation Type";

            modal.show();
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "Unable to load violation type",
                text:
                    error.message ||
                    "The selected violation type could not be loaded.",
            });
        }
    });

    document.addEventListener("click", function (e) {
        const deleteBtn = e.target.closest(".deleteTypeBtn");

        if (!deleteBtn) return;

        Swal.fire({
            title: "Delete violation type?",
            text: "This action cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Delete",
        }).then(async function (result) {
            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await fetch(
                    window.ViolationRoutes.typeDelete.replace(
                        ":id",
                        deleteBtn.dataset.id,
                    ),
                    {
                        method: "DELETE",
                        headers: {
                            Accept: "application/json",
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,
                        },
                    },
                );

                const payload = await response.json();

                if (!response.ok) {
                    throw payload;
                }

                await loadCategories();

                Swal.fire({
                    icon: "success",
                    title: "Deleted",
                    text:
                        payload.message ||
                        "Violation type deleted successfully.",
                });
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Unable to delete",
                    text:
                        error.message || "Violation type could not be deleted.",
                });
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        saveViolationType();
    });
});
async function saveViolationType() {
    const saveButton = document.getElementById("saveViolationTypeBtn");

    saveButton.disabled = true;

    saveButton.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2"></span>
        Saving...
    `;

    const id = document.getElementById("violation_type_id").value;

    const data = {
        violation_category_id: document.getElementById("violation_category_id")
            .value,

        violation_type: document.getElementById("violation_type").value,

        resolution_number: document.getElementById("resolution_number").value,

        violation_description: document.getElementById("violation_description")
            .value,

        severity_level: document.getElementById("severity_level").value,
    };

    let url = window.ViolationRoutes.typeStore;

    let method = "POST";

    if (id !== "") {
        url = window.ViolationRoutes.typeUpdate.replace(":id", id);

        method = "PUT";
    }

    try {
        const response = await fetch(url, {
            method: method,

            headers: {
                "Content-Type": "application/json",

                Accept: "application/json",

                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },

            body: JSON.stringify(data),
        });

        const result = await response.json();

        if (!response.ok) {
            throw result;
        }

        bootstrap.Modal.getInstance(
            document.getElementById("violationTypeModal"),
        ).hide();

        document.getElementById("violationTypeForm").reset();
        saveButton.disabled = false;

        saveButton.innerHTML = `
            <i class="bi bi-save me-1"></i>
            Save Violation Type
        `;
        await loadCategories();

        await Swal.fire({
            icon: "success",
            title: "Success",
            text: result.message,
        });
    } catch (error) {
        saveButton.disabled = false;

        saveButton.innerHTML = `
            <i class="bi bi-save me-1"></i>
            Save Violation Type
        `;

        let message = "Unable to save violation type.";

        if (error.errors) {
            message = Object.values(error.errors)[0][0];
        } else if (error.message) {
            message = error.message;
        }

        Swal.fire({
            icon: "error",

            title: "Validation Error",

            text: message,
        });
    }

    saveButton.disabled = false;

    saveButton.innerHTML = `
            <i class="bi bi-save me-1"></i>
            Save Violation Type
        `;
}
async function loadCategories() {
    const container = document.getElementById("categoryAccordionContainer");

    if (!container) {
        return;
    }

    const response = await fetch(window.ViolationRoutes.categoryIndex, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });

    const html = await response.text();

    container.innerHTML = html;
}

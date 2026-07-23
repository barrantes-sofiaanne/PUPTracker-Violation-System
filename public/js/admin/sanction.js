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

        document.getElementById("violationTypeForm").reset();
        document.getElementById("violation_type_id").value = "";

        document.getElementById("violation_category_id").value = btn.dataset.id;

        document.getElementById("category_name_display").value =
            btn.dataset.name;

        document.getElementById("violationTypeModalLabel").textContent =
            "Add Violation Type";

        modal.show();
    });
    document.addEventListener("click", async function (e) {
        const button = e.target.closest(".editSanctionBtn");

        if (!button) return;

        editingId = button.dataset.id;

        try {
            const response = await fetch(
                window.ViolationRoutes.sanctionShow.replace(":id", editingId),
            );

            const sanction = await response.json();

            form.reset();

            document.getElementById("sanction_id").value =
                sanction.disciplinary_sanction_id;

            document.getElementById("violation_type_id").value =
                sanction.violation_type_id;

            document.getElementById("offense_level").value =
                sanction.offense_level;

            document.getElementById("disciplinary_sanction").value =
                sanction.disciplinary_sanction;

            document.getElementById("sanctionModalLabel").textContent =
                "Edit Disciplinary Sanction";

            sanctionModal.show();
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Unable to load disciplinary sanction.",
            });
        }
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

        document.getElementById("violationTypeForm").reset();

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
    document.addEventListener("click", function (e) {
        const link = e.target.closest(".pagination a");

        if (!link) return;

        e.preventDefault();

        loadViolationTypes(link.href);
    });
    async function loadViolationTypes(url) {
        const response = await fetch(url, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        const html = await response.text();

        document.getElementById("typesTable11").innerHTML = html;
    }
}
async function loadCategories() {
    const response = await fetch(window.ViolationRoutes.categoryIndex, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });

    const html = await response.text();

    document.getElementById("categoryAccordionContainer").innerHTML = html;

    // Reattach the Add Type button events
}
document.addEventListener("DOMContentLoaded", function () {
    const sanctionModal = new bootstrap.Modal(
        document.getElementById("sanctionModal"),
    );

    const form = document.getElementById("sanctionForm");

    let editingId = null;

    document.addEventListener("click", function (e) {
        const button = e.target.closest(".addSanctionBtn");

        if (!button) return;

        editingId = null;

        form.reset();

        document.getElementById("sanction_id").value = "";

        document.getElementById("sanctionModalLabel").textContent =
            "Add Disciplinary Sanction";

        sanctionModal.show();
    });
});
form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const formData = new FormData(form);

    const url = editingId
        ? window.ViolationRoutes.sanctionUpdate.replace(":id", editingId)
        : window.ViolationRoutes.sanctionStore;

    if (editingId) {
        formData.append("_method", "PUT");
    }

    try {
        const response = await fetch(url, {
            method: "POST",

            headers: {
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,

                "X-Requested-With": "XMLHttpRequest",
            },

            body: formData,
        });

        const data = await response.json();

        if (!response.ok) {
            throw data;
        }

        bootstrap.Modal.getInstance(
            document.getElementById("sanctionModal"),
        ).hide();

        Swal.fire({
            icon: "success",

            title: "Success",

            text: data.message,
        });

        loadSanctions();
    } catch (error) {
        Swal.fire({
            icon: "error",

            title: "Error",

            text: error.message ?? "Unable to save sanction.",
        });
    }
});
async function loadViolationTypes() {
    const response = await fetch(window.ViolationRoutes.sanctionIndex, {
        headers: {
            "X-Requested-With": "XMLHttpRequest",
        },
    });

    const data = await response.json();

    const select = document.getElementById("violation_type_id");

    select.innerHTML = '<option value="">-- Select Violation Type --</option>';

    data.violationTypes.forEach(function (type) {
        const option = document.createElement("option");

        option.value = type.violation_type_id;

        option.textContent = type.violation_type;

        select.appendChild(option);
    });
    document.addEventListener("click", function (e) {
        const button = e.target.closest(".deleteSanctionBtn");

        if (!button) return;

        const sanctionId = button.dataset.id;

        Swal.fire({
            title: "Delete Disciplinary Sanction?",

            text: "This action cannot be undone.",

            icon: "warning",

            showCancelButton: true,

            confirmButtonColor: "#d33",

            cancelButtonColor: "#6c757d",

            confirmButtonText: "Yes, Delete",
        }).then(async (result) => {
            if (!result.isConfirmed) return;

            try {
                const response = await fetch(
                    window.ViolationRoutes.sanctionDelete.replace(
                        ":id",
                        sanctionId,
                    ),
                    {
                        method: "POST",

                        headers: {
                            "X-CSRF-TOKEN": document.querySelector(
                                'meta[name="csrf-token"]',
                            ).content,

                            "X-Requested-With": "XMLHttpRequest",
                        },

                        body: new URLSearchParams({
                            _method: "DELETE",
                        }),
                    },
                );

                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                Swal.fire({
                    icon: "success",

                    title: "Deleted",

                    text: data.message,
                });

                await loadSanctions();
            } catch (error) {
                Swal.fire({
                    icon: "error",

                    title: "Error",

                    text:
                        error.message ??
                        "Unable to delete disciplinary sanction.",
                });
            }
        });
    });
    async function loadSanctions() {
        const response = await fetch(window.ViolationRoutes.sanctionIndex, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        const html = await response.text();

        document.getElementById("sanctionContainer").innerHTML = html;
    }
}

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

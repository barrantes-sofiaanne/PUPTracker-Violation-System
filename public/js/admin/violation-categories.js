document.addEventListener("DOMContentLoaded", function () {
    const modal = new bootstrap.Modal(document.getElementById("categoryModal"));

    const form = document.getElementById("categoryForm");

    const modalTitle = document.getElementById("categoryModalTitle");

    const categoryId = document.getElementById("category_id");

    /*
    |--------------------------------------------------------------------------
    | Add Category
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll(".add-category").forEach(function (button) {
        button.addEventListener("click", function () {
            form.reset();

            categoryId.value = "";

            modalTitle.textContent = "Add Category";
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        saveCategory();
    });
});
async function saveCategory() {
    const saveButton = document.getElementById("saveCategoryBtn");

    saveButton.disabled = true;

    saveButton.innerHTML = `
    <span class="spinner-border spinner-border-sm me-2"></span>
    Saving...
`;
    const id = document.getElementById("category_id").value;

    const data = {
        category_name: document.getElementById("category_name").value,
    };

    let url = window.ViolationRoutes.categoryStore;
    let method = "POST";

    if (id !== "") {
        url = window.ViolationRoutes.categoryUpdate.replace(":id", id);
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

        const modal = bootstrap.Modal.getOrCreateInstance(
            document.getElementById("categoryModal"),
        );

        modal.hide();

        document.getElementById("categoryForm").reset();
        document.getElementById("category_id").value = "";

        await loadCategories();

        saveButton.disabled = false;

        saveButton.innerHTML = `
    <i class="bi bi-save me-1"></i>
    Save Category
`;

        Swal.fire({
            icon: "success",
            title: "Success!",
            text: result.message || "Category saved successfully.",
            timer: 1200,
            showConfirmButton: false,
        });
    } catch (error) {
        saveButton.disabled = false;

        saveButton.innerHTML = `
        <i class="bi bi-save me-1"></i>
        Save Category
    `;

        let message = "Unable to save category.";

        if (error.errors && error.errors.category_name) {
            message = error.errors.category_name[0];
        } else if (error.message) {
            message = error.message;
        }

        Swal.fire({
            icon: "error",
            title: "Validation Error",
            text: message,
        });
    }
}

document.addEventListener("click", function (e) {
    const btn = e.target.closest(".delete-category");

    if (!btn) return;

    Swal.fire({
        title: "Delete Category?",

        text: "This action cannot be undone.",

        icon: "warning",

        showCancelButton: true,

        confirmButtonText: "Delete",
    }).then(async function (result) {
        if (!result.isConfirmed) {
            return;
        }

        const response = await fetch(
            window.ViolationRoutes.categoryDelete.replace(
                ":id",
                btn.dataset.id,
            ),

            {
                method: "DELETE",

                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]',
                    ).content,
                },
            },
        );

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: "success",

                title: data.message,

                timer: 1200,

                showConfirmButton: false,
            });

            await loadCategories();
        } else {
            Swal.fire({
                icon: "error",

                title: "Unable to delete",

                text: data.message,
            });
        }
    });

    $(document).on("click", ".addTypeBtn", function () {
        const categoryId = $(this).data("id");

        const categoryName = $(this).data("name");

        $("#violationTypeForm")[0].reset();

        $("#violation_type_id").val("");

        $("#violation_category_id").val(categoryId);

        $("#category_name_display").val(categoryName);

        $("#violationTypeModalLabel").text("Add Violation Type");

        $("#violationTypeModal").modal("show");
    });
});
async function loadCategories() {
    try {
        const response = await fetch(window.ViolationRoutes.categoryIndex, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        if (!response.ok) {
            throw new Error("Unable to reload categories.");
        }

        const html = await response.text();

        document.getElementById("categoryAccordionContainer").innerHTML = html;
    } catch (error) {
        console.error(error);

        Swal.fire({
            icon: "error",
            title: "Reload Failed",
            text: "Unable to refresh the category list.",
        });
    }
}

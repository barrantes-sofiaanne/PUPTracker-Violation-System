document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById("sanctionModal");
    if (!modalElement) {
        return;
    }

    const sanctionModal = new bootstrap.Modal(modalElement);
    const form = document.getElementById("sanctionForm");
    const submitButton = document.getElementById("saveSanctionBtn");
    const offenseLevelSelect = document.getElementById("offense_level");
    const offenseLevelCustomWrap = document.getElementById(
        "offenseLevelCustomWrap",
    );
    const offenseLevelCustom = document.getElementById("offense_level_custom");

    let editingId = null;

    function syncOffenseLevelCustomField() {
        if (
            !offenseLevelSelect ||
            !offenseLevelCustomWrap ||
            !offenseLevelCustom
        ) {
            return;
        }

        const isCustom = offenseLevelSelect.value === "__other__";
        offenseLevelCustomWrap.classList.toggle("d-none", !isCustom);

        if (isCustom) {
            offenseLevelCustom.required = true;
            offenseLevelCustom.focus();
        } else {
            offenseLevelCustom.required = false;
        }
    }

    if (offenseLevelSelect) {
        offenseLevelSelect.addEventListener(
            "change",
            syncOffenseLevelCustomField,
        );
    }

    function resetForm() {
        form.reset();
        document.getElementById("sanction_id").value = "";
        document.getElementById("sanctionModalLabel").textContent =
            "Add Disciplinary Sanction";
        editingId = null;
        if (offenseLevelSelect) {
            offenseLevelSelect.value = "";
        }
        syncOffenseLevelCustomField();
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-save me-2"></i>Save';
        }
    }

    document.addEventListener("click", function (event) {
        const addButton = event.target.closest(".addSanctionBtn");
        if (addButton) {
            event.preventDefault();
            event.stopPropagation();
            resetForm();
            const violationTypeId = addButton.dataset.violationTypeId;
            if (violationTypeId) {
                document.getElementById("violation_type_id").value =
                    violationTypeId;
            }
            sanctionModal.show();
            return;
        }

        const editButton = event.target.closest(".edit-sanction-btn");
        if (editButton) {
            editingId = editButton.dataset.id;
            fetch(
                window.ViolationRoutes.sanctionShow.replace(":id", editingId),
                {
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "application/json",
                    },
                },
            )
                .then((response) => response.json())
                .then((sanction) => {
                    form.reset();
                    document.getElementById("sanction_id").value =
                        sanction.disciplinary_sanction_id;
                    document.getElementById("violation_type_id").value =
                        sanction.violation_type_id;
                    document.getElementById("disciplinary_sanction").value =
                        sanction.disciplinary_sanction;

                    // If the saved offense level is not in the preset list,
                    // put it in the custom input and select "Other".
                    const presetValues = [
                        "1st Offense",
                        "2nd Offense",
                        "3rd Offense",
                        "4th Offense",
                        "5th Offense",
                    ];
                    if (presetValues.includes(sanction.offense_level)) {
                        offenseLevelSelect.value = sanction.offense_level;
                        offenseLevelCustom.value = "";
                    } else {
                        offenseLevelSelect.value = "__other__";
                        offenseLevelCustom.value = sanction.offense_level || "";
                    }
                    syncOffenseLevelCustomField();

                    document.getElementById("sanctionModalLabel").textContent =
                        "Edit Disciplinary Sanction";
                    sanctionModal.show();
                })
                .catch(() => {
                    Swal.fire({
                        icon: "error",
                        title: "Unable to load sanction",
                        text: "The disciplinary sanction could not be loaded at the moment.",
                    });
                });
        }

        const deleteButton = event.target.closest(".delete-sanction-btn");
        if (!deleteButton) {
            return;
        }

        const sanctionId = deleteButton.dataset.id;
        Swal.fire({
            title: "Delete disciplinary sanction?",
            text: "This action cannot be undone.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Yes, delete it",
        }).then(async (result) => {
            if (!result.isConfirmed) {
                return;
            }

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
                    text: data.message ?? "Sanction deleted successfully.",
                });
                await loadSanctions();
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Unable to delete sanction",
                    text: error.message ?? "The sanction could not be deleted.",
                });
            }
        });
    });

    form.addEventListener("submit", async function (event) {
        event.preventDefault();
        if (!submitButton) {
            return;
        }

        submitButton.disabled = true;
        submitButton.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        const formData = new FormData(form);

        // If "Other / Custom" was chosen, use the custom input value as the
        // offense level before sending.
        if (offenseLevelSelect && offenseLevelSelect.value === "__other__") {
            formData.set("offense_level", offenseLevelCustom.value.trim());
        }

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

            bootstrap.Modal.getInstance(modalElement).hide();
            Swal.fire({
                icon: "success",
                title: "Success",
                text: data.message ?? "Sanction saved successfully.",
            });
            await loadSanctions();
        } catch (error) {
            Swal.fire({
                icon: "error",
                title: "Unable to save sanction",
                text: error.message ?? "The sanction could not be saved.",
            });
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-save me-2"></i>Save';
        }
    });

    async function loadSanctions() {
        const response = await fetch(window.ViolationRoutes.sanctionIndex, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        const html = await response.text();
        const container = document.getElementById("sanctionContainer");
        if (container) {
            container.innerHTML = html;
        }
    }

    document.addEventListener("input", function (event) {
        if (event.target.id !== "sanctionSearchInput") {
            return;
        }

        const keyword = event.target.value.trim().toLowerCase();
        const groups = document.querySelectorAll(".sanction-accordion-item");

        groups.forEach((group) => {
            const label = (group.dataset.violationTypeName || "").toLowerCase();
            const matched = keyword === "" || label.includes(keyword);
            group.classList.toggle("d-none", !matched);
        });
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById("recordViolationModal");
    const studentStep = document.getElementById("studentStep");
    const violationStep = document.getElementById("violationStep");
    const continueBtn = document.getElementById("continueStep");
    const backBtn = document.getElementById("backStep");
    const saveBtn = document.getElementById("saveViolation");
    const studentIndicator = document.getElementById("studentStepIndicator");
    const violationIndicator = document.getElementById(
        "violationStepIndicator",
    );
    const searchBox = document.getElementById("studentSearch");
    const resultBox = document.getElementById("studentResults");
    const categorySelect = document.getElementById("violationCategory");
    const violationTypeSelect = document.getElementById("violationType");

    if (
        !modalElement ||
        !studentStep ||
        !violationStep ||
        !continueBtn ||
        !backBtn ||
        !saveBtn ||
        !studentIndicator ||
        !violationIndicator ||
        !searchBox ||
        !resultBox ||
        !categorySelect ||
        !violationTypeSelect
    ) {
        return;
    }

    const showAlert = (icon, title, text) => {
        Swal.fire({ icon, title, text });
    };

    const setStep = (stepNumber) => {
        const isStudentStep = stepNumber === 1;

        studentStep.classList.toggle("d-none", !isStudentStep);
        violationStep.classList.toggle("d-none", isStudentStep);

        continueBtn.classList.toggle("d-none", !isStudentStep);
        saveBtn.classList.toggle("d-none", isStudentStep);
        backBtn.classList.toggle("d-none", isStudentStep);

        studentIndicator.classList.toggle("bg-danger", isStudentStep);
        studentIndicator.classList.toggle("bg-success", !isStudentStep);

        violationIndicator.classList.toggle("bg-secondary", isStudentStep);
        violationIndicator.classList.toggle("bg-danger", !isStudentStep);
    };

    const resetModal = () => {
        document.getElementById("violationForm").reset();
        document.getElementById("selectedStudentNumber").value = "";
        document.getElementById("previewCategory").textContent = "-";
        document.getElementById("previewViolationType").textContent = "-";
        document.getElementById("previewOffenseLevelDisplay").textContent = "-";
        document.getElementById("previewOffenseLevel").value = "";
        document.getElementById("previewSanction").textContent = "-";

        resultBox.innerHTML = "";
        violationTypeSelect.innerHTML =
            '<option value="">Select Category First</option>';
        violationTypeSelect.disabled = true;

        document.getElementById("selectedStudentInfo").classList.add("d-none");
        document.getElementById("noStudentSelected").classList.remove("d-none");

        continueBtn.disabled = true;
        setStep(1);
    };

    continueBtn.addEventListener("click", function () {
        if (!document.getElementById("selectedStudentNumber").value) {
            showAlert(
                "warning",
                "Select a student",
                "Choose a student before continuing.",
            );
            return;
        }

        setStep(2);
    });

    backBtn.addEventListener("click", function () {
        setStep(1);
    });

    const selectStudent = (student) => {
        document.getElementById("selectedStudentNumber").value =
            student.student_number;
        document.getElementById("studentNumber").value = student.student_number;
        document.getElementById("studentName").value =
            `${student.last_name}, ${student.first_name}`;
        document.getElementById("studentProgram").value =
            student.program?.program_name ?? "-";
        document.getElementById("studentYear").value =
            student.year?.year_name ?? "-";
        document.getElementById("studentSection").value =
            student.section?.section_name ?? "-";
        document.getElementById("studentStatus").value =
            student.student_status?.status_name ?? "-";

        document.getElementById("noStudentSelected").classList.add("d-none");
        document
            .getElementById("selectedStudentInfo")
            .classList.remove("d-none");

        continueBtn.disabled = false;
        resultBox.innerHTML = "";
        searchBox.value = `${student.student_number} - ${student.last_name}, ${student.first_name}`;
    };

    let searchTimer;
    searchBox.addEventListener("keyup", function () {
        const keyword = this.value.trim();

        clearTimeout(searchTimer);

        if (keyword.length < 2) {
            resultBox.innerHTML = "";
            return;
        }

        searchTimer = setTimeout(() => {
            fetch(
                `${window.ViolationRoutes.searchStudent}?search=${encodeURIComponent(keyword)}`,
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error("Unable to search students.");
                    }

                    return response.json();
                })
                .then((data) => {
                    resultBox.innerHTML = "";

                    if (!Array.isArray(data) || data.length === 0) {
                        resultBox.innerHTML =
                            '<div class="list-group-item text-muted">No matching students found.</div>';
                        return;
                    }

                    data.forEach((student) => {
                        const item = document.createElement("button");
                        item.type = "button";
                        item.className =
                            "list-group-item list-group-item-action";
                        item.innerHTML = `<strong>${student.student_number}</strong><br>${student.last_name}, ${student.first_name}`;
                        item.addEventListener("click", function () {
                            selectStudent(student);
                        });
                        resultBox.appendChild(item);
                    });
                })
                .catch(() => {
                    showAlert(
                        "error",
                        "Search failed",
                        "Unable to search students right now.",
                    );
                });
        }, 250);
    });

    categorySelect.addEventListener("change", function () {
        const categoryId = this.value;

        violationTypeSelect.innerHTML = '<option value="">Loading...</option>';
        violationTypeSelect.disabled = true;

        if (!categoryId) {
            violationTypeSelect.innerHTML =
                '<option value="">Select Violation Type</option>';
            return;
        }

        fetch(
            `${window.ViolationRoutes.violationTypes}?category_id=${categoryId}`,
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error("Unable to load violation types.");
                }

                return response.json();
            })
            .then((types) => {
                violationTypeSelect.innerHTML =
                    '<option value="">Select Violation Type</option>';

                types.forEach((type) => {
                    violationTypeSelect.innerHTML += `<option value="${type.violation_type_id}">${type.violation_type}</option>`;
                });

                violationTypeSelect.disabled = false;
            })
            .catch(() => {
                violationTypeSelect.innerHTML =
                    '<option value="">Unable to load</option>';
                showAlert(
                    "error",
                    "Load failed",
                    "Unable to load violation types.",
                );
            });
    });

    violationTypeSelect.addEventListener("change", function () {
        const studentNumber = document.getElementById(
            "selectedStudentNumber",
        ).value;
        const violationTypeId = this.value;

        if (!studentNumber || !violationTypeId) {
            return;
        }

        fetch(window.ViolationRoutes.previewViolation, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            body: JSON.stringify({
                student_number: studentNumber,
                violation_type_id: violationTypeId,
            }),
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message || "Unable to preview violation details.",
                    );
                }

                return data;
            })
            .then((data) => {
                document.getElementById("previewOffenseLevel").value =
                    data.offense_level ?? "-";
                document.getElementById("previewCategory").textContent =
                    data.category ?? "-";
                document.getElementById("previewViolationType").textContent =
                    data.violation_type ?? "-";
                document.getElementById(
                    "previewOffenseLevelDisplay",
                ).textContent = data.offense_level ?? "-";
                document.getElementById("previewSanction").textContent =
                    data.sanction ?? "-";
            })
            .catch((error) => {
                showAlert("error", "Preview failed", error.message);
            });
    });

    saveBtn.addEventListener("click", function () {
        const studentNumber = document.getElementById(
            "selectedStudentNumber",
        ).value;
        const violationTypeId = document.getElementById("violationType").value;
        const violationDate = document.getElementById("violationDate").value;
        const description = document.getElementById("description").value.trim();

        if (
            !studentNumber ||
            !violationTypeId ||
            !violationDate ||
            !description
        ) {
            showAlert(
                "warning",
                "Missing required fields",
                "Complete all required fields before saving.",
            );
            return;
        }

        fetch(window.ViolationRoutes.store, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]',
                ).content,
            },
            body: JSON.stringify({
                student_number: studentNumber,
                violation_type_id: violationTypeId,
                violation_date: violationDate,
                description,
            }),
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message || "Unable to save violation.",
                    );
                }

                const modal = bootstrap.Modal.getInstance(modalElement);
                modal.hide();

                await Swal.fire({
                    icon: "success",
                    title: "Violation recorded",
                    text: data.message || "Violation recorded successfully.",
                });

                loadManagementTable();
            })
            .catch((error) => {
                showAlert("error", "Save failed", error.message);
            });
    });

    modalElement.addEventListener("hidden.bs.modal", resetModal);

    function loadManagementTable() {
        fetch(window.location.href)
            .then((response) => response.text())
            .then((html) => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, "text/html");
                const newTable = doc.querySelector("#managementTableContainer");
                const currentTable = document.querySelector(
                    "#managementTableContainer",
                );

                if (newTable && currentTable) {
                    currentTable.innerHTML = newTable.innerHTML;
                } else {
                    window.location.reload();
                }
            })
            .catch(() => {
                window.location.reload();
            });
    }
});

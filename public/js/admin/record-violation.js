document.addEventListener("DOMContentLoaded", function () {
    const studentStep = document.getElementById("studentStep");
    const violationStep = document.getElementById("violationStep");

    const continueBtn = document.getElementById("continueStep");
    const backBtn = document.getElementById("backStep");
    const saveBtn = document.getElementById("saveViolation");

    const studentIndicator = document.getElementById("studentStepIndicator");
    const violationIndicator = document.getElementById(
        "violationStepIndicator",
    );

    continueBtn.addEventListener("click", function () {
        studentStep.classList.add("d-none");
        violationStep.classList.remove("d-none");

        continueBtn.classList.add("d-none");
        saveBtn.classList.remove("d-none");

        backBtn.classList.remove("d-none");

        studentIndicator.classList.remove("bg-danger");
        studentIndicator.classList.add("bg-success");

        violationIndicator.classList.remove("bg-secondary");
        violationIndicator.classList.add("bg-danger");
    });

    backBtn.addEventListener("click", function () {
        violationStep.classList.add("d-none");
        studentStep.classList.remove("d-none");

        saveBtn.classList.add("d-none");
        continueBtn.classList.remove("d-none");

        backBtn.classList.add("d-none");

        studentIndicator.classList.remove("bg-success");
        studentIndicator.classList.add("bg-danger");

        violationIndicator.classList.remove("bg-danger");
        violationIndicator.classList.add("bg-secondary");
    });

    const searchBox = document.getElementById("studentSearch");
    const resultBox = document.getElementById("studentResults");
    const categorySelect = document.getElementById("violationCategory");
    const violationTypeSelect = document.getElementById("violationType");

    searchBox.addEventListener("keyup", function () {
        let keyword = this.value.trim();

        if (keyword.length < 2) {
            resultBox.innerHTML = "";
            return;
        }

        fetch(
            window.ViolationRoutes.searchStudent +
                "?search=" +
                encodeURIComponent(keyword),
        )
            .then((response) => response.json())

            .then((data) => {
                resultBox.innerHTML = "";

                data.forEach((student) => {
                    let item = document.createElement("button");

                    item.type = "button";

                    item.className = "list-group-item list-group-item-action";

                    item.innerHTML = `
                    <strong>${student.student_number}</strong><br>
                    ${student.last_name}, ${student.first_name}
                `;

                    item.onclick = function () {
                        selectStudent(student);
                    };

                    resultBox.appendChild(item);
                });
            });
    });

    function selectStudent(student) {
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

        document.getElementById("continueStep").disabled = false;

        document.getElementById("studentResults").innerHTML = "";
    }
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
            window.ViolationRoutes.violationTypes +
                "?category_id=" +
                categoryId,
        )
            .then((response) => response.json())
            .then((types) => {
                violationTypeSelect.innerHTML =
                    '<option value="">Select Violation Type</option>';

                types.forEach((type) => {
                    violationTypeSelect.innerHTML += `
                <option value="${type.violation_type_id}">
                    ${type.violation_type}
                </option>
            `;
                });

                violationTypeSelect.disabled = false;
            })
            .catch((error) => {
                console.error(error);

                violationTypeSelect.innerHTML =
                    '<option value="">Unable to load</option>';
            });
    });
    const previewOffense = document.getElementById("previewOffenseLevel");
    const previewOffenseDisplay = document.getElementById(
        "previewOffenseLevelDisplay",
    );
    const previewSanction = document.getElementById("previewSanction");

    violationTypeSelect.addEventListener("change", function () {
        const studentNumber = document.getElementById(
            "selectedStudentNumber",
        ).value;

        if (!studentNumber || !this.value) return;

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
                violation_type_id: this.value,
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                console.log(data);

                previewOffense.value = data.offense_level ?? "-";

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
                console.error(error);
            });
    });
    saveBtn.addEventListener("click", function () {
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
                student_number: document.getElementById("selectedStudentNumber")
                    .value,

                violation_type_id:
                    document.getElementById("violationType").value,

                violation_date: document.getElementById("violationDate").value,

                description: document.getElementById("description").value,
            }),
        })
            .then(async (response) => {
                const data = await response.json();

                if (!response.ok) {
                    console.error(data);
                    alert(data.message || "Unable to save violation.");
                    return;
                }
                const modal = bootstrap.Modal.getInstance(
                    document.getElementById("recordViolationModal"),
                );

                modal.hide();

                loadManagementTable();
            })
            .catch(console.error);
    });
});
function loadManagementTable() {
    fetch(window.location.href)
        .then((response) => response.text())
        .then((html) => {
            const parser = new DOMParser();

            const doc = parser.parseFromString(html, "text/html");

            const newTable = doc.querySelector("#managementTableContainer");

            document.querySelector("#managementTableContainer").innerHTML =
                newTable.innerHTML;
        });
}

/*
|--------------------------------------------------------------------------
| Student Violation Module
|--------------------------------------------------------------------------
|
| Handles:
| - Student Search
| - Modal Navigation
| - Category & Violation Loading
| - Offense Preview
| - Form Reset
|
*/

document.addEventListener("DOMContentLoaded", function () {
    // ==========================================================
    // Modal
    // ==========================================================

    const violationModal = document.getElementById("addViolationModal");

    // ==========================================================
    // Step Wizard
    // ==========================================================

    const stepOne = document.getElementById("stepOne");

    const stepTwo = document.getElementById("stepTwo");

    const stepOneIndicator = document.getElementById("stepOneIndicator");

    const stepTwoIndicator = document.getElementById("stepTwoIndicator");

    const continueButton = document.getElementById("continueStep");

    const backButton = document.getElementById("backStep");

    const saveButton = document.getElementById("saveViolation");

    // ==========================================================
    // Student Search
    // ==========================================================

    const searchInput = document.getElementById("studentSearch");

    const searchResults = document.getElementById("studentResults");

    // ==========================================================
    // Student Information
    // ==========================================================

    const hiddenStudentNumber = document.getElementById(
        "selectedStudentNumber",
    );

    const studentNumber = document.getElementById("studentNumber");

    const studentName = document.getElementById("studentName");

    const studentCourse = document.getElementById("studentCourse");

    const studentYear = document.getElementById("studentYear");

    const studentSection = document.getElementById("studentSection");

    // ==========================================================
    // Violation Information
    // ==========================================================

    const category = document.getElementById("violationCategory");

    const violationType = document.getElementById("violationType");

    const description = document.getElementById("description");

    // ==========================================================
    // Preview
    // ==========================================================

    const previewOffense = document.getElementById("previewOffense");

    const previewSanction = document.getElementById("previewSanction");

    // ==========================================================
    // Variables
    // ==========================================================

    let selectedStudent = null;

    let debounceTimer = null;
    // ==========================================================
    // STEP 1 → STEP 2
    // ==========================================================

    continueButton.addEventListener("click", function () {
        stepOne.classList.add("d-none");

        stepTwo.classList.remove("d-none");

        continueButton.classList.add("d-none");

        backButton.classList.remove("d-none");

        saveButton.classList.remove("d-none");

        stepOneIndicator.classList.remove("active");

        stepTwoIndicator.classList.add("active");
    });

    // ==========================================================
    // STEP 2 → STEP 1
    // ==========================================================

    backButton.addEventListener("click", function () {
        stepTwo.classList.add("d-none");

        stepOne.classList.remove("d-none");

        continueButton.classList.remove("d-none");

        backButton.classList.add("d-none");

        saveButton.classList.add("d-none");

        stepTwoIndicator.classList.remove("active");

        stepOneIndicator.classList.add("active");
    });
    // ==========================================================
    // Student Search
    // ==========================================================

    searchInput.addEventListener("keyup", function () {
        clearTimeout(debounceTimer);

        const keyword = this.value.trim();

        if (keyword.length < 2) {
            searchResults.innerHTML = "";

            return;
        }

        debounceTimer = setTimeout(function () {
            fetch(
                "/admin/violations/search-student?search=" +
                    encodeURIComponent(keyword),
            )
                .then((response) => response.json())
                .then(showStudentResults);
        }, 300);
    });
    function showStudentResults(students) {
        searchResults.innerHTML = "";

        if (students.length === 0) {
            searchResults.innerHTML =
                '<div class="list-group-item text-muted">No students found.</div>';

            return;
        }

        students.forEach(function (student) {
            const item = document.createElement("button");

            item.type = "button";

            item.className = "list-group-item list-group-item-action";

            item.innerHTML =
                "<strong>" +
                student.student_number +
                "</strong><br>" +
                student.last_name +
                ", " +
                student.first_name +
                '<br><small class="text-muted">' +
                student.course.course_name +
                " • " +
                student.year.year +
                " • " +
                student.section.section_name +
                "</small>";

            item.addEventListener("click", function () {
                selectStudent(student);
            });

            searchResults.appendChild(item);
        });
    }
    function selectStudent(student) {
        selectedStudent = student;

        hiddenStudentNumber.value = student.student_number;

        studentNumber.value = student.student_number;

        studentName.value = student.last_name + ", " + student.first_name;

        studentCourse.value = student.course.course_name;

        studentYear.value = student.year.year;

        studentSection.value = student.section.section_name;

        continueButton.disabled = false;

        searchResults.innerHTML = "";

        searchInput.value = student.student_number;
    }
});
// ==========================================================
// Category Changed
// ==========================================================

category.addEventListener("change", function () {
    const categoryId = this.value;

    violationType.innerHTML = '<option value="">Loading...</option>';

    violationType.disabled = true;

    previewOffense.textContent = "—";

    previewSanction.textContent = "—";

    if (!categoryId) {
        violationType.innerHTML =
            '<option value="">Select Category First</option>';

        return;
    }

    fetch(window.ViolationRoutes.violationTypes + "/" + categoryId)
        .then((response) => response.json())
        .then(loadViolationTypes);
});
function loadViolationTypes(types) {
    violationType.innerHTML = "";

    const defaultOption = document.createElement("option");

    defaultOption.value = "";

    defaultOption.textContent = "Select Violation";

    violationType.appendChild(defaultOption);

    types.forEach(function (type) {
        const option = document.createElement("option");

        option.value = type.violation_type_id;

        option.textContent = type.violation_type;

        violationType.appendChild(option);
    });

    violationType.disabled = false;
}
// ==========================================================
// Preview Violation
// ==========================================================

violationType.addEventListener("change", function () {
    const violationId = this.value;

    previewOffense.textContent = "Loading...";

    previewSanction.textContent = "Loading...";

    if (!violationId) {
        previewOffense.textContent = "—";

        previewSanction.textContent = "—";

        return;
    }

    fetch(
        window.ViolationRoutes.previewViolation +
            "?student_number=" +
            encodeURIComponent(hiddenStudentNumber.value) +
            "&violation_type_id=" +
            encodeURIComponent(violationId),
    )
        .then((response) => response.json())

        .then(showPreview);
});
function showPreview(data) {
    previewOffense.textContent = data.offense_level ?? "—";

    previewSanction.textContent =
        data.sanction ?? "No disciplinary sanction found.";
}
// ==========================================================
// Reset Modal
// ==========================================================

violationModal.addEventListener(
    "hidden.bs.modal",

    function () {
        document.getElementById("violationForm").reset();

        selectedStudent = null;

        hiddenStudentNumber.value = "";

        searchResults.innerHTML = "";

        previewOffense.textContent = "—";

        previewSanction.textContent = "—";

        continueButton.disabled = true;

        violationType.innerHTML =
            '<option value="">Select Category First</option>';

        violationType.disabled = true;

        stepTwo.classList.add("d-none");

        stepOne.classList.remove("d-none");

        backButton.classList.add("d-none");

        saveButton.classList.add("d-none");

        continueButton.classList.remove("d-none");

        stepOneIndicator.classList.add("active");

        stepTwoIndicator.classList.remove("active");
    },
);

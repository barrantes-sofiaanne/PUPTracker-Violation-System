document.addEventListener("DOMContentLoaded", function () {
    document.addEventListener("click", function (e) {
        const button = e.target.closest(".viewStudent");

        if (!button) return;

        const studentNumber = button.dataset.student;

        const modal = new bootstrap.Modal(
            document.getElementById("studentHistoryModal"),
        );

        const content = document.getElementById("studentHistoryContent");

        content.innerHTML = `
        <div class="text-center py-5">

            <div class="spinner-border text-danger"></div>

            <p class="mt-3">Loading student record...</p>

        </div>
    `;

        modal.show();

        fetch(window.ViolationRoutes.studentHistory + studentNumber)
            .then((response) => response.text())
            .then((html) => {
                content.innerHTML = html;
            })
            .catch((error) => {
                console.error(error);

                content.innerHTML = `
            <div class="alert alert-danger">

                Unable to load student record.

            </div>
        `;
            });
    });
});

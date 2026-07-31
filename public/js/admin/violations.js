document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById("studentHistoryModal");
    const content = document.getElementById("studentHistoryContent");

    if (!modalElement || !content) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);

    const loadingMarkup = `
        <div class="text-center py-5">
            <div class="spinner-border text-danger"></div>
            <p class="mt-3">Loading student record...</p>
        </div>
    `;

    async function loadStudentHistory(url) {
        content.innerHTML = loadingMarkup;

        try {
            const response = await fetch(url, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                throw new Error("Unable to load student record.");
            }

            const html = await response.text();
            content.innerHTML = html;
        } catch (error) {
            console.error(error);

            content.innerHTML = `
                <div class="alert alert-danger">
                    Unable to load student record.
                </div>
            `;
        }
    }

    document.addEventListener("click", function (e) {
        const button = e.target.closest(".viewStudent");

        if (!button) return;

        e.preventDefault();

        const studentNumber = button.dataset.student;

        modal.show();

        loadStudentHistory(
            window.ViolationRoutes.studentHistory + studentNumber,
        );
    });

    content.addEventListener("click", function (e) {
        const paginationLink = e.target.closest(".pagination a");

        if (!paginationLink) return;

        e.preventDefault();
        loadStudentHistory(paginationLink.href);
    });
});

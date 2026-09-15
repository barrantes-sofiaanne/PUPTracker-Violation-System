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

    /**
     * Violation History → "View N Evidence Files".
     *
     * Renders the evidence list for one violation. The URLs come from the
     * server-rendered button and point at the existing authorized evidence
     * endpoint; clicking one re-checks permission server-side, so nothing here
     * grants access on its own.
     */
    const evidenceModalEl = document.getElementById("violationEvidenceModal");

    if (evidenceModalEl) {
        // The Admin layout keeps modals in the body; Bootstrap can mis-stack a
        // modal nested inside a filtered container, so mirror what the auth
        // pages do and hoist it. (No-op when it is already a body child.)
        if (evidenceModalEl.parentElement !== document.body) {
            document.body.appendChild(evidenceModalEl);
        }

        const evidenceModal = new bootstrap.Modal(evidenceModalEl);
        const evidenceList = document.getElementById("violationEvidenceList");
        const evidenceEmpty = document.getElementById("violationEvidenceEmpty");

        document.addEventListener("click", function (e) {
            const button = e.target.closest(".viewViolationEvidence");

            if (!button) return;

            e.preventDefault();

            let items = [];

            try {
                items = JSON.parse(button.dataset.evidence || "[]");
            } catch (error) {
                items = [];
            }

            if (evidenceList) {
                evidenceList.innerHTML = items
                    .map(function (item) {
                        const label = item.name || item.code || "Evidence file";
                        const code = item.code ? ` <span class="text-muted">(${item.code})</span>` : "";
                        const method = item.method === "camera" ? "Camera" : "Upload";

                        return `
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2"
                               href="${item.url}" target="_blank" rel="noopener">
                                <span class="text-truncate">
                                    <i class="bi bi-paperclip me-2"></i>${label}${code}
                                </span>
                                <span class="admin-badge admin-badge-gold">${method}</span>
                            </a>
                        `;
                    })
                    .join("");
            }

            if (evidenceEmpty) {
                evidenceEmpty.classList.toggle("d-none", items.length > 0);
            }

            evidenceModal.show();
        });
    }
});

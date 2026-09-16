document.addEventListener("DOMContentLoaded", function () {
    if (!document.getElementById("reportFilterForm")) {
        return;
    }

    initializeFilters();
    initializeCharts();
    initializeExportButtons();
    loadReports();
});

let monthlyChart;
let categoryChart;
let programChart;
let topViolationChart;
const filterForm = document.getElementById("reportFilterForm");
const reportTable = document.getElementById("reportTable");

function initializeFilters() {
    filterForm.addEventListener("submit", function (e) {
        e.preventDefault();
        loadReports();
    });

    filterForm.addEventListener("reset", function () {
        setTimeout(function () {
            loadReports();
        }, 100);
    });

    const searchStudent = document.getElementById("searchStudent");
    if (searchStudent) {
        let timer;
        searchStudent.addEventListener("keyup", function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                loadReports();
            }, 400);
        });
    }
}

function loadReports(page = 1) {
    const formData = new FormData(filterForm);
    formData.append("page", page);

    showLoading();

    fetch(window.ReportRoutes.filter, {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
            "X-Requested-With": "XMLHttpRequest",
        },
        body: formData,
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            updateSummaryCards(data.statistics);
            updateTable(data.records);
            updateCharts(data.charts);
            document.getElementById("recordCount").textContent =
                data.total + " Record(s)";
        })
        .catch(function () {
            showError();
        });
}

function updateSummaryCards(stats) {
    // The Minor Violations and Major Violations cards were removed from the
    // Violation Reports summary, so this reads the remaining cards defensively:
    // a missing card must never throw and abort the rest of the AJAX update
    // (table + charts). The values are still on the page when present.
    setText("totalViolations", stats.total);
    setText("repeatOffenders", stats.repeat_offenders);
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.textContent = value;
    }
}

function updateTable(html) {
    if (reportTable) {
        reportTable.innerHTML = html;
    }
}

function initializeCharts() {
    const monthlyCanvas = document.getElementById("monthlyTrendChart");
    const categoryCanvas = document.getElementById("categoryChart");
    const programCanvas = document.getElementById("programChart");
    const topViolationCanvas = document.getElementById("topViolationChart");

    if (
        !monthlyCanvas ||
        !categoryCanvas ||
        !programCanvas ||
        !topViolationCanvas
    ) {
        return;
    }

    monthlyChart = new Chart(monthlyCanvas, {
        type: "line",
        data: {
            labels: [],
            datasets: [
                {
                    label: "Violations",
                    data: [],
                    borderColor: "#800000",
                    backgroundColor: "rgba(128, 0, 0, 0.12)",
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                },
            ],
        },
        options: { responsive: true, maintainAspectRatio: false },
    });

    categoryChart = new Chart(categoryCanvas, {
        type: "doughnut",
        data: {
            labels: [],
            datasets: [
                {
                    data: [],
                    backgroundColor: [
                        "#800000",
                        "#a31515",
                        "#daa520",
                        "#f0c447",
                        "#5f0000",
                        "#c9981f",
                        "#7a0f1e",
                        "#e6c36a",
                    ],
                    borderColor: "#ffffff",
                    borderWidth: 2,
                },
            ],
        },
        options: { responsive: true },
    });

    topViolationChart = new Chart(topViolationCanvas, {
        type: "bar",
        data: {
            labels: [],
            datasets: [
                {
                    label: "Records",
                    data: [],
                    backgroundColor: "#800000",
                    borderColor: "#5f0000",
                    borderWidth: 1,
                },
            ],
        },
        options: { responsive: true, indexAxis: "y" },
    });
    programChart = new Chart(programCanvas, {
        type: "doughnut",
        data: {
            labels: [],
            datasets: [
                {
                    data: [],
                    backgroundColor: [
                        "#800000",
                        "#a31515",
                        "#daa520",
                        "#f0c447",
                        "#5f0000",
                        "#c9981f",
                        "#7a0f1e",
                        "#e6c36a",
                    ],
                    borderColor: "#ffffff",
                    borderWidth: 2,
                },
            ],
        },
        options: { responsive: true },
    });
}

function updateCharts(charts) {
    if (
        !monthlyChart ||
        !categoryChart ||
        !programChart ||
        !topViolationChart
    ) {
        return;
    }

    monthlyChart.data.labels = charts.monthly.labels;
    monthlyChart.data.datasets[0].data = charts.monthly.data;
    monthlyChart.update();

    categoryChart.data.labels = charts.categories.labels;
    categoryChart.data.datasets[0].data = charts.categories.data;
    categoryChart.update();

    programChart.data.labels = charts.programs.labels;
    programChart.data.datasets[0].data = charts.programs.data;
    programChart.update();

    topViolationChart.data.labels = charts.topViolations.labels;
    topViolationChart.data.datasets[0].data = charts.topViolations.data;
    topViolationChart.update();
}

function showLoading() {
    if (!reportTable) {
        return;
    }

    reportTable.innerHTML = `
        <tr>
            <td colspan="9" class="text-center py-5">
                <div class="spinner-border text-danger"></div>
                <p class="mt-3">Loading report...</p>
            </td>
        </tr>
    `;
}

function showError() {
    if (!reportTable) {
        return;
    }

    reportTable.innerHTML = `
        <tr>
            <td colspan="9" class="text-center text-danger py-5">Unable to load report.</td>
        </tr>
    `;
}

function initializeExportButtons() {
    const pdfButton = document.getElementById("exportPdf");

    if (pdfButton) {
        pdfButton.addEventListener("click", function () {
            const params = new URLSearchParams(new FormData(filterForm));
            window.open(
                window.ReportRoutes.pdf + "?" + params.toString(),
                "_blank",
            );
        });
    }
}

document.addEventListener("click", function (e) {
    const paginationLink = e.target.closest(".pagination a");
    if (!paginationLink) {
        return;
    }

    e.preventDefault();
    const url = new URL(paginationLink.href);
    loadReports(url.searchParams.get("page"));
});

document.addEventListener("DOMContentLoaded", function () {
    initializeFilters();
    initializeCharts();
    initializeExportButtons();
    initializeSearch();
});
let monthlyChart;
let categoryChart;
let topViolationChart;
const filterForm = document.getElementById("reportFilterForm");

const reportTable = document.getElementById("reportTable");
function initializeFilters() {
    filterForm.addEventListener("submit", function (e) {
        e.preventDefault();

        loadReports();
    });
}
function loadReports(page = 1) {
    const formData = new FormData(filterForm);

    formData.append("page", page);

    fetch(window.ReportRoutes.filter, {
        method: "POST",

        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },

        body: formData,
    })
        .then((response) => response.json())

        .then((data) => {
            updateSummaryCards(data.statistics);

            updateTable(data.records);

            updateCharts(data.charts);
        });
}
function updateSummaryCards(stats) {
    document.getElementById("totalViolations").textContent = stats.total;

    document.getElementById("minorViolations").textContent = stats.minor;

    document.getElementById("majorViolations").textContent = stats.major;

    document.getElementById("repeatOffenders").textContent =
        stats.repeat_offenders;
}
document.getElementById("recordCount").textContent = stats.total + " Record(s)";
filterForm.addEventListener("reset", function () {
    setTimeout(function () {
        loadReports();
    }, 100);
});
function initializeSearch() {
    let timer;

    document
        .getElementById("searchStudent")
        .addEventListener("keyup", function () {
            clearTimeout(timer);

            timer = setTimeout(function () {
                loadReports();
            }, 400);
        });
}
function initializeCharts() {
    monthlyChart = new Chart(document.getElementById("monthlyTrendChart"), {
        type: "line",
        data: {
            labels: [],
            datasets: [
                {
                    label: "Violations",
                    data: [],
                    borderWidth: 2,
                    tension: 0.3,
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
        },
    });

    categoryChart = new Chart(document.getElementById("categoryChart"), {
        type: "doughnut",
        data: {
            labels: [],
            datasets: [
                {
                    data: [],
                },
            ],
        },
        options: {
            responsive: true,
        },
    });

    topViolationChart = new Chart(
        document.getElementById("topViolationChart"),
        {
            type: "bar",
            data: {
                labels: [],
                datasets: [
                    {
                        label: "Records",
                        data: [],
                    },
                ],
            },
            options: {
                responsive: true,
                indexAxis: "y",
            },
        },
    );
}
function updateCharts(charts) {
    monthlyChart.data.labels = charts.monthly.labels;

    monthlyChart.data.datasets[0].data = charts.monthly.data;

    monthlyChart.update();

    categoryChart.data.labels = charts.categories.labels;

    categoryChart.data.datasets[0].data = charts.categories.data;

    categoryChart.update();

    topViolationChart.data.labels = charts.topViolations.labels;

    topViolationChart.data.datasets[0].data = charts.topViolations.data;

    topViolationChart.update();
}
function showLoading() {
    reportTable.innerHTML = `

        <tr>

            <td colspan="9" class="text-center py-5">

                <div
                    class="spinner-border text-danger">

                </div>

                <p class="mt-3">

                    Loading Report...

                </p>

            </td>

        </tr>

    `;
}
function showError() {
    reportTable.innerHTML = `

        <tr>

            <td colspan="9"
                class="text-center text-danger py-5">

                Unable to load report.

            </td>

        </tr>

    `;
}
function initializeExportButtons() {
    document
        .getElementById("printReport")
        .addEventListener("click", function () {
            window.print();
        });
}
document.getElementById("exportPdf").addEventListener("click", function () {
    const params = new URLSearchParams(new FormData(filterForm));

    window.open(
        window.ReportRoutes.pdf + "?" + params.toString(),

        "_blank",
    );
});
document.getElementById("exportExcel").addEventListener("click", function () {
    const params = new URLSearchParams(new FormData(filterForm));

    window.location = window.ReportRoutes.excel + "?" + params.toString();
});
document.addEventListener(
    "click",

    function (e) {
        if (e.target.closest(".pagination a")) {
            e.preventDefault();

            let url = new URL(e.target.closest("a").href);

            loadReports(url.searchParams.get("page"));
        }
    },
);

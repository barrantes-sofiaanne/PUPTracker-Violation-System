/* Admin dashboard — moved from inline <script> to keep Blade templates clean.
   PHP data is passed via data-* attributes (read below). */

document.addEventListener("DOMContentLoaded", function () {
    // Philippine Standard Time live clock
    var phTimeDisplay = document.getElementById("phTimeDisplay");

    function updatePhilippineTime() {
        if (!phTimeDisplay) {
            return;
        }

        var now = new Date();
        var formatted = new Intl.DateTimeFormat("en-PH", {
            timeZone: "Asia/Manila",
            month: "short",
            day: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: false,
        }).format(now);

        var span = phTimeDisplay.querySelector("span");
        if (span) {
            span.textContent = formatted + " PHT";
        }
    }

    updatePhilippineTime();
    setInterval(updatePhilippineTime, 1000);

    // Weekly violation trend chart
    var weeklyCanvas = document.getElementById("weeklyChart");
    if (weeklyCanvas) {
        var trendColors = [
            "#800000",
            "#a31515",
            "#daa520",
            "#f0c447",
            "#5f0000",
            "#c9981f",
            "#7a0f1e",
            "#e6c36a",
            "#8e1d29",
            "#b8860b",
        ];

        var initialLabels = [];
        var initialData = [];
        try {
            initialLabels = JSON.parse(weeklyCanvas.dataset.labels || "[]");
            initialData = JSON.parse(weeklyCanvas.dataset.values || "[]");
        } catch (e) {
            initialLabels = [];
            initialData = [];
        }

        var chartDataUrl = weeklyCanvas.dataset.chartDataUrl || "";
        var periodSelect = document.getElementById("chartPeriod");
        var statusSelect = document.getElementById("chartStatus");
        var trendMeta = document.getElementById("violationTrendMeta");

        window.weeklyViolationChart = new Chart(weeklyCanvas, {
            type: "pie",
            data: {
                labels: initialLabels,
                datasets: [
                    {
                        label: "Violations",
                        data: initialData,
                        backgroundColor: initialLabels.map(function (_, i) {
                            return trendColors[i % trendColors.length];
                        }),
                        borderColor: "#fffaf0",
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: "bottom",
                    },
                },
                scales: {},
            },
        });

        function loadViolationTrend() {
            if (!chartDataUrl) {
                return;
            }

            var params = new URLSearchParams({
                period: periodSelect ? periodSelect.value : "week",
                status: statusSelect ? statusSelect.value : "",
            });

            fetch(chartDataUrl + "?" + params.toString(), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (data) {
                    if (
                        !data ||
                        !Array.isArray(data.labels) ||
                        !Array.isArray(data.values)
                    ) {
                        return;
                    }

                    window.weeklyViolationChart.data.labels = data.labels;
                    window.weeklyViolationChart.data.datasets[0].data =
                        data.values;
                    window.weeklyViolationChart.data.datasets[0].backgroundColor =
                        data.values.map(function (_, i) {
                            return trendColors[i % trendColors.length];
                        });
                    window.weeklyViolationChart.data.datasets[0].borderColor =
                        "#fffaf0";
                    window.weeklyViolationChart.update();

                    if (trendMeta) {
                        trendMeta.textContent =
                            "Total: " + (data.total || 0) + " violation(s)";
                    }
                })
                .catch(function () {
                    /* keep last data on transient errors */
                });
        }

        // Re-render when the filters change.
        if (periodSelect) {
            periodSelect.addEventListener("change", loadViolationTrend);
        }
        if (statusSelect) {
            statusSelect.addEventListener("change", loadViolationTrend);
        }

        // Real-time refresh: poll for fresh dashboard data every 60s.
        if (chartDataUrl) {
            setInterval(loadViolationTrend, 60000);
        }
    }

    document.querySelectorAll("[data-progress-width]").forEach(function (bar) {
        var width = parseFloat(bar.getAttribute("data-progress-width"));
        if (!Number.isNaN(width)) {
            var safeWidth = Math.max(0, Math.min(100, width));
            bar.style.width = safeWidth + "%";
            bar.setAttribute("aria-valuenow", safeWidth.toString());
        }
    });
});

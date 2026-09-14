/* Admin dashboard — moved from inline <script> to keep Blade templates clean.
   PHP data is passed via data-* attributes (read below). */

document.addEventListener("DOMContentLoaded", function () {
    // The Philippine Standard Time clock is owned by the shared page-header
    // component (x-admin.page-header), which emits ONE updater per page. The
    // dashboard must not run a second one against the same #phTimeDisplay.

    // Daily violations bar chart (last 7 days, including today)
    var dailyCanvas = document.getElementById("dailyViolationChart");
    if (dailyCanvas) {
        var barColor = "#800000";

        var initialLabels = [];
        var initialData = [];
        try {
            initialLabels = JSON.parse(dailyCanvas.dataset.labels || "[]");
            initialData = JSON.parse(dailyCanvas.dataset.values || "[]");
        } catch (e) {
            initialLabels = [];
            initialData = [];
        }

        var chartDataUrl = dailyCanvas.dataset.chartDataUrl || "";
        var trendMeta = document.getElementById("dailyViolationChartMeta");

        window.dailyViolationChart = new Chart(dailyCanvas, {
            type: "bar",
            data: {
                labels: initialLabels,
                datasets: [
                    {
                        label: "Violations",
                        data: initialData,
                        backgroundColor: barColor,
                        hoverBackgroundColor: "#5f0000",
                        borderColor: "#5f0000",
                        borderWidth: 1,
                        borderRadius: 4,
                        maxBarThickness: 46,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return (
                                    (context.parsed.y || 0) + " violation(s)"
                                );
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: "Date",
                        },
                        grid: {
                            display: false,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        precision: 0,
                        title: {
                            display: true,
                            text: "Number of Violations",
                        },
                        ticks: {
                            precision: 0,
                            stepSize: 1,
                        },
                    },
                },
            },
        });

        function loadDailyViolations() {
            if (!chartDataUrl) {
                return;
            }

            fetch(chartDataUrl, {
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

                    window.dailyViolationChart.data.labels = data.labels;
                    window.dailyViolationChart.data.datasets[0].data =
                        data.values;
                    window.dailyViolationChart.update();

                    if (trendMeta) {
                        trendMeta.textContent =
                            "Showing the last " +
                            (data.labels.length || 0) +
                            " day(s). Total: " +
                            (data.total || 0) +
                            " violation(s).";
                    }
                })
                .catch(function () {
                    /* keep last data on transient errors */
                });
        }

        // Real-time refresh: poll for fresh dashboard data every 60s so the
        // chart updates as new violations are recorded.
        if (chartDataUrl) {
            setInterval(loadDailyViolations, 60000);
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

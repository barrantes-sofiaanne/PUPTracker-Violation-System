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

    // Violations by Category chart (distinct colors per category)
    var categoryCanvas = document.getElementById("categoryChart");
    if (categoryCanvas) {
        var categoryColors = [
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
            initialLabels = JSON.parse(categoryCanvas.dataset.labels || "[]");
            initialData = JSON.parse(categoryCanvas.dataset.values || "[]");
        } catch (e) {
            initialLabels = [];
            initialData = [];
        }

        var chartDataUrl = categoryCanvas.dataset.chartDataUrl || "";

        window.categoryChart = new Chart(categoryCanvas, {
            type: "bar",
            data: {
                labels: initialLabels,
                datasets: [
                    {
                        label: "Violations",
                        data: initialData,
                        backgroundColor: initialLabels.map(function (_, i) {
                            return categoryColors[i % categoryColors.length];
                        }),
                        borderColor: initialLabels.map(function (_, i) {
                            return categoryColors[i % categoryColors.length];
                        }),
                        borderWidth: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });

        // Real-time refresh: poll for fresh dashboard data every 60s.
        if (chartDataUrl) {
            setInterval(function () {
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
                        if (!data || !Array.isArray(data)) {
                            return;
                        }
                        window.categoryChart.data.labels = data.map(
                            function (d) {
                                return d.category_name;
                            },
                        );
                        window.categoryChart.data.datasets[0].data = data.map(
                            function (d) {
                                return d.violations_count;
                            },
                        );
                        window.categoryChart.data.datasets[0].backgroundColor =
                            data.map(function (_, i) {
                                return categoryColors[
                                    i % categoryColors.length
                                ];
                            });
                        window.categoryChart.update();
                    })
                    .catch(function () {
                        /* keep last data on transient errors */
                    });
            }, 60000);
        }
    }

    // Apply the legend dot colors from the data-color attribute
    // (set server-side so no Blade directives live in inline CSS).
    document
        .querySelectorAll(".category-legend-dot[data-color]")
        .forEach(function (dot) {
            dot.style.backgroundColor = dot.dataset.color;
            dot.style.width = "12px";
            dot.style.height = "12px";
            dot.style.borderRadius = "3px";
        });

    document.querySelectorAll("[data-progress-width]").forEach(function (bar) {
        var width = parseFloat(bar.getAttribute("data-progress-width"));
        if (!Number.isNaN(width)) {
            var safeWidth = Math.max(0, Math.min(100, width));
            bar.style.width = safeWidth + "%";
            bar.setAttribute("aria-valuenow", safeWidth.toString());
        }
    });
});

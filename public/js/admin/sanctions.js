document.addEventListener("DOMContentLoaded", function () {
    if (
        typeof window.bootstrap !== "undefined" &&
        typeof window.bootstrap.Modal === "function"
    ) {
        if (typeof window.__sanctionWorkflowInitialized === "undefined") {
            window.__sanctionWorkflowInitialized = true;
            const script = document.createElement("script");
            script.src = "/js/admin/sanction.js";
            document.body.appendChild(script);
        }
    }
});

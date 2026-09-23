document.addEventListener("DOMContentLoaded", function () {
    document
        .querySelectorAll("[data-puptracker-logout-form]")
        .forEach(function (form) {
            if (form.dataset.logoutHandlerAttached === "1") {
                return;
            }

            form.dataset.logoutHandlerAttached = "1";

            form.addEventListener("submit", async function (event) {
                if (form.dataset.logoutSubmitting === "1") {
                    event.preventDefault();
                    return;
                }

                event.preventDefault();
                form.dataset.logoutSubmitting = "1";

                const submitButton = form.querySelector(
                    'button[type="submit"]',
                );

                if (submitButton) {
                    submitButton.disabled = true;
                }

                try {
                    const response = await fetch("/csrf-token", {
                        method: "GET",
                        credentials: "same-origin",
                        cache: "no-store",
                        headers: {
                            Accept: "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                        },
                    });

                    if (!response.ok) {
                        throw new Error(
                            "Unable to refresh CSRF token. HTTP " +
                                response.status,
                        );
                    }

                    const data = await response.json();

                    if (!data.token) {
                        throw new Error("CSRF token was not returned.");
                    }

                    // Update this logout form's CSRF token.
                    let tokenInput = form.querySelector('input[name="_token"]');

                    if (!tokenInput) {
                        tokenInput = document.createElement("input");
                        tokenInput.type = "hidden";
                        tokenInput.name = "_token";
                        form.appendChild(tokenInput);
                    }

                    tokenInput.value = data.token;

                    // Also update the global CSRF meta token.
                    const meta = document.querySelector(
                        'meta[name="csrf-token"]',
                    );

                    if (meta) {
                        meta.setAttribute("content", data.token);
                    }

                    // Submit normally so Laravel receives a normal POST request.
                    HTMLFormElement.prototype.submit.call(form);
                } catch (error) {
                    console.error(
                        "PUPTracker logout CSRF refresh failed:",
                        error,
                    );

                    form.dataset.logoutSubmitting = "0";

                    if (submitButton) {
                        submitButton.disabled = false;
                    }

                    /*
                     * Do NOT submit the potentially stale token here.
                     * Reload the page so Laravel can render a fresh token.
                     */
                    window.location.reload();
                }
            });
        });
});

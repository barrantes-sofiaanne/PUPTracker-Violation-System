function initializePasswordResetPolicy() {
    const password = document.getElementById("newPassword");
    const confirmation = document.getElementById("passwordConfirmation");
    const submit = document.getElementById("resetPasswordSubmit");

    if (!password || !confirmation || !submit) {
        return;
    }

    const requirements = {
        length: function (value) {
            return value.length >= 12;
        },
        lowercase: function (value) {
            return /[a-z]/.test(value);
        },
        uppercase: function (value) {
            return /[A-Z]/.test(value);
        },
        number: function (value) {
            return /\d/.test(value);
        },
        symbol: function (value) {
            return /[^A-Za-z0-9]/.test(value);
        },
    };

    function updatePolicy() {
        const value = password.value;
        const policyMet = Object.entries(requirements).every(function ([name, passes]) {
            const item = document.querySelector('[data-policy="' + name + '"]');
            const met = passes(value);

            if (item) {
                item.classList.toggle("is-met", met);
                item.querySelector("i").className = met
                    ? "bi bi-check-circle-fill"
                    : "bi bi-circle";
            }

            return met;
        });

        submit.disabled = !policyMet || value !== confirmation.value;
    }

    password.addEventListener("input", updatePolicy);
    confirmation.addEventListener("input", updatePolicy);
    updatePolicy();
}

window.initializePasswordResetPolicy = initializePasswordResetPolicy;
document.addEventListener("DOMContentLoaded", initializePasswordResetPolicy);
document.addEventListener("auth:content-updated", initializePasswordResetPolicy);

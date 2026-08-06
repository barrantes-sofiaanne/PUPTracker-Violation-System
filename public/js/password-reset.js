document.addEventListener("DOMContentLoaded", function () {
    const password = document.getElementById("newPassword");
    const confirmation = document.getElementById("passwordConfirmation");
    const submit = document.getElementById("resetPasswordSubmit");

    if (!password || !confirmation || !submit) {
        return;
    }

    const requirements = {
        length: (value) => value.length >= 8,
        lowercase: (value) => /[a-z]/.test(value),
        uppercase: (value) => /[A-Z]/.test(value),
        number: (value) => /\d/.test(value),
        symbol: (value) => /[^A-Za-z0-9]/.test(value),
    };

    function updatePolicy() {
        const value = password.value;
        const policyMet = Object.entries(requirements).every(function ([name, passes]) {
            const item = document.querySelector('[data-policy="' + name + '"]');
            const met = passes(value);

            if (item) {
                item.classList.toggle("is-met", met);
                item.querySelector("i").className = met ? "bi bi-check-circle-fill" : "bi bi-circle";
            }

            return met;
        });

        submit.disabled = !policyMet || value !== confirmation.value;
    }

    password.addEventListener("input", updatePolicy);
    confirmation.addEventListener("input", updatePolicy);
    updatePolicy();
});

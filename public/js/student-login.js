document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    if (toggle && password) {
        toggle.addEventListener("click", function () {
            if (password.type === "password") {
                password.type = "text";

                toggle.innerHTML = '<i class="bi bi-eye-slash"></i>';
                toggle.setAttribute("aria-label", "Hide password");
            } else {
                password.type = "password";

                toggle.innerHTML = '<i class="bi bi-eye"></i>';
                toggle.setAttribute("aria-label", "Show password");
            }
        });
    }
});

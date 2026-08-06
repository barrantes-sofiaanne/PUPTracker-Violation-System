function setInitialFocus() {
    const firstInput = document.querySelector(
        '.auth-content-card form input:not([type="hidden"]):not([disabled]), .auth-content-card form textarea:not([disabled]), .auth-content-card form select:not([disabled])',
    );

    if (firstInput instanceof HTMLElement) {
        firstInput.focus();
    }
}

function injectLoginEnhancementStyles() {
    if (document.getElementById("login-enhancement-styles")) {
        return;
    }

    const style = document.createElement("style");
    style.id = "login-enhancement-styles";
    style.textContent = `
        body {
            background:
                linear-gradient(rgba(16, 7, 10, 0.74), rgba(16, 7, 10, 0.74)),
                url("/assets/images/PUPT-picture.jpg") center/cover fixed;
        }

        .auth-content-card .form-control::placeholder {
            color: #7f6f73;
        }

        .auth-content-card .form-control:focus-visible,
        .toggle-password:focus-visible,
        .login-btn:focus-visible {
            outline: 3px solid rgba(245, 198, 93, 0.82);
            outline-offset: 3px;
        }

        .toggle-password {
            right: 0.35rem;
            width: 46px;
            height: 46px;
        }

        .login-btn {
            min-height: 54px;
            gap: 0.55rem;
            font-size: 1rem;
            box-shadow: 0 14px 26px rgba(122, 23, 32, 0.28);
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 28px rgba(122, 23, 32, 0.32);
        }

        .login-btn-spinner {
            display: none;
        }

        .login-btn.is-loading .login-btn-spinner {
            display: inline-block;
        }

        .login-btn.is-loading {
            pointer-events: none;
        }

        .login-error {
            display: flex;
            gap: 0.6rem;
            align-items: flex-start;
            animation: login-error-shake 320ms ease;
        }

        .login-error i {
            margin-top: 0.12rem;
            color: #a7232f;
        }

        .auth-secondary-link {
            color: #7a1720 !important;
            text-decoration: underline;
            text-decoration-color: rgba(122, 23, 32, 0.35);
            text-underline-offset: 0.18em;
        }

        .auth-secondary-link:hover,
        .auth-secondary-link:focus-visible {
            text-decoration-color: currentColor;
        }

        .security-note {
            display: flex;
            gap: 0.45rem;
            align-items: flex-start;
            margin: 0.9rem 0 0;
            color: #70585d;
            font-size: 0.74rem;
            line-height: 1.5;
        }

        .security-note i {
            color: #7a1720;
            margin-top: 0.1rem;
        }

        .student-status-banner {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.85rem;
            align-items: flex-start;
            margin-bottom: 0;
            padding: 1rem 1rem 0.95rem;
            color: #23425a;
            background: linear-gradient(180deg, #eef7ff, #f8fbff);
            border: 1px solid #c5def4;
            border-radius: 1rem;
            box-shadow: 0 10px 24px rgba(35, 66, 90, 0.08);
        }

        .student-status-banner strong {
            display: block;
            margin-bottom: 0.25rem;
            color: #153651;
        }

        .student-status-icon {
            display: grid;
            width: 2.35rem;
            height: 2.35rem;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, #2f6e9d, #1f4d73);
            border-radius: 0.85rem;
        }

        .login-input-group:hover {
            border-color: rgba(122, 23, 32, 0.3);
        }

        @keyframes login-error-shake {
            0%,
            100% {
                transform: translateX(0);
            }
            25% {
                transform: translateX(-5px);
            }
            75% {
                transform: translateX(5px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-btn,
            .toggle-password,
            .login-error {
                animation: none !important;
                transition: none !important;
            }
        }
    `;

    document.head.append(style);
}

function enhancePasswordToggle() {
    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    if (!toggle || !password) {
        return;
    }

    toggle.addEventListener("click", function () {
        const showingPassword = password.type === "text";
        password.type = showingPassword ? "password" : "text";
        toggle.innerHTML = showingPassword
            ? '<i class="bi bi-eye"></i>'
            : '<i class="bi bi-eye-slash"></i>';
        toggle.setAttribute(
            "aria-label",
            showingPassword ? "Show password" : "Hide password",
        );
    });
}

function enhanceFieldHints() {
    document.querySelectorAll('input[type="email"]').forEach(function (input) {
        if (!input.getAttribute("placeholder")) {
            input.setAttribute("placeholder", "name@example.com");
        }
    });

    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (!input.getAttribute("placeholder")) {
            input.setAttribute("placeholder", "Enter your password");
        }
    });
}

function enhanceLoginErrors() {
    document.querySelectorAll(".login-error").forEach(function (errorBox) {
        if (errorBox.querySelector("i")) {
            return;
        }

        const icon = document.createElement("i");
        icon.className = "bi bi-exclamation-octagon-fill";
        icon.setAttribute("aria-hidden", "true");
        errorBox.prepend(icon);
    });
}

function enhanceStudentBanner() {
    const unavailableAlert = document.querySelector(
        ".portal-student .alert-warning",
    );

    if (
        !unavailableAlert ||
        unavailableAlert.classList.contains("student-status-banner")
    ) {
        return;
    }

    unavailableAlert.className = "student-status-banner";
    unavailableAlert.setAttribute("role", "status");
    unavailableAlert.setAttribute("aria-live", "polite");
    unavailableAlert.innerHTML =
        '<div class="student-status-icon"><i class="bi bi-info-circle-fill" aria-hidden="true"></i></div>' +
        '<div><strong>Student portal temporarily unavailable</strong>' +
        '<p class="mb-0">IDP sign-in for students is currently disabled while access is being updated. Please try again later.</p></div>';
}

function enhanceSupportLinks() {
    document.querySelectorAll(".footer-links a[href]").forEach(function (link) {
        if (link.textContent.includes("Forgot Password")) {
            link.classList.add("auth-secondary-link");
        }
    });
}

function ensureSecurityNote() {
    const card = document.querySelector(".auth-content-card");
    const button = document.querySelector(".auth-content-card .login-btn");
    const fallbackTarget = document.querySelector(
        ".auth-content-card .student-status-banner, .auth-content-card form",
    );

    if (!card || card.querySelector(".security-note")) {
        return;
    }

    const note = document.createElement("p");
    note.className = "security-note";
    note.innerHTML =
        '<i class="bi bi-lock-fill" aria-hidden="true"></i> Secure login protected by encrypted connections and two-factor authentication.';

    if (button) {
        button.insertAdjacentElement("afterend", note);
        return;
    }

    if (fallbackTarget instanceof HTMLElement) {
        fallbackTarget.insertAdjacentElement("afterend", note);
    }
}

function enhanceLoginButtons() {
    document.querySelectorAll(".auth-content-card form").forEach(function (form) {
        const button = form.querySelector(".login-btn");

        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        if (!button.querySelector(".login-btn-label")) {
            const label = document.createElement("span");
            label.className = "login-btn-label";
            label.textContent = button.textContent.trim() || "Login";
            button.textContent = "";
            button.append(label);
        }

        if (!button.querySelector(".login-btn-spinner")) {
            const spinner = document.createElement("span");
            spinner.className = "spinner-border spinner-border-sm login-btn-spinner";
            spinner.setAttribute("aria-hidden", "true");
            button.append(spinner);
        }

        if (!button.dataset.loadingText) {
            button.dataset.loadingText = "Signing in...";
        }

        form.addEventListener("submit", function () {
            if (!form.checkValidity()) {
                return;
            }

            button.disabled = true;
            button.classList.add("is-loading");
            button.setAttribute("aria-busy", "true");

            const label = button.querySelector(".login-btn-label");

            if (label && button.dataset.loadingText) {
                label.textContent = button.dataset.loadingText;
            }
        });
    });
}

document.addEventListener("DOMContentLoaded", function () {
    injectLoginEnhancementStyles();
    setInitialFocus();
    enhanceFieldHints();
    enhancePasswordToggle();
    enhanceLoginErrors();
    enhanceStudentBanner();
    enhanceSupportLinks();
    ensureSecurityNote();
    enhanceLoginButtons();
});

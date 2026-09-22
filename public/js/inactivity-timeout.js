(() => {
    "use strict";

    /*
    |--------------------------------------------------------------------------
    | PUPTracker Inactivity Timeout
    |--------------------------------------------------------------------------
    |
    | Client-side warning only.
    |
    | The Laravel middleware remains the authoritative security control.
    |
    */

    const modal = document.getElementById("puptrackerInactivityModal");

    if (!modal) {
        return;
    }

    /* ----------------------------------------------------------------------
     * Elements
     * ------------------------------------------------------------------ */

    const countdownText = document.getElementById("puptrackerCountdown");

    const countdownNumber = document.getElementById(
        "puptrackerCountdownNumber",
    );

    const countdownProgress = document.getElementById(
        "puptrackerCountdownProgress",
    );

    const stayButton = document.getElementById("puptrackerStayButton");

    /* ----------------------------------------------------------------------
     * Configuration
     * ------------------------------------------------------------------ */

    const TIMEOUT_MINUTES = Number(
        document.body.dataset.inactivityTimeoutMinutes || 15,
    );

    const WARNING_SECONDS = Number(
        document.body.dataset.inactivityWarningSeconds || 10,
    );

    const ACTIVITY_URL = document.body.dataset.sessionActivityUrl;

    const CSRF_TOKEN = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    /* ----------------------------------------------------------------------
     * Constants
     * ------------------------------------------------------------------ */

    const TIMEOUT_MS = TIMEOUT_MINUTES * 60 * 1000;

    const WARNING_MS = WARNING_SECONDS * 1000;

    const CIRCUMFERENCE = 2 * Math.PI * 52;

    /* ----------------------------------------------------------------------
     * State
     * ------------------------------------------------------------------ */

    let lastActivityAt = Date.now();

    let warningInterval = null;

    let countdownInterval = null;

    let modalOpen = false;

    let activityRequestInProgress = false;

    let lastActivitySentAt = Date.now();

    /* ----------------------------------------------------------------------
     * Modal
     * ------------------------------------------------------------------ */

    function showTimeoutWarning() {
        if (modalOpen) {
            return;
        }

        modalOpen = true;

        let remaining = WARNING_SECONDS;

        countdownText.textContent = remaining;

        countdownNumber.textContent = remaining;

        countdownProgress.style.strokeDasharray = CIRCUMFERENCE;

        countdownProgress.style.strokeDashoffset = 0;

        modal.hidden = false;

        modal.setAttribute("aria-hidden", "false");

        stayButton.disabled = false;

        /*
         * Move keyboard focus directly to the
         * only action available to the user.
         */
        stayButton.focus();

        countdownInterval = window.setInterval(() => {
            remaining -= 1;

            const current = Math.max(remaining, 0);

            countdownText.textContent = current;

            countdownNumber.textContent = current;

            const percentage = current / WARNING_SECONDS;

            countdownProgress.style.strokeDashoffset =
                CIRCUMFERENCE * (1 - percentage);

            if (remaining <= 0) {
                clearInterval(countdownInterval);

                countdownInterval = null;

                expireSession();
            }
        }, 1000);
    }

    function hideTimeoutWarning() {
        modalOpen = false;

        if (countdownInterval !== null) {
            clearInterval(countdownInterval);

            countdownInterval = null;
        }

        modal.hidden = true;

        modal.setAttribute("aria-hidden", "true");
    }

    /* ----------------------------------------------------------------------
     * Server activity
     * ------------------------------------------------------------------ */

    async function sendActivity() {
        if (activityRequestInProgress || modalOpen || !ACTIVITY_URL) {
            return;
        }

        activityRequestInProgress = true;

        try {
            const response = await fetch(ACTIVITY_URL, {
                method: "POST",

                credentials: "same-origin",

                headers: {
                    "Content-Type": "application/json",

                    Accept: "application/json",

                    "X-CSRF-TOKEN": CSRF_TOKEN,
                },

                body: JSON.stringify({
                    source: "user_activity",
                }),
            });

            /*
             * Laravel session has already expired.
             */
            if (response.status === 401 || response.status === 440) {
                window.location.reload();

                return;
            }

            if (response.ok) {
                lastActivityAt = Date.now();

                lastActivitySentAt = Date.now();
            }
        } catch (error) {
            /*
             * Do not interrupt the user.
             *
             * The server-side middleware remains
             * authoritative.
             */
        } finally {
            activityRequestInProgress = false;
        }
    }

    /* ----------------------------------------------------------------------
     * "I'm still here"
     * ------------------------------------------------------------------ */

    async function stayActive() {
        stayButton.disabled = true;

        try {
            const response = await fetch(ACTIVITY_URL, {
                method: "POST",

                credentials: "same-origin",

                headers: {
                    "Content-Type": "application/json",

                    Accept: "application/json",

                    "X-CSRF-TOKEN": CSRF_TOKEN,
                },

                body: JSON.stringify({
                    source: "stay_active_button",
                }),
            });

            /*
             * Session already expired.
             */
            if (response.status === 401 || response.status === 440) {
                window.location.reload();

                return;
            }

            if (!response.ok) {
                throw new Error("Unable to extend session.");
            }

            /*
             * Server successfully refreshed
             * the inactivity window.
             */
            lastActivityAt = Date.now();

            lastActivitySentAt = Date.now();

            hideTimeoutWarning();
        } catch (error) {
            /*
             * If the server cannot confirm
             * the session, reload and allow
             * Laravel to determine the state.
             */
            window.location.reload();
        } finally {
            stayButton.disabled = false;
        }
    }

    /* ----------------------------------------------------------------------
     * Expiration
     * ------------------------------------------------------------------ */

    function expireSession() {
        /*
         * The browser reloads the current
         * authenticated page.
         *
         * Laravel's EnforceInactivityTimeout
         * middleware detects that the session
         * has reached the timeout and performs
         * the actual logout/redirect.
         */
        window.location.reload();
    }

    /* ----------------------------------------------------------------------
     * User activity
     * ------------------------------------------------------------------ */

    function userInteracted() {
        if (modalOpen) {
            return;
        }

        const now = Date.now();

        /*
         * Any genuine interaction means the user is present.
         */
        lastActivityAt = now;

        /*
         * Synchronize the server activity timestamp
         * at most once every 30 seconds.
         */
        if (now - lastActivitySentAt >= 30000) {
            lastActivitySentAt = now;

            sendActivity();
        }
    }

    /* ----------------------------------------------------------------------
     * Events considered user activity
     * ------------------------------------------------------------------ */

    window.addEventListener("pointerdown", userInteracted, {
        passive: true,
        capture: true,
    });

    window.addEventListener("keydown", userInteracted, {
        passive: true,
        capture: true,
    });

    window.addEventListener("touchstart", userInteracted, {
        passive: true,
        capture: true,
    });

    window.addEventListener("scroll", userInteracted, {
        passive: true,
        capture: true,
    });

    /* ----------------------------------------------------------------------
     * Stay button
     * ------------------------------------------------------------------ */

    stayButton.addEventListener("click", stayActive);

    /* ----------------------------------------------------------------------
     * Warning monitor
     * ------------------------------------------------------------------ */

    warningInterval = window.setInterval(() => {
        console.log("[PUPTracker Warning Timer]", {
            inactiveFor: Math.round((Date.now() - lastActivityAt) / 1000),
            modalOpen: modalOpen,
        });

        if (modalOpen) {
            return;
        }

        const now = Date.now();

        const inactiveFor = now - lastActivityAt;

        const warningAt = TIMEOUT_MS - WARNING_MS;

        /*
         * Show the warning shortly before the
         * server-side inactivity limit.
         */
        if (inactiveFor >= warningAt) {
            console.log("[PUPTracker Inactivity]", {
                inactiveFor: Math.round(inactiveFor / 1000),
                warningAt: Math.round(warningAt / 1000),
                timeout: TIMEOUT_MINUTES,
                modalOpen,
            });
            showTimeoutWarning();
        }
    }, 1000);
})();

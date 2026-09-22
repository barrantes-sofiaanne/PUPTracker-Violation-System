<div
    id="puptrackerInactivityModal"
    class="puptracker-inactivity-overlay"
    hidden
    aria-hidden="true"
>
    <div
        class="puptracker-inactivity-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="puptrackerInactivityTitle"
    >

        {{-- PUPTracker system logo --}}
        <div class="puptracker-inactivity-logo">
            <img
                src="{{ asset('assets/images/System-logo.png') }}"
                alt="PUPTracker Violation System"
            >
        </div>

        {{-- Timeout icon --}}
        <div class="puptracker-inactivity-icon">
            <i class="bi bi-clock-history"></i>
        </div>

        {{-- Title --}}
        <h2 id="puptrackerInactivityTitle">
            Are you still there?
        </h2>

        {{-- Explanation --}}
        <p class="puptracker-inactivity-message">
            You have been inactive for a while.
            For your security, your session will expire in
            <strong>
                <span id="puptrackerCountdown">10</span> seconds.
            </strong>
        </p>

        {{-- Circular countdown --}}
        <div
            class="puptracker-countdown"
            aria-live="assertive"
            aria-atomic="true"
        >

            <svg
                class="puptracker-countdown-ring"
                viewBox="0 0 120 120"
                aria-hidden="true"
            >
                <circle
                    class="puptracker-countdown-track"
                    cx="60"
                    cy="60"
                    r="52"
                ></circle>

                <circle
                    id="puptrackerCountdownProgress"
                    class="puptracker-countdown-progress"
                    cx="60"
                    cy="60"
                    r="52"
                ></circle>
            </svg>

            <span id="puptrackerCountdownNumber">
                10
            </span>

        </div>

        {{-- Stay logged in button --}}
        <button
            type="button"
            id="puptrackerStayButton"
            class="puptracker-stay-button"
        >
            <i class="bi bi-check-circle me-1"></i>
            I'm still here
        </button>

        {{-- Security explanation --}}
        <div class="puptracker-security-note">

            <i class="bi bi-shield-lock-fill"></i>

            <span>
                If you do not respond, you will be automatically
                logged out to protect your account and sensitive records.
            </span>

        </div>

    </div>
</div>  
{{--
    Privacy / Terms & Conditions consent modal.

    Shown FIRST after login (before any login announcement modal). The user
    must agree to the privacy terms & conditions to continue using the system.
    - I Agree   → closes this modal, marks consent for the browser session
                  (sessionStorage), then the login announcement modal (if any) shows.
    - I Disagree → automatically logs the user out of the system.

    Only renders when the session flag `show_privacy_consent_modal` is set
    (set on every successful login). Re-show is suppressed client-side via
    sessionStorage so no AJAX/session write races with logout (prevents 419).
--}}
@if(session()->get('show_privacy_consent_modal', false))
    @php
        $consentLogoutUrl = $consentLogoutUrl ?? match (true) {
            auth('admin')->check() => route('admin.logout'),
            auth('security')->check() => route('security.logout'),
            default => route('logout'),
        };
    @endphp

    <div
        class="modal fade"
        id="privacyConsentModal"
        tabindex="-1"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        aria-labelledby="privacyConsentModalLabel"
        aria-hidden="true"
        role="dialog"
        aria-modal="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="privacyConsentModalLabel">
                        <i class="bi bi-shield-lock me-2"></i>
                        Privacy &amp; Terms of Use
                    </h5>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    <p class="text-muted">
                        Before you continue, please review the summary of our privacy practices and the
                        terms &amp; conditions governing your use of the PUPTracker Violation System.
                    </p>

                    <h6 class="fw-semibold mt-3"><i class="bi bi-eye me-1"></i> Privacy</h6>
                    <p class="text-muted small mb-2">
                        The system collects and processes your personal and academic information
                        (e.g. name, student/employee number, email, disciplinary records) strictly for
                        official university purposes — tracking violations, managing sanctions, generating
                        reports, and issuing announcements. Your data is kept confidential, access is
                        restricted to authorized personnel, and it is never sold or shared beyond what is
                        required by university policy and applicable law.
                    </p>

                    <h6 class="fw-semibold mt-3"><i class="bi bi-file-earmark-text me-1"></i> Terms &amp; Conditions</h6>
                    <p class="text-muted small mb-2">
                        By using this system you agree to provide accurate information, use the system only
                        for its intended academic and administrative purpose, keep your account credentials
                        secure, and not attempt to access, alter, or misuse records you are not authorized to
                        view. All activities are monitored and logged for security and accountability.
                        Misuse may result in disciplinary or administrative action.
                    </p>

                    <p class="text-muted small mb-0">
                        By clicking <strong>I Agree</strong>, you acknowledge that you have read and understood
                        the summary above. If you do not agree, click <strong>I Disagree</strong> and you will
                        be logged out of the system.
                    </p>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="admin-btn admin-btn-danger" id="privacyConsentDisagree">
                        <i class="bi bi-x-circle me-1"></i> I Disagree
                    </button>
                    <button type="button" class="admin-btn admin-btn-success" id="privacyConsentAgree">
                        <i class="bi bi-check-circle me-1"></i> I Agree
                    </button>
                </div>
            </div>
        </div>
    </div>

    <form id="privacyConsentLogoutForm" method="POST" action="{{ $consentLogoutUrl }}" class="d-none">
        @csrf
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('privacyConsentModal');
            const agreeBtn = document.getElementById('privacyConsentAgree');
            const disagreeBtn = document.getElementById('privacyConsentDisagree');
            const logoutForm = document.getElementById('privacyConsentLogoutForm');

            if (!modalElement || !agreeBtn || !disagreeBtn) {
                return;
            }

            // Show the consent modal only if it has NOT been acknowledged in
            // this browser session (sessionStorage). This avoids any AJAX
            // session write that could race with the logout POST (419).
            const CONSENT_KEY = 'puptracker_privacy_consent_acknowledged';
            if (sessionStorage.getItem(CONSENT_KEY)) {
                // Already acknowledged this session — let the announcement modal show directly.
                document.dispatchEvent(new CustomEvent('privacy:consent-agreed'));
                return;
            }

            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false,
            });

            // Show the consent modal FIRST — before any login announcement modal.
            modal.show();

            agreeBtn.addEventListener('click', function () {
                modal.hide();
                // Mark acknowledged for this browser session (client-side only,
                // no server request — removes the AJAX/session race entirely).
                try {
                    sessionStorage.setItem(CONSENT_KEY, '1');
                } catch (e) { /* ignore storage errors */ }
                // Notify the login announcement modal (if any) that consent was
                // granted so it can now show.
                document.dispatchEvent(new CustomEvent('privacy:consent-agreed'));
            });

            disagreeBtn.addEventListener('click', function () {
                if (logoutForm) {
                    logoutForm.submit();
                }
            });
        });
    </script>
@endif

{{--
    PUPTracker Privacy / Terms & Conditions Modal

    IMPORTANT:
    This modal must appear once for EVERY successful login.

    The server sets `show_privacy_consent_modal` during successful
    authentication.

    The browser acknowledgment is tied to the CURRENT Laravel
    session. Therefore:

        Login A
        -> acknowledge
        -> logout
        -> Login B
        -> modal appears again

    This is different from using one permanent sessionStorage key,
    which could survive logout/login in the same browser tab.
--}}

@if(session()->get('show_privacy_consent_modal', false))
    @php
        $consentLogoutUrl = $consentLogoutUrl ?? match (true) {
            auth('admin')->check() => route('admin.logout'),
            auth('security')->check() => route('security.logout'),
            default => route('logout'),
        };

        /*
         * Generate a browser-side identifier for THIS authenticated
         * Laravel session.

         * We do NOT expose the actual Laravel session ID.
         * Instead, only a SHA-256 hash is placed in the JavaScript.
         */
        $privacySessionKey = hash(
            'sha256',
            'puptracker-privacy-' . session()->getId()
        );
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

                {{-- HEADER --}}
                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="privacyConsentModalLabel">

                        <i class="bi bi-shield-lock me-2"></i>

                        Privacy &amp; Terms of Use

                    </h5>

                </div>


                {{-- BODY --}}
                <div
                    class="modal-body"
                    style="max-height: 65vh; overflow-y: auto;">

                    {{-- INTRODUCTION --}}
                    <div class="alert alert-light border mb-3">

                        <div class="d-flex align-items-start">

                            <i class="bi bi-info-circle me-2 mt-1"></i>

                            <div>

                                <strong>
                                    Please review before continuing.
                                </strong>

                                <p class="text-muted small mb-0 mt-1">

                                    PUPTracker operates in accordance with
                                    the <strong>PUP Privacy Statement</strong>
                                    and <strong>PUP Terms of Use</strong>.
                                    The information below summarizes how
                                    these policies apply to your use of
                                    the PUPTracker Violation System.

                                </p>

                            </div>

                        </div>

                    </div>


                    {{-- PRIVACY --}}
                    <h6 class="fw-semibold mt-3">

                        <i class="bi bi-eye me-1"></i>

                        Privacy

                    </h6>

                    <p class="text-muted small mb-2">

                        PUPTracker processes personal information in
                        accordance with the PUP Privacy Statement and
                        applicable data privacy laws and regulations.

                        Information processed through the system may
                        include student and account information,
                        violation and sanction records, submitted
                        evidence, photographs, and necessary device
                        or system information.

                    </p>

                    <p class="text-muted small mb-2">

                        This information may be processed for legitimate
                        university purposes, including the operation
                        and security of the system, documentation and
                        management of reported student violations,
                        authorized review of incidents, maintenance
                        of records, notifications, and related
                        university processes.

                    </p>

                    <p class="text-muted small mb-2">

                        Access to personal information, violation
                        records, and evidence is restricted to
                        authorized personnel according to their
                        assigned roles and official functions.

                    </p>


                    {{-- CAMERA AND EVIDENCE --}}
                    <div class="mt-4">

                        <h6 class="fw-semibold">

                            <i class="bi bi-camera me-1"></i>

                            Camera &amp; Evidence

                        </h6>

                        <p class="text-muted small mb-2">

                            Authorized personnel may use the PUPTracker
                            camera and evidence-upload features to
                            document a specific reported violation.

                            Captured photographs and other evidence
                            are associated with the relevant violation
                            record and are protected through access
                            controls and other security measures.

                        </p>

                        <p class="text-muted small mb-2">

                            The camera feature is intended for
                            documentation of specific incidents and
                            is <strong>not intended for continuous
                            surveillance, facial recognition, or
                            automated identification</strong>.

                        </p>

                        <p class="text-muted small mb-2">

                            Where applicable, privacy-protection
                            measures such as <strong>face
                            blurring</strong> may be applied to
                            captured images to reduce unnecessary
                            exposure of individuals who are not
                            necessary subjects of the evidence.

                        </p>

                    </div>


                    {{-- SECURITY --}}
                    <div class="mt-4">

                        <h6 class="fw-semibold">

                            <i class="bi bi-shield-check me-1"></i>

                            Protection of Information

                        </h6>

                        <p class="text-muted small mb-2">

                            PUPTracker applies access controls and
                            other technical and organizational
                            safeguards to protect information processed
                            through the system.

                        </p>

                        <p class="text-muted small mb-2">

                            Violation evidence is intended to be
                            accessible only to authorized personnel
                            performing their official functions.

                        </p>

                        <p class="text-muted small mb-2">

                            System and evidence access may be recorded
                            for security, accountability, auditing,
                            and authorized administrative purposes.

                        </p>

                    </div>


                    {{-- TERMS --}}
                    <div class="mt-4">

                        <h6 class="fw-semibold">

                            <i class="bi bi-file-earmark-text me-1"></i>

                            Terms of Use

                        </h6>

                        <p class="text-muted small mb-2">

                            Use PUPTracker only for its intended
                            academic and administrative purposes.

                            Keep your account credentials secure and
                            do not access, disclose, modify, download,
                            or otherwise use records or information
                            beyond what you are authorized to access.

                        </p>

                        <p class="text-muted small mb-2">

                            Users are responsible for using the system
                            in accordance with the PUP Terms of Use,
                            applicable University policies, and their
                            assigned responsibilities.

                        </p>

                    </div>


                    {{-- STUDENT RIGHTS --}}
                    <div class="mt-4">

                        <h6 class="fw-semibold">

                            <i class="bi bi-person-check me-1"></i>

                            Your Privacy Rights

                        </h6>

                        <p class="text-muted small mb-2">

                            As a data subject, you have applicable
                            rights under the Data Privacy Act of 2012
                            and the PUP Privacy Statement, including
                            the right to be informed about the
                            processing of your personal information,
                            the right to access your personal
                            information, the right to request
                            correction of inaccurate information,
                            and other rights applicable under law
                            and University policy.

                        </p>

                        <p class="text-muted small mb-2">

                            Privacy concerns or requests regarding
                            information processed through PUPTracker
                            may be raised through the appropriate
                            University privacy channels.

                        </p>

                    </div>


                    {{-- FULL PUP DOCUMENTS --}}
                    <div class="mt-4">

                        <h6 class="fw-semibold">

                            <i class="bi bi-journal-text me-1"></i>

                            Full PUP Documents

                        </h6>

                        <p class="text-muted small mb-2">

                            The following official PUP documents
                            contain the complete privacy and terms
                            information applicable to your use of
                            PUPTracker:

                        </p>

                        <ul class="mb-2">

                            <li class="mb-1">

                                <a
                                    href="https://www.pup.edu.ph/privacy/"
                                    target="_blank"
                                    rel="noopener noreferrer">

                                    PUP Privacy Statement

                                    <i class="bi bi-box-arrow-up-right ms-1"></i>

                                </a>

                            </li>

                            <li>

                                <a
                                    href="https://www.pup.edu.ph/terms/"
                                    target="_blank"
                                    rel="noopener noreferrer">

                                    PUP Terms of Use

                                    <i class="bi bi-box-arrow-up-right ms-1"></i>

                                </a>

                            </li>

                        </ul>

                    </div>


                    {{-- PRIVACY CONTACT --}}
                    <div class="mt-4">

                        <h6 class="fw-semibold">

                            <i class="bi bi-envelope me-1"></i>

                            Privacy Concerns

                        </h6>

                        <p class="text-muted small mb-2">

                            For privacy-related questions or concerns,
                            you may contact the PUP Data Protection
                            Officer through:

                        </p>

                        <p class="small mb-2">

                            <a href="mailto:dataprivacy@pup.edu.ph">

                                dataprivacy@pup.edu.ph

                            </a>

                        </p>

                    </div>


                    {{-- ACKNOWLEDGMENT --}}
                    <div class="border-top pt-3 mt-4">

                        <p class="text-muted small mb-0">

                            By selecting
                            <strong>I Acknowledge &amp; Continue</strong>,
                            you acknowledge that you have been provided
                            access to and have reviewed the PUP Privacy
                            Statement and PUP Terms of Use applicable
                            to your use of PUPTracker.

                        </p>

                        <p class="text-muted small mt-2 mb-0">

                            If you do not wish to proceed, select
                            <strong>I Disagree / Log Out</strong>.

                        </p>

                    </div>

                </div>


                {{-- FOOTER --}}
                <div class="modal-footer justify-content-between">

                    <button
                        type="button"
                        class="admin-btn admin-btn-danger"
                        id="privacyConsentDisagree">

                        <i class="bi bi-x-circle me-1"></i>

                        I Disagree / Log Out

                    </button>


                    <button
                        type="button"
                        class="admin-btn admin-btn-success"
                        id="privacyConsentAgree">

                        <i class="bi bi-check-circle me-1"></i>

                        I Acknowledge &amp; Continue

                    </button>

                </div>

            </div>

        </div>

    </div>


    {{-- LOGOUT FORM --}}
    <form
        id="privacyConsentLogoutForm"
        method="POST"
        action="{{ $consentLogoutUrl }}"
        class="d-none"
        data-puptracker-logout-form>

        @csrf

    </form>


    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const modalElement =
                document.getElementById('privacyConsentModal');

            const agreeBtn =
                document.getElementById('privacyConsentAgree');

            const disagreeBtn =
                document.getElementById('privacyConsentDisagree');

            const logoutForm =
                document.getElementById('privacyConsentLogoutForm');


            if (
                !modalElement ||
                !agreeBtn ||
                !disagreeBtn
            ) {
                return;
            }


            /*
             * IMPORTANT:
             *
             * The key contains an identifier unique to the current
             * Laravel authenticated session.
             *
             * Therefore the acknowledgment from a previous login
             * cannot suppress the modal on the next login.
             */
const PRIVACY_SESSION_KEY =
    'puptracker_privacy_consent_{{ $privacySessionKey }}';


            /*
             * Only suppress the modal after the user has acknowledged
             * it during THIS authenticated session.
             */
            try {

                if (
                    sessionStorage.getItem(PRIVACY_SESSION_KEY) === '1'
                ) {

                    document.dispatchEvent(
                        new CustomEvent('privacy:consent-agreed')
                    );

                    return;
                }

            } catch (error) {

                /*
                 * If sessionStorage is unavailable, DO NOT suppress
                 * the privacy modal.
                 *
                 * This is intentional: failure of client-side
                 * storage should never allow the modal to be bypassed.
                 */

            }


            /*
             * Static backdrop prevents clicking outside the modal
             * to bypass it.
             */
            const modal = new bootstrap.Modal(modalElement, {

                backdrop: 'static',

                keyboard: false,

            });


            /*
             * SHOW MODAL
             */
            modal.show();


            /*
             * ACKNOWLEDGE
             */
            agreeBtn.addEventListener('click', function () {

                try {

                    sessionStorage.setItem(
                        PRIVACY_SESSION_KEY,
                        '1'
                    );

                } catch (error) {

                    /*
                     * If storage fails, do not create an alternative
                     * persistent consent state.
                     *
                     * The current page may continue, but the modal
                     * will appear again on the next authenticated page.
                     */

                }


                modal.hide();


                /*
                 * Tell the login announcement modal that the privacy
                 * acknowledgment has been completed.
                 */
                document.dispatchEvent(
                    new CustomEvent('privacy:consent-agreed')
                );

            });


            /*
             * DISAGREE / LOG OUT
             */
            disagreeBtn.addEventListener('click', function () {

                /*
                 * Remove any acknowledgment associated with this
                 * session before logging out.
                 */
                try {

                    sessionStorage.removeItem(
                        PRIVACY_SESSION_KEY
                    );

                } catch (error) {
                    // Ignore storage errors.
                }


                if (logoutForm) {

                    logoutForm.submit();

                }

            });

        });
    </script>
@endif
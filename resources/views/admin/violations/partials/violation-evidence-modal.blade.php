{{--
    Admin → Violations → Violation History → "View N Evidence Files" modal.

    Lists every evidence file attached to ONE violation. Each link points at the
    existing `admin.violations.evidence` route, which re-checks authorization
    server-side on the click — the URLs in the DOM are therefore not a bypass:
    guessing another violation's evidence id still fails that check.

    No raw storage path is ever emitted; only opaque evidence ids appear.

    Opened by `public/js/admin/violations.js` from a row button carrying
    `data-evidence` (a JSON array of {url, name, code}).
--}}

<div class="modal fade" id="violationEvidenceModal" tabindex="-1" aria-labelledby="violationEvidenceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 overflow-hidden">

            <div class="modal-header admin-modal-header text-white">
                <div>
                    <h5 class="modal-title fw-bold" id="violationEvidenceModalLabel">
                        <i class="bi bi-paperclip me-2"></i>Attached Evidence
                    </h5>
                    <small class="opacity-75 d-block mt-1">
                        Evidence files recorded for this violation.
                    </small>
                </div>

                {{-- `admin-modal-close` is what carries the 44px touch target and the
                     transparent-background white × on the maroon header. Without it
                     Bootstrap's default 16px glyph is too small to tap. --}}
                <button type="button" class="btn-close btn-close-white admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="list-group" id="violationEvidenceList"></div>

                <p class="text-muted small mb-0 mt-3" id="violationEvidenceEmpty">
                    No evidence files are attached to this violation.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>

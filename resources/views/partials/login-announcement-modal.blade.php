@if(!empty($loginAnnouncementModal))
    <div
        class="modal fade"
        id="loginAnnouncementModal"
        tabindex="-1"
        aria-labelledby="loginAnnouncementModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginAnnouncementModalLabel">
                        {{ $loginAnnouncementModal->title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-muted small">
                        Posted {{ optional($loginAnnouncementModal->created_at)->format('M d, Y h:i A') }}
                    </div>
                    <div>{!! nl2br(e($loginAnnouncementModal->content)) !!}</div>

                    @if($loginAnnouncementModal->attachment_path)
                        <hr>
                        <a
                            href="{{ asset($loginAnnouncementModal->attachment_path) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-outline-primary btn-sm">
                            View Attachment
                        </a>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Understood</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('loginAnnouncementModal');
            if (!modalElement) {
                return;
            }

            const modal = new bootstrap.Modal(modalElement);

            // If the privacy/terms consent modal is present, it should show
            // FIRST and reveal this announcement modal only after the user
            // clicks "I Agree". Otherwise, show immediately.
            const consentModalElement = document.getElementById('privacyConsentModal');
            if (consentModalElement) {
                // The consent modal's script calls PUPPrivacyConsentReady() to
                // show itself; the announcement waits for the agreement event.
                document.addEventListener('privacy:consent-agreed', function () {
                    modal.show();
                });
                return;
            }

            modal.show();
        });
    </script>
@endif

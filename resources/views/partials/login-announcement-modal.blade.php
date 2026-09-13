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
                    <div class="announcement-rendered-content">{!! $loginAnnouncementModal->rendered_content !!}</div>

                    @php
                        $secureModalAttachments = $loginAnnouncementModal->attachments ?? collect();
                        // ACTUAL PHOTO ATTACHMENT — rendered inline, directly
                        // under the message, using the authorized attachment
                        // endpoint (private disk + server-side authorization).
                        // No photo means no empty image container is rendered.
                        $modalPhotos = $secureModalAttachments->filter->is_image;
                        $modalFiles = $secureModalAttachments->reject->is_image;
                    @endphp

                    @if($modalPhotos->isNotEmpty())
                        <div class="mt-3">
                            @foreach($modalPhotos as $photo)
                                <figure class="announcement-viewer-photo mb-3">
                                    <img
                                        src="{{ route('announcements.attachments.show', $photo) }}"
                                        alt="Announcement photo: {{ $photo->original_filename }}"
                                        loading="lazy"
                                        decoding="async">
                                    <figcaption class="announcement-viewer-photo-caption">
                                        {{ $photo->original_filename }}
                                        <a href="{{ route('announcements.attachments.download', $photo) }}" class="ms-2">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    @endif

                    @if($modalFiles->isNotEmpty() || $loginAnnouncementModal->attachment_path)
                        <hr>
                        <div class="d-flex flex-column gap-2">
                            @foreach($modalFiles as $file)
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2">
                                    <div class="d-flex align-items-center gap-2 small">
                                        <i class="bi bi-file-earmark-text text-maroon fs-5"></i>
                                        <div>
                                            <div class="fw-semibold">{{ $file->original_filename }}</div>
                                            <div class="text-muted">
                                                {{ strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')) }}
                                                @if($file->human_size !== '0 B') &bull; {{ $file->human_size }} @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a
                                            href="{{ route('announcements.attachments.show', $file) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <a
                                            href="{{ route('announcements.attachments.download', $file) }}"
                                            class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                            @if($loginAnnouncementModal->attachment_path)
                                <a
                                    href="{{ asset($loginAnnouncementModal->attachment_path) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-outline-primary btn-sm align-self-start">
                                    View Attachment
                                </a>
                            @endif
                        </div>
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

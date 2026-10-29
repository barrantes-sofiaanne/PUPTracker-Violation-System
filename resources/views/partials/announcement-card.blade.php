{{--
    Shared announcement card — ONE design for every module.

    The Student Announcements card is the SOURCE OF TRUTH. Both the Student and
    Security announcement lists render this exact partial, so there is a single
    announcement-card implementation and no module-specific markup to drift.

    Card structure (as per the standardized design):

        Announcement Title
        By [Author]  •  [Date/Time]
        ─────────────────────────────
        Announcement message preview...
        [attachment indicator]      Read More →

    The card deliberately stays clean: it shows a short message preview and an
    attachment *indicator* only. The full content, the images and the
    per-attachment actions all live in the shared detail modal, so neither
    module renders a large attachment-management section on the card.

    MODULE DIFFERENCES ARE NOT PRESENT HERE. Authorisation, navigation and
    permitted management actions are handled by each module's own page
    controller/layout — never by branching this card's markup.

    NOTE ON ATTACHMENTS: `view` always points at the authorized
    `announcements.attachments.show` endpoint, which re-checks authorization
    server-side and streams inline from the private R2 disk (via the configured
    private disk). No public/R2 URL is ever emitted, and
    `announcements.attachments.download` is unchanged.
--}}
@php
    // Author presentation: the admin who posted the announcement.
    $cardAuthor = trim(
        (string) optional($announcement->adminInfo)->firstname . ' '
        . (string) optional($announcement->adminInfo)->lastname
    );

    if ($cardAuthor === '') {
        // Fall back to a neutral label rather than leaking an internal id.
        $cardAuthor = 'PUPTracker Administration';
    }

    $cardPosted = optional($announcement->created_at)->format('F d, Y h:i A');
    $cardHasLegacy = (bool) $announcement->attachment_path;
    // Presentation hook: true when the announcement has body text to preview.
    $cardHasPreview = trim(strip_tags((string) $announcement->content)) !== '';

    // Viewer payload. Same shape for both modules; `view` is the authorized
    // inline endpoint, `download` the authorized forced-download endpoint.
    $cardAttachments = $announcement->attachments->map(function ($file) {
        return [
            'name' => $file->original_filename,
            'ext' => $file->is_image
                ? 'image'
                : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')),
            'size' => $file->human_size,
            'date' => optional($file->created_at)->format('F d, Y'),
            'view' => route('announcements.attachments.show', $file),
            'download' => route('announcements.attachments.download', $file),
            'is_image' => (bool) $file->is_image,
        ];
    })->values()->all();

    $cardAttachmentCount = count($cardAttachments);
@endphp

<article class="card portal-card mb-4 w-100 announcement-card">

    <div class="card-body">

        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

            <div class="announcement-card-heading">

                <h4 class="fw-bold mb-1 announcement-card-title">

                    {{ $announcement->title }}

                </h4>

                {{-- Author and date/time metadata --}}
                <div class="announcement-card-meta">

                    <span class="announcement-card-author">By {{ $cardAuthor }}</span>

                    <span class="announcement-card-date">{{ $cardPosted }}</span>

                </div>

            </div>

            <i class="bi bi-megaphone-fill fs-2 announcement-card-icon" aria-hidden="true"></i>

        </div>

        <hr class="border-secondary-subtle">

        <p class="announcement-card-preview mb-0 text-dark{{ $cardHasPreview ? '' : ' is-empty' }}">
            {{ \Illuminate\Support\Str::limit(strip_tags($announcement->content), 180) }}
        </p>

        <div class="announcement-card-footer">

            @if($cardAttachmentCount > 0 || $cardHasLegacy)
                {{-- Attachment indicator only — no management section here. --}}
                <span class="badge bg-secondary announcement-card-attachment-badge">
                    <i class="bi bi-paperclip" aria-hidden="true"></i>
                    {{ $cardAttachmentCount > 0 ? $cardAttachmentCount . ' attachment(s)' : 'Attachment' }}
                </span>
            @endif

            <button type="button"
                class="btn btn-sm btn-outline-primary announcement-view-trigger announcement-read-more"
                aria-label="Read more: {{ $announcement->title }}"
                data-announcement-title="{{ $announcement->title }}"
                data-announcement-author="{{ $cardAuthor }}"
                data-announcement-posted="{{ $cardPosted }}"
                data-announcement-content-html="{{ json_encode($announcement->rendered_content) }}"
                data-announcement-content-text="{{ e($announcement->content) }}"
                data-announcement-attachments="{{ json_encode($cardAttachments) }}"
                @if($cardHasLegacy)
                data-announcement-legacy="{{ asset($announcement->attachment_path) }}"
                data-announcement-legacy-name="{{ basename($announcement->attachment_path) }}"
                @endif>
                <span>Read More</span>
                <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
            </button>

        </div>

    </div>

</article>

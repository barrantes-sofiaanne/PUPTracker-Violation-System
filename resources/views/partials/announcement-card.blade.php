{{--
    Shared announcement card.

    The Student Announcements card is the SOURCE OF TRUTH for this markup —
    the hero, title/date block, divider, message, attachment indicator, and
    View action below deliberately mirror it. Both the Student and Security
    announcement lists render this partial so the two modules cannot drift
    apart again.

    `$cardVariant` (optional, default 'student') carries the ONLY differences:

      student  — message preview (180 chars) + attachment count badge.
      security — keeps the full server-sanitized announcement body and its
                 existing per-attachment View/Download rows, so no Security
                 functionality is removed by the UI standardization.

    Everything else (shell, typography, spacing, divider, View button,
    viewer payload) is identical for both modules.

    NOTE ON ATTACHMENTS: `view` always points at the authorized
    `announcements.attachments.show` endpoint, which re-checks authorization
    server-side and streams inline from the private disk. No public/R2 URL is
    ever emitted. `announcements.attachments.download` is unchanged.
--}}
@php
    $cardVariant = $cardVariant ?? 'student';

    // Viewer payload — identical shape for both modules.
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

    $cardHasLegacy = (bool) $announcement->attachment_path;
    $cardPosted = optional($announcement->created_at)->format('F d, Y h:i A');
@endphp

<div class="card portal-card mb-4 w-100">

    <div class="card-body">

        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

            <div class="announcement-card-heading">

                <h4 class="fw-bold mb-1">

                    {{ $announcement->title }}

                </h4>

                <small class="text-muted">

                    Posted

                    {{ $cardPosted }}

                </small>

            </div>

            <i class="bi bi-megaphone-fill fs-2" style="color: var(--portal-goldenrod);"></i>

        </div>

        <hr class="border-secondary-subtle">

        @if($cardVariant === 'security')
            {{-- Security keeps the complete announcement body on the card. --}}
            <div class="announcement-rendered-content">{!! $announcement->rendered_content !!}</div>
        @else
            <p style="white-space: pre-line" class="mb-0 text-dark">
                {{ \Illuminate\Support\Str::limit(strip_tags($announcement->content), 180) }}
            </p>
        @endif

        @if(! empty($cardAttachments))
            <span class="badge bg-secondary mt-3">
                <i class="bi bi-paperclip"></i>
                {{ count($cardAttachments) }} attachment(s)
            </span>
        @endif

        @if($cardVariant === 'security' && (! empty($cardAttachments) || $cardHasLegacy))
            {{-- Preserved Security functionality: direct per-attachment actions
                 on the card. Same authorized endpoints as the viewer. --}}
            <div class="mt-3">
                <div class="fw-semibold small text-uppercase text-muted mb-2">Attachments</div>
                @foreach($announcement->attachments as $file)
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2 mb-2 bg-white">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi {{ $file->is_image ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }} fs-4 text-maroon"></i>
                            <div class="announcement-attachment-meta">
                                <div class="fw-semibold small">{{ $file->original_filename }}</div>
                                <div class="text-muted small">
                                    {{ $file->is_image ? 'IMAGE' : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')) }}
                                    @if($file->human_size !== '0 B') &bull; {{ $file->human_size }} @endif
                                    @if($file->created_at) &bull; Uploaded {{ $file->created_at->format('M d, Y') }} @endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('announcements.attachments.show', $file) }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="btn btn-sm btn-outline-primary"
                               aria-label="View attachment {{ $file->original_filename }}">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>View
                            </a>
                            <a href="{{ route('announcements.attachments.download', $file) }}"
                               class="btn btn-sm btn-outline-secondary"
                               aria-label="Download attachment {{ $file->original_filename }}">
                                <i class="bi bi-download me-1" aria-hidden="true"></i>Download
                            </a>
                        </div>
                    </div>
                @endforeach
                @if($cardHasLegacy)
                    <a href="{{ asset($announcement->attachment_path) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="btn btn-sm portal-btn-outline">
                        <i class="bi bi-paperclip me-1" aria-hidden="true"></i>
                        View Attachment
                    </a>
                @endif
            </div>
        @endif

        {{-- Standardized View action — same placement, styling, icon and
             interaction pattern as Student Announcements. --}}
        <div class="d-flex justify-content-end mt-3">
            <button type="button"
                class="btn btn-sm btn-outline-primary announcement-view-trigger"
                aria-label="View announcement: {{ $announcement->title }}"
                data-announcement-title="{{ $announcement->title }}"
                data-announcement-posted="{{ $cardPosted }}"
                data-announcement-content-html="{{ json_encode($announcement->rendered_content) }}"
                data-announcement-content-text="{{ e($announcement->content) }}"
                data-announcement-attachments="{{ json_encode($cardAttachments) }}"
                @if($cardHasLegacy)
                data-announcement-legacy="{{ asset($announcement->attachment_path) }}"
                data-announcement-legacy-name="{{ basename($announcement->attachment_path) }}"
                @endif>
                <i class="bi bi-eye me-1" aria-hidden="true"></i>View
            </button>
        </div>

    </div>

</div>

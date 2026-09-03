@extends('layouts.student')

@section('title', 'Announcements')

@push('styles')
@endpush

@section('content')

<div class="container-fluid py-1">

            {{-- Page Header --}}
            <div class="portal-hero mb-4">

                <h2 class="fw-bold mb-1">

                        Student Announcements

                </h2>

                <p class="mb-0">

                        Stay updated with the latest announcements from the administration.

                    </p>

                </div>

            </div>

            @forelse($announcements as $announcement)

                @php
                    $attachmentItems = $announcement->attachments->map(function ($file) {
                        return [
                            'name' => $file->original_filename,
                            'ext' => $file->is_image ? 'image' : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')),
                            'size' => $file->human_size,
                            'date' => optional($file->created_at)->format('F d, Y'),
                            'view' => route('announcements.attachments.show', $file),
                            'download' => route('announcements.attachments.download', $file),
                        ];
                    })->values()->all();

                    // Legacy single public attachment (old rows only).
                    if ($announcement->attachment_path) {
                        $attachmentItems[] = [
                            'name' => basename($announcement->attachment_path),
                            'ext' => 'LEGACY',
                            'size' => '',
                            'date' => '',
                            'view' => asset($announcement->attachment_path),
                            'download' => asset($announcement->attachment_path),
                        ];
                    }
                @endphp

                <button
                    type="button"
                    class="card portal-card mb-4 w-100 text-start announcement-trigger"
                    data-title="{{ $announcement->title }}"
                    data-posted="{{ $announcement->created_at->format('F d, Y h:i A') }}"
                    data-content="{{ e($announcement->content) }}"
                    data-attachments="{{ e(json_encode($attachmentItems)) }}"
                    @if(empty($attachmentItems)) data-no-attachments="1" @endif>

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">

                            <div>

                                <h4 class="fw-bold">

                                    {{ $announcement->title }}

                                </h4>

                                <small class="text-muted">

                                    Posted

                                    {{ $announcement->created_at->format('F d, Y h:i A') }}

                                </small>

                            </div>

                            <i class="bi bi-megaphone-fill fs-2" style="color: var(--portal-goldenrod);"></i>

                        </div>

                        <hr class="border-secondary-subtle">

                        <p style="white-space: pre-line" class="mb-0 text-dark">
                            {{ \Illuminate\Support\Str::limit(strip_tags($announcement->content), 180) }}
                        </p>

                        @if(! empty($attachmentItems))
                            <hr>
                            <span class="badge bg-secondary">
                                <i class="bi bi-paperclip"></i>
                                {{ count($attachmentItems) }} attachment(s) — click to view
                            </span>
                        @endif

                    </div>

                </button>

            @empty

                <div class="card portal-card">

                    <div class="card-body text-center py-5">

                        <i class="bi bi-megaphone fs-1" style="color: var(--portal-goldenrod);"></i>

                        <h4 class="mt-3">

                            No announcements available.

                        </h4>

                    </div>

                </div>

            @endforelse

            @if($announcements->hasPages())

                <div class="mt-4">

                    {{ $announcements->links() }}

                </div>

            @endif

        </div>

@endsection

@push('scripts')
<div class="modal fade" id="announcementDetailModal" tabindex="-1" aria-labelledby="announcementDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementDetailModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-3" id="announcementModalDate"></small>
                <p class="mb-0" style="white-space: pre-line;" id="announcementModalContent"></p>
                <div id="announcementModalAttachments" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('announcementDetailModal');
    if (!modalElement) {
        return;
    }

    const modal = new bootstrap.Modal(modalElement);
    const titleEl = document.getElementById('announcementDetailModalLabel');
    const dateEl = document.getElementById('announcementModalDate');
    const contentEl = document.getElementById('announcementModalContent');
    const attachmentsEl = document.getElementById('announcementModalAttachments');

    function renderAttachments(items) {
        attachmentsEl.innerHTML = '';
        if (!items || !items.length) {
            return;
        }

        const heading = document.createElement('div');
        heading.className = 'fw-semibold small text-uppercase text-muted mb-2';
        heading.textContent = 'Attachments';
        attachmentsEl.appendChild(heading);

        items.forEach(function (file) {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2 mb-2';

            const info = document.createElement('div');
            info.className = 'd-flex align-items-center gap-2';
            const icon = document.createElement('i');
            icon.className = file.ext === 'image'
                ? 'bi bi-file-earmark-image fs-4 text-maroon'
                : 'bi bi-file-earmark-text fs-4 text-maroon';
            info.appendChild(icon);

            const meta = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'fw-semibold small';
            name.textContent = file.name || 'Attachment';
            meta.appendChild(name);

            const detail = document.createElement('div');
            detail.className = 'text-muted small';
            const bits = [];
            if (file.ext) { bits.push(file.ext); }
            if (file.size) { bits.push(file.size); }
            if (file.date) { bits.push('Uploaded ' + file.date); }
            detail.textContent = bits.join(' \u2022 ');
            meta.appendChild(detail);
            info.appendChild(meta);
            row.appendChild(info);

            const actions = document.createElement('div');
            actions.className = 'd-flex gap-2';
            if (file.view) {
                const viewLink = document.createElement('a');
                viewLink.href = file.view;
                viewLink.target = '_blank';
                viewLink.rel = 'noopener noreferrer';
                viewLink.className = 'btn btn-outline-primary btn-sm';
                viewLink.innerHTML = '<i class="bi bi-eye me-1"></i>View';
                actions.appendChild(viewLink);
            }
            if (file.download) {
                const dlLink = document.createElement('a');
                dlLink.href = file.download;
                dlLink.className = 'btn btn-outline-secondary btn-sm';
                dlLink.innerHTML = '<i class="bi bi-download me-1"></i>Download';
                actions.appendChild(dlLink);
            }
            row.appendChild(actions);
            attachmentsEl.appendChild(row);
        });
    }

    document.querySelectorAll('.announcement-trigger').forEach(function (item) {
        item.addEventListener('click', function () {
            titleEl.textContent = item.dataset.title || 'Announcement';
            dateEl.textContent = 'Posted ' + (item.dataset.posted || '-');
            contentEl.textContent = item.dataset.content || '';

            let items = [];
            if (item.dataset.attachments) {
                try {
                    items = JSON.parse(item.dataset.attachments);
                } catch (e) {
                    items = [];
                }
            }
            renderAttachments(items);

            modal.show();
        });
    });
});
</script>
@endpush
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

                <button
                    type="button"
                    class="card portal-card mb-4 w-100 text-start announcement-trigger"
                    data-title="{{ $announcement->title }}"
                    data-posted="{{ $announcement->created_at->format('F d, Y h:i A') }}"
                    data-content="{{ e($announcement->content) }}"
                    data-attachment="{{ $announcement->attachment_path ? asset($announcement->attachment_path) : '' }}">

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

                        @if($announcement->attachment_path)
                            <hr>
                            <span class="btn btn-outline-primary disabled">
                                <i class="bi bi-paperclip"></i>
                                Attachment available (click to view)
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementDetailModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <small class="text-muted d-block mb-3" id="announcementModalDate"></small>
                <p class="mb-0" style="white-space: pre-line;" id="announcementModalContent"></p>
            </div>
            <div class="modal-footer">
                <a href="#" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary d-none" id="announcementModalAttachment">View Attachment</a>
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
    const attachmentEl = document.getElementById('announcementModalAttachment');

    document.querySelectorAll('.announcement-trigger').forEach(function (item) {
        item.addEventListener('click', function () {
            titleEl.textContent = item.dataset.title || 'Announcement';
            dateEl.textContent = 'Posted ' + (item.dataset.posted || '-');
            contentEl.textContent = item.dataset.content || '';

            if (item.dataset.attachment) {
                attachmentEl.href = item.dataset.attachment;
                attachmentEl.classList.remove('d-none');
            } else {
                attachmentEl.href = '#';
                attachmentEl.classList.add('d-none');
            }

            modal.show();
        });
    });
});
</script>
@endpush
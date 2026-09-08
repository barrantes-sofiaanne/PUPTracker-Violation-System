@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')

@php
    $currentAdmin = Auth::guard('admin')->user();
    $isItAdmin = (bool) $currentAdmin?->isItAdministrator();
@endphp

<div class="page-header-modern d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="fw-bold mb-1">Announcements</h2>
        <p class="text-muted mb-0">Publish and manage announcements for students and staff.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.notifications') }}" class="admin-btn admin-btn-neutral">
            Notifications
            @if($unreadNotificationCount > 0)
                <span class="badge bg-danger ms-2">{{ $unreadNotificationCount }}</span>
            @endif
        </a>
        <a href="{{ route('admin.announcements.create') }}" class="admin-btn admin-btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            New Announcement
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">Posted announcements</div>
                        <div class="display-6 fw-bold">{{ $announcementStats['total'] }}</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-megaphone-fill fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small text-muted fw-semibold">With attachments</div>
                        <div class="display-6 fw-bold">{{ $announcementStats['with_attachments'] }}</div>
                    </div>
                    <div class="rounded-circle bg-maroon-soft p-3 text-maroon">
                        <i class="bi bi-paperclip fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs" id="announcementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="announcement-tab" data-bs-toggle="tab" data-bs-target="#announcement-pane" type="button" role="tab">
                    <i class="bi bi-megaphone me-1"></i>
                    Announcements
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="configure-tab" data-bs-toggle="tab" data-bs-target="#configure-pane" type="button" role="tab">
                    <i class="bi bi-gear me-1"></i>
                    Configure Announcements
                </button>
            </li>
        </ul>
    </div>

    <div class="card-body p-0">
        <div class="tab-content">
            {{-- Announcements tab (all announcements, view enlarges) --}}
            <div class="tab-pane fade show active" id="announcement-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 announcements-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Posted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($announcements as $announcement)
                                @php
                                    $attachmentCount = $announcement->attachments->count();

                                    // Precompute attachment payload (plain PHP; Blade
                                    // @json cannot balance arrow functions inline).
                                    $attachmentItems = $announcement->attachments->map(function ($file) {
                                        return [
                                            'name' => $file->original_filename,
                                            'ext' => $file->is_image ? 'image' : strtoupper((string) \Illuminate\Support\Str::after((string) $file->mime_type, '/')),
                                            'size' => $file->human_size,
                                            'date' => optional($file->created_at)->format('F d, Y'),
                                            'view' => route('announcements.attachments.show', $file),
                                            'download' => route('announcements.attachments.download', $file),
                                            'is_image' => (bool) $file->is_image,
                                        ];
                                    })->values();
                                    $viewAttachmentsJson = json_encode($attachmentItems);
                                    $renderedContentJson = json_encode($announcement->rendered_content);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $announcement->title }}</div>
                                        @if($attachmentCount > 0 || $announcement->attachment_path)
                                            <span class="badge bg-secondary mt-2">
                                                <i class="bi bi-paperclip me-1"></i>{{ $attachmentCount > 0 ? $attachmentCount . ' attachment(s)' : 'Attachment' }}
                                            </span>
                                        @endif
                                        @if($announcement->show_on_login)
                                            <span class="badge bg-info text-dark mt-2">Login Modal</span>
                                        @endif
                                        <div class="text-muted small mt-2">
                                            {{ Str::limit(strip_tags($announcement->content), 80) }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ optional($announcement->adminInfo)->firstname ? optional($announcement->adminInfo)->firstname . ' ' . optional($announcement->adminInfo)->lastname : 'Admin #' . $announcement->admin_id }}
                                    </td>
                                    <td>{{ optional($announcement->created_at)->format('M d, Y h:i A') }}</td>
                                    <td class="text-end">
                                        <button type="button"
                                            class="admin-btn admin-btn-view admin-btn-sm admin-announcement-view-trigger"
                                            data-title="{{ $announcement->title }}"
                                            data-posted="{{ optional($announcement->created_at)->format('F d, Y h:i A') }}"
                                            data-content-html="{{ $renderedContentJson }}"
                                            data-content-text="{{ Str::limit(strip_tags($announcement->content), 4000) }}"
                                            data-attachments="{{ $viewAttachmentsJson }}"
                                            @if($announcement->attachment_path)
                                            data-legacy-attachment="{{ asset($announcement->attachment_path) }}"
                                            data-legacy-name="{{ basename($announcement->attachment_path) }}"
                                            @endif>
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty-state
                                    :colspan="4"
                                    icon="bi-megaphone"
                                    title="No announcements yet"
                                    message="No announcements have been posted. Create the first one to keep students informed."
                                    :clearUrl="null" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 d-flex justify-content-center">
                    {{ $announcements->links() }}
                </div>
            </div>

            {{-- Configure Announcements tab (only own announcements, edit/delete) --}}
            <div class="tab-pane fade" id="configure-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 announcements-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Posted</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myAnnouncements as $announcement)
                                @php
                                    $attachmentCount = $announcement->attachments->count();
                                    $editContentJson = json_encode($announcement->rendered_content);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $announcement->title }}</div>
                                        @if($attachmentCount > 0 || $announcement->attachment_path)
                                            <span class="badge bg-secondary mt-2">
                                                <i class="bi bi-paperclip me-1"></i>{{ $attachmentCount > 0 ? $attachmentCount . ' attachment(s)' : 'Attachment' }}
                                            </span>
                                        @endif
                                        <div class="text-muted small mt-2">
                                            {{ Str::limit(strip_tags($announcement->content), 80) }}
                                        </div>
                                    </td>
                                    <td>{{ optional($announcement->created_at)->format('M d, Y h:i A') }}</td>
                                    <td class="text-end">
                                        <button type="button"
                                            class="admin-btn admin-btn-view admin-btn-sm me-1 admin-announcement-edit-trigger"
                                            data-announcement-id="{{ $announcement->announcement_id }}"
                                            data-title="{{ $announcement->title }}"
                                            data-content="{{ $editContentJson }}"
                                            data-action="{{ route('admin.announcements.update', $announcement) }}"
                                            @if($announcement->attachment_path)
                                            data-current-name="{{ basename($announcement->attachment_path) }}"
                                            data-current-view="{{ asset($announcement->attachment_path) }}"
                                            @elseif($announcement->attachments->isNotEmpty())
                                            data-current-name="{{ $announcement->attachments->first()->original_filename }}"
                                            data-current-view="{{ route('announcements.attachments.show', $announcement->attachments->first()) }}"
                                            @endif
                                            @if($announcement->show_on_login)
                                            data-show-on-login="1"
                                            data-login-duration="{{ $announcement->login_modal_expires_at ? 'custom' : '1_week' }}"
                                            data-login-expiry="{{ optional($announcement->login_modal_expires_at)->toDateString() }}"
                                            @endif>
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                        <form action="{{ route('admin.announcements.destroy', $announcement) }}" method="POST" class="d-inline-block" data-confirm="This will permanently delete the announcement. This action cannot be undone."
                                              data-confirm-title="Delete Announcement?" data-confirm-confirm-label="Delete">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty-state
                                    :colspan="3"
                                    icon="bi-megaphone"
                                    title="No announcements by you"
                                    message="You have not posted any announcements yet."
                                    :clearUrl="null" />
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-3 d-flex justify-content-center">
                    {{ $myAnnouncements->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- View Announcement modal --}}
<div class="modal fade admin-modal admin-announcement-view-modal" id="announcementViewModal" tabindex="-1" aria-labelledby="announcementViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="announcementViewModalLabel">Announcement</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body admin-modal-body">
                <div class="admin-announcement-view-head">
                    <h4 class="admin-announcement-view-title" id="adminAnnouncementViewTitle"></h4>
                    <div class="text-muted small" id="adminAnnouncementViewDate"></div>
                </div>
                <div class="admin-announcement-view-content announcement-rendered-content" id="adminAnnouncementViewContent"></div>
                <div id="adminAnnouncementViewAttachments" class="mt-4"></div>
            </div>
            <div class="modal-footer admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Announcement modal --}}
<div class="modal fade admin-modal admin-announcement-edit-modal" id="announcementEditModal" tabindex="-1" aria-labelledby="announcementEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header admin-modal-header">
                <h5 class="modal-title admin-modal-title" id="announcementEditModalLabel">Edit Announcement</h5>
                <button type="button" class="btn-close admin-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="announcementEditForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body admin-modal-body">
                    <div class="mb-3">
                        <label for="announcementEditTitle" class="form-label fw-semibold">Title</label>
                        <input type="text" class="form-control" id="announcementEditTitle" name="title" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content</label>
                        <input type="hidden" id="announcementEditContent" name="content">
                        <div id="announcementQuillEditor" class="announcement-quill-editor"></div>
                    </div>

                    <div class="mb-3">
                        <label for="announcementEditFile" class="form-label fw-semibold">Attach File (Image or PDF, max 5MB)</label>
                        <input type="file" class="form-control" id="announcementEditFile" name="attachment" accept=".jpg,.jpeg,.png,.gif,.pdf">
                        <div class="form-text">PDF, JPG, JPEG, PNG, GIF. Maximum 5MB per file.</div>
                    </div>

                    <div class="border rounded p-3 bg-light" id="announcementEditCurrentAttachmentWrap" style="display:none;">
                        <strong class="d-block mb-2">Current Attachment</strong>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <span class="small text-truncate" id="announcementEditCurrentAttachmentName" style="max-width: 100%;"></span>
                            <a href="#" id="announcementEditCurrentAttachmentLink" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-view admin-btn-sm">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="announcementEditRemoveAttachment" name="remove_attachment" value="1">
                            <label class="form-check-label" for="announcementEditRemoveAttachment">Remove this file</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer admin-modal-footer d-flex justify-content-end gap-2">
                    <button type="button" class="admin-btn admin-btn-neutral" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="bi bi-check2 me-1"></i>Save Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
    .announcement-quill-editor {
        background: #fff;
        border-radius: 0.6rem;
        min-height: 180px;
    }

    .announcement-quill-editor .ql-toolbar {
        border-radius: 0.6rem 0.6rem 0 0;
        border: 1px solid #d8cdd1;
    }

    .announcement-quill-editor .ql-container {
        min-height: 140px;
        border-radius: 0 0 0.6rem 0.6rem;
        border: 1px solid #d8cdd1;
        border-top: 0;
        font-size: 0.98rem;
        font-family: inherit;
    }

    .announcement-quill-editor .ql-editor {
        min-height: 140px;
        color: #3c3134;
        line-height: 1.7;
    }

    .admin-announcement-view-title {
        color: #7f0000;
        font-weight: 800;
        margin: 0 0 0.25rem;
        font-size: 1.6rem;
        line-height: 1.3;
    }

    .admin-announcement-view-content {
        color: #444;
        line-height: 1.7;
        font-size: 0.98rem;
        overflow-wrap: anywhere;
    }

    .admin-announcement-view-modal .modal-body {
        max-height: min(72vh, 70rem);
        overflow-y: auto;
    }

    .admin-announcement-edit-modal textarea,
    .admin-announcement-edit-modal input[type="text"],
    .admin-announcement-edit-modal input[type="file"] {
        min-height: 42px;
    }

    .admin-announcement-edit-modal textarea {
        line-height: 1.6;
    }

    @media (max-width: 575.98px) {
        .admin-announcement-view-title {
            font-size: 1.25rem;
        }

        .admin-announcement-view-modal .modal-dialog,
        .admin-announcement-edit-modal .modal-dialog {
            margin: 0.25rem;
        }
    }
</style>
@endpush

@push('scripts')
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/js/announcements.js'])
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const viewModalEl = document.getElementById('announcementViewModal');
    const editModalEl = document.getElementById('announcementEditModal');

    // ---- View modal ----
    if (viewModalEl) {
        const viewModal = new bootstrap.Modal(viewModalEl);
        const titleEl = document.getElementById('adminAnnouncementViewTitle');
        const dateEl = document.getElementById('adminAnnouncementViewDate');
        const contentEl = document.getElementById('adminAnnouncementViewContent');
        const attachmentsEl = document.getElementById('adminAnnouncementViewAttachments');

        function appendAttachmentRow(parent, file) {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between flex-wrap gap-2 border rounded p-2 mb-2 bg-white';

            const info = document.createElement('div');
            info.className = 'd-flex align-items-center gap-2';

            const icon = document.createElement('i');
            icon.className = file.is_image
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
                const a = document.createElement('a');
                a.href = file.view;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = 'admin-btn admin-btn-view admin-btn-sm';
                a.innerHTML = '<i class="bi bi-eye me-1"></i>View';
                actions.appendChild(a);
            }
            if (file.download) {
                const a = document.createElement('a');
                a.href = file.download;
                a.className = 'admin-btn admin-btn-view admin-btn-sm';
                a.innerHTML = '<i class="bi bi-download me-1"></i>Download';
                actions.appendChild(a);
            }
            row.appendChild(actions);
            parent.appendChild(row);
        }

        function renderView(data) {
            titleEl.textContent = data.title || 'Announcement';
            dateEl.textContent = data.posted ? 'Posted ' + data.posted : '';

            // Render server-sanitized HTML when available; otherwise fall back
            // to safe plain text.
            if (data.contentHtml) {
                contentEl.innerHTML = data.contentHtml;
            } else {
                contentEl.textContent = data.content || '';
            }

            attachmentsEl.innerHTML = '';
            let hasAttachments = false;

            const items = data.attachments || [];
            if (items.length) {
                hasAttachments = true;
                const heading = document.createElement('div');
                heading.className = 'fw-semibold small text-uppercase text-muted mb-2';
                heading.textContent = 'Attachments';
                attachmentsEl.appendChild(heading);
                items.forEach(function (file) {
                    appendAttachmentRow(attachmentsEl, file);
                });
            }

            if (data.legacyAttachment) {
                hasAttachments = true;
                if (!items.length) {
                    const heading = document.createElement('div');
                    heading.className = 'fw-semibold small text-uppercase text-muted mb-2';
                    heading.textContent = 'Attachments';
                    attachmentsEl.appendChild(heading);
                }
                appendAttachmentRow(attachmentsEl, {
                    name: data.legacyName || 'Attachment',
                    ext: 'LEGACY',
                    is_image: false,
                    view: data.legacyAttachment,
                    download: data.legacyAttachment,
                });
            }

            if (!hasAttachments) {
                attachmentsEl.innerHTML = '';
            }
        }

        document.querySelectorAll('.admin-announcement-view-trigger').forEach(function (btn) {
            btn.addEventListener('click', function () {
                let attachments = [];
                if (btn.dataset.attachments) {
                    try {
                        attachments = JSON.parse(btn.dataset.attachments);
                    } catch (e) {
                        attachments = [];
                    }
                }

                let contentHtml = null;
                if (btn.dataset.contentHtml) {
                    try {
                        contentHtml = JSON.parse(btn.dataset.contentHtml);
                    } catch (e) {
                        contentHtml = null;
                    }
                }

                renderView({
                    title: btn.dataset.title,
                    posted: btn.dataset.posted,
                    content: btn.dataset.contentText || '',
                    contentHtml: contentHtml,
                    attachments: attachments,
                    legacyAttachment: btn.dataset.legacyAttachment || null,
                    legacyName: btn.dataset.legacyName || null,
                });
                viewModal.show();
            });
        });
    }

    // ---- Edit modal ----
    if (editModalEl) {
        const editModal = new bootstrap.Modal(editModalEl);
        const editForm = document.getElementById('announcementEditForm');
        const editTitle = document.getElementById('announcementEditTitle');
        const editContent = document.getElementById('announcementEditContent');
        const editFile = document.getElementById('announcementEditFile');
        const currentWrap = document.getElementById('announcementEditCurrentAttachmentWrap');
        const currentName = document.getElementById('announcementEditCurrentAttachmentName');
        const currentLink = document.getElementById('announcementEditCurrentAttachmentLink');
        const removeCheck = document.getElementById('announcementEditRemoveAttachment');

        let activeQuill = null;

        document.querySelectorAll('.admin-announcement-edit-trigger').forEach(function (btn) {
            btn.addEventListener('click', function () {
                editForm.action = btn.dataset.action || '';
                editTitle.value = btn.dataset.title || '';

                // Load existing content into Quill (sanitized HTML or text).
                const rawContent = btn.dataset.content || '';
                if (activeQuill) {
                    activeQuill.clipboard.dangerouslyPasteHTML(0, rawContent || '');
                } else {
                    editContent.value = rawContent;
                }

                editFile.value = '';
                if (removeCheck) { removeCheck.checked = false; }

                if (currentWrap && btn.dataset.currentName && btn.dataset.currentView) {
                    currentName.textContent = btn.dataset.currentName;
                    currentLink.href = btn.dataset.currentView;
                    currentWrap.style.display = '';
                } else if (currentWrap) {
                    currentWrap.style.display = 'none';
                }

                editModal.show();
            });
        });

        // Initialize Quill once the modal exists on the page.
        if (typeof window.initAnnouncementQuill === 'function') {
            activeQuill = window.initAnnouncementQuill('announcementQuillEditor', 'announcementEditContent');
        }

        // Switching files replaces the current legacy attachment via the
        // existing server-side single-file handling; leaving the input empty
        // keeps the existing file untouched unless "Remove this file" is ticked.
        if (editFile) {
            editFile.addEventListener('change', function () {
                if (editFile.files && editFile.files.length) {
                    if (removeCheck) { removeCheck.checked = false; }
                }
            });
        }
    }
});
</script>
@endpush

@endsection

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
                                            class="admin-btn admin-btn-view admin-btn-sm announcement-view-trigger"
                                            data-announcement-title="{{ $announcement->title }}"
                                            data-announcement-posted="{{ optional($announcement->created_at)->format('F d, Y h:i A') }}"
                                            data-announcement-content-html="{{ $renderedContentJson }}"
                                            data-announcement-content-text="{{ Str::limit(strip_tags($announcement->content), 4000) }}"
                                            data-announcement-attachments="{{ $viewAttachmentsJson }}"
                                            @if($announcement->attachment_path)
                                            data-announcement-legacy="{{ asset($announcement->attachment_path) }}"
                                            data-announcement-legacy-name="{{ basename($announcement->attachment_path) }}"
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
                                            data-show-on-login="{{ $announcement->show_on_login ? '1' : '0' }}"
data-login-duration="{{ $announcement->login_modal_expires_at ? 'custom' : '1_week' }}"
data-login-expiry="{{ optional($announcement->login_modal_expires_at)->toDateString() }}"
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

{{-- View Announcement modal — shared interface, identical across modules.
     Renders TITLE -> MESSAGE -> ACTUAL PHOTO ATTACHMENT (photo streamed from
     the authorized attachment endpoint on the private disk). --}}
@include('partials.announcement-view-modal')

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
                    @if($isItAdmin)
    <div class="mb-4">
        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                value="1"
                id="announcementEditShowOnLogin"
                name="show_on_login">

            <label
                class="form-check-label fw-semibold"
                for="announcementEditShowOnLogin">
                Show this announcement as a login modal across modules
            </label>
        </div>

        <div class="form-text">
            When enabled, this announcement will appear on the login page until the selected expiry date.
        </div>
    </div>

    <div class="mb-4 d-none" id="announcementEditLoginModalDurationWrap">
        <label
            for="announcementEditLoginModalDuration"
            class="form-label fw-semibold">
            Duration shown as login modal
        </label>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <select
                    class="form-select"
                    id="announcementEditLoginModalDuration"
                    name="login_modal_duration">

                    <option value="1_week">1 Week</option>
                    <option value="1_month">1 Month</option>
                    <option value="custom">Custom Date</option>
                </select>
            </div>

            <div
                class="col-12 col-md-4 d-none"
                id="announcementEditLoginModalCustomDateWrap">

                <input
                    type="date"
                    class="form-control"
                    id="announcementEditLoginModalExpiresAt"
                    name="login_modal_expires_at"
                    min="{{ now()->toDateString() }}">

                <div class="form-text">
                    Select the date until this login modal is shown.
                </div>
            </div>
        </div>
    </div>
@endif
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

@media (max-width: 767.98px) {
    .admin-announcement-edit-modal .modal-dialog {
        width: auto;
        max-width: none;
        margin: 0.5rem;
    }

    .admin-announcement-edit-modal .modal-content {
        border-radius: 0.85rem;
        overflow: hidden;
    }

    .admin-announcement-edit-modal .modal-header {
        padding: 0.9rem 1rem;
    }

    .admin-announcement-edit-modal .modal-title {
        font-size: 1.15rem;
        line-height: 1.3;
        padding-right: 2.5rem;
    }

    .admin-announcement-edit-modal .modal-body {
        padding: 1rem;
        overflow-x: hidden;
    }

    .admin-announcement-edit-modal .modal-footer {
        padding: 0.85rem 1rem;
        flex-wrap: wrap;
    }

    .admin-announcement-edit-modal .form-control,
    .admin-announcement-edit-modal .form-select {
        width: 100%;
        max-width: 100%;
    }

    .admin-announcement-edit-modal input[type="file"] {
        max-width: 100%;
        font-size: 0.9rem;
    }

    .admin-announcement-edit-modal .announcement-quill-editor {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-toolbar {
        overflow-x: auto;
        white-space: nowrap;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-container {
        max-width: 100%;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-editor {
        min-height: 160px;
        overflow-wrap: anywhere;
    }

    .admin-announcement-edit-modal .admin-modal-footer {
        gap: 0.5rem !important;
    }
}

@media (max-width: 767.98px) {
    .admin-announcement-edit-modal .modal-dialog {
        width: auto;
        max-width: none;
        margin: 0.5rem;
    }

    .admin-announcement-edit-modal .modal-content {
        border-radius: 0.85rem;
        overflow: hidden;
    }

    .admin-announcement-edit-modal .modal-header {
        padding: 0.9rem 1rem;
    }

    .admin-announcement-edit-modal .modal-title {
        font-size: 1.15rem;
        line-height: 1.3;
        padding-right: 2.5rem;
    }

    .admin-announcement-edit-modal .modal-body {
        padding: 1rem;
        overflow-x: hidden;
    }

    .admin-announcement-edit-modal .modal-footer {
        padding: 0.85rem 1rem;
        flex-wrap: wrap;
    }

    .admin-announcement-edit-modal .form-control,
    .admin-announcement-edit-modal .form-select {
        width: 100%;
        max-width: 100%;
    }

    .admin-announcement-edit-modal input[type="file"] {
        max-width: 100%;
        font-size: 0.9rem;
    }

    .admin-announcement-edit-modal .announcement-quill-editor {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-toolbar {
        overflow-x: auto;
        white-space: nowrap;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-container {
        max-width: 100%;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-editor {
        min-height: 160px;
        overflow-wrap: anywhere;
    }
}

@media (max-width: 575.98px) {
    .admin-announcement-view-title {
        font-size: 1.25rem;
    }

    .admin-announcement-view-modal .modal-dialog {
        margin: 0.25rem;
    }

    .admin-announcement-edit-modal .modal-dialog {
        margin: 0.25rem;
    }

    .admin-announcement-edit-modal .modal-header {
        padding: 0.8rem 0.9rem;
    }

    .admin-announcement-edit-modal .modal-title {
        font-size: 1rem;
    }

    .admin-announcement-edit-modal .modal-body {
        padding: 0.85rem;
    }

    .admin-announcement-edit-modal .modal-footer {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-announcement-edit-modal .modal-footer .admin-btn {
        width: 100%;
    }

    .admin-announcement-edit-modal .announcement-quill-editor .ql-editor {
        min-height: 150px;
    }
}

/* =========================================================
   Announcement Edit Modal - Mobile Override
   ========================================================= */

@media (max-width: 767.98px) {

    /* Force the announcement modal to use the available viewport */
    .main-content .admin-announcement-edit-modal .modal-dialog {
        width: calc(100vw - 1rem) !important;
        max-width: none !important;
        margin: 0.5rem auto !important;
        height: calc(100vh - 1rem);
    }

    .main-content .admin-announcement-edit-modal .modal-content {
        width: 100% !important;
        max-width: 100% !important;
        height: 100%;
        max-height: calc(100vh - 1rem);
        border-radius: 0.85rem;
        overflow: hidden;
    }

    /* Header */
    .main-content .admin-announcement-edit-modal .modal-header {
        flex: 0 0 auto;
        padding: 0.85rem 1rem;
        gap: 0.5rem;
    }

    .main-content .admin-announcement-edit-modal .modal-title {
        min-width: 0;
        max-width: calc(100% - 50px);
        font-size: 1.1rem;
        line-height: 1.25;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .main-content .admin-announcement-edit-modal .admin-modal-close {
        flex: 0 0 44px;
        width: 44px;
        height: 44px;
        min-width: 44px;
        min-height: 44px;
        margin-left: auto !important;
    }

    /* Body becomes the scrollable area */
    .main-content .admin-announcement-edit-modal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        padding: 1rem;
        overflow-x: hidden;
        overflow-y: auto;
    }

    /* Prevent every form element from overflowing */
    .main-content .admin-announcement-edit-modal .form-control,
    .main-content .admin-announcement-edit-modal .form-select {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }

    /* File input */
    .main-content .admin-announcement-edit-modal input[type="file"] {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        font-size: 0.85rem;
    }

    /* Quill */
    .main-content .admin-announcement-edit-modal .announcement-quill-editor {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow: hidden;
    }

    .main-content .admin-announcement-edit-modal .ql-toolbar {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
    }

    .main-content .admin-announcement-edit-modal .ql-container {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    .main-content .admin-announcement-edit-modal .ql-editor {
        min-height: 150px;
        max-width: 100%;
        overflow-x: hidden;
        overflow-wrap: anywhere;
    }

    /* Current attachment */
    .main-content .admin-announcement-edit-modal
    #announcementEditCurrentAttachmentWrap {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .main-content .admin-announcement-edit-modal
    #announcementEditCurrentAttachmentName {
        max-width: calc(100% - 80px) !important;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Footer */
    .main-content .admin-announcement-edit-modal .modal-footer {
        flex: 0 0 auto;
        padding: 0.75rem 1rem;
        gap: 0.5rem !important;
    }
}

@media (max-width: 575.98px) {

    .main-content .admin-announcement-edit-modal .modal-dialog {
        width: calc(100vw - 0.5rem) !important;
        height: calc(100vh - 0.5rem);
        margin: 0.25rem auto !important;
    }

    .main-content .admin-announcement-edit-modal .modal-content {
        max-height: calc(100vh - 0.5rem);
        border-radius: 0.75rem;
    }

    .main-content .admin-announcement-edit-modal .modal-header {
        padding: 0.7rem 0.85rem;
    }

    .main-content .admin-announcement-edit-modal .modal-title {
        font-size: 1rem;
    }

    .main-content .admin-announcement-edit-modal .modal-body {
        padding: 0.85rem;
    }

    .main-content .admin-announcement-edit-modal .modal-footer {
        flex-direction: column;
        align-items: stretch;
        padding: 0.7rem 0.85rem;
    }

    .main-content .admin-announcement-edit-modal .modal-footer .admin-btn {
        width: 100%;
        min-height: 42px;
    }

    .main-content .admin-announcement-edit-modal .ql-toolbar {
        font-size: 0.85rem;
    }

    .main-content .admin-announcement-edit-modal .ql-editor {
        min-height: 140px;
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
    const editModalEl = document.getElementById('announcementEditModal');

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
        const showOnLogin = document.getElementById('announcementEditShowOnLogin');
const loginDurationWrap = document.getElementById('announcementEditLoginModalDurationWrap');
const loginDuration = document.getElementById('announcementEditLoginModalDuration');
const loginCustomDateWrap = document.getElementById('announcementEditLoginModalCustomDateWrap');
const loginExpiry = document.getElementById('announcementEditLoginModalExpiresAt');

        let activeQuill = null;

        function toggleLoginModalSettings() {
    if (!showOnLogin || !loginDurationWrap) {
        return;
    }

    loginDurationWrap.classList.toggle('d-none', !showOnLogin.checked);
    toggleCustomLoginDate();
}

function toggleCustomLoginDate() {
    if (!loginCustomDateWrap || !loginDuration) {
        return;
    }

    loginCustomDateWrap.classList.toggle(
        'd-none',
        loginDuration.value !== 'custom'
    );
}

        document.querySelectorAll('.admin-announcement-edit-trigger').forEach(function (btn) {
            btn.addEventListener('click', function () {
                editForm.action = btn.dataset.action || '';
                editTitle.value = btn.dataset.title || '';
                // Load login modal settings.
if (showOnLogin) {
    showOnLogin.checked = btn.dataset.showOnLogin === '1';
}

if (loginDuration) {
    loginDuration.value = btn.dataset.loginDuration || '1_week';
}

if (loginExpiry) {
    loginExpiry.value = btn.dataset.loginExpiry || '';
}

toggleLoginModalSettings();

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
                if (showOnLogin) {
    showOnLogin.addEventListener('change', toggleLoginModalSettings);
}

if (loginDuration) {
    loginDuration.addEventListener('change', toggleCustomLoginDate);
}
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
